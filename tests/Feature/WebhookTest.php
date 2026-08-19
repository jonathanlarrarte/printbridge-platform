<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_listar_y_borrar_webhook(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];

        $crear = $this->postJson('/v1/webhooks', [
            'url' => 'https://ejemplo.com/hook',
            'subscribed_events' => ['job.printed'],
        ], $headers);
        $crear->assertCreated()->assertJsonStructure(['data' => ['id', 'url'], 'secret']);

        $id = $crear->json('data.id');
        $this->getJson('/v1/webhooks', $headers)->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/v1/webhooks/{$id}", [], $headers)->assertNoContent();
        $this->assertDatabaseMissing('webhooks_configurados', ['id' => $id]);
    }

    public function test_rechaza_evento_no_soportado(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];

        $this->postJson('/v1/webhooks', [
            'url' => 'https://ejemplo.com/hook',
            'subscribed_events' => ['algo.inventado'],
        ], $headers)->assertStatus(422);
    }

    public function test_evento_de_trabajo_dispara_entrega_firmada(): void
    {
        Http::fake(['ejemplo.com/*' => Http::response(['ok' => true], 200)]);

        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $token = $empresa->createToken('t')->plainTextToken;

        $webhook = $this->postJson('/v1/webhooks', [
            'url' => 'https://ejemplo.com/hook',
            'subscribed_events' => ['job.printed'],
        ], ['Authorization' => "Bearer {$token}"])->json();

        $agenteToken = $this->postJson('/agent/register', [
            'installation_id' => 'pos-x', 'client_code' => 'demo',
        ])->json('token');

        $this->postJson('/agent/events', [
            'event_type' => 'job.printed',
            'external_job_id' => 'job-1',
            'target' => 'receipt',
        ], ['Authorization' => "Bearer {$agenteToken}"])->assertStatus(202);

        Http::assertSent(function ($request) use ($webhook) {
            $firmaEsperada = 'sha256='.hash_hmac('sha256', $request->body(), $webhook['secret']);

            return $request->url() === 'https://ejemplo.com/hook'
                && $request->header('X-PrintBridge-Signature')[0] === $firmaEsperada;
        });

        $this->assertDatabaseHas('webhook_entregas', ['webhook_id' => $webhook['data']['id'], 'status_http' => 200]);
    }

    public function test_evento_no_suscrito_no_dispara_entrega(): void
    {
        Http::fake();

        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $token = $empresa->createToken('t')->plainTextToken;

        $this->postJson('/v1/webhooks', [
            'url' => 'https://ejemplo.com/hook',
            'subscribed_events' => ['job.failed'],
        ], ['Authorization' => "Bearer {$token}"]);

        $agenteToken = $this->postJson('/agent/register', [
            'installation_id' => 'pos-x', 'client_code' => 'demo',
        ])->json('token');

        $this->postJson('/agent/events', [
            'event_type' => 'job.printed',
            'external_job_id' => 'job-1',
            'target' => 'receipt',
        ], ['Authorization' => "Bearer {$agenteToken}"])->assertStatus(202);

        Http::assertNothingSent();
    }
}

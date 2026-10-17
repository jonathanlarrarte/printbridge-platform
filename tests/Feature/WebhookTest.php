<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\WebhookConfigurado;
use App\Services\EventosPlataforma;
use App\Support\EventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

    public function test_entrega_con_respuesta_no_2xx_queda_sin_marcar_como_entregada(): void
    {
        // Bajo la cola 'sync' de testing (phpunit.xml) el job corre inline:
        // la excepcion de $respuesta->throw() se propaga hasta aca mismo en
        // vez de quedar en un worker separado (como en produccion, que usa
        // Redis real -- ver .env). Lo que importa para este test es que la
        // fila en webhook_entregas ya quedo guardada ANTES de que esa
        // excepcion se propague.
        Http::fake(['ejemplo.com/*' => Http::response('Internal Server Error', 500)]);

        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $token = $empresa->createToken('t')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $webhook = $this->postJson('/v1/webhooks', [
            'url' => 'https://ejemplo.com/hook',
            'subscribed_events' => ['agent.offline'],
        ], $headers)->json();

        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-x', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);

        try {
            EventosPlataforma::registrarTransicionAgente($agente, EventType::AGENT_OFFLINE);
        } catch (\Throwable) {
            // Esperado bajo la cola sync -- ver comentario arriba.
        }

        $this->assertDatabaseHas('webhook_entregas', [
            'webhook_id' => $webhook['data']['id'],
            'status_http' => 500,
            'entregado_en' => null,
        ]);

        $entrega = $this->getJson("/v1/webhooks/{$webhook['data']['id']}/deliveries", $headers)->json('data.0');
        $this->assertFalse($entrega['successful']);
        $this->assertSame(500, $entrega['http_status']);
    }

    public function test_fallo_de_red_guarda_la_entrega_sin_http_status(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $token = $empresa->createToken('t')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $webhook = $this->postJson('/v1/webhooks', [
            'url' => 'https://ejemplo.com/hook',
            'subscribed_events' => ['agent.offline'],
        ], $headers)->json();

        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-x', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);

        try {
            EventosPlataforma::registrarTransicionAgente($agente, EventType::AGENT_OFFLINE);
        } catch (\Throwable) {
            // Idem al test anterior.
        }

        $this->assertDatabaseHas('webhook_entregas', [
            'webhook_id' => $webhook['data']['id'],
            'status_http' => null,
            'entregado_en' => null,
        ]);
    }

    public function test_no_se_puede_ver_las_entregas_de_un_webhook_de_otra_empresa(): void
    {
        // Creado directo por Eloquent (no por POST /v1/webhooks): autenticar
        // dos empresas distintas con requests HTTP separados dentro del
        // mismo test de PHPUnit no funciona -- el guard de Sanctum cachea el
        // primer usuario resuelto para el resto del test (ver el comentario
        // equivalente en TrabajoRemotoTest). Esto no refleja produccion (un
        // proceso PHP nuevo por request), asi que la empresa B se prepara
        // sin pasar por HTTP, y la unica llamada real es la de la empresa A.
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $webhookB = WebhookConfigurado::create([
            'empresa_id' => $empresaB->id,
            'url' => 'https://ejemplo.com/hook',
            'eventos_suscritos' => ['job.printed'],
            'secreto' => 'x',
            'activo' => true,
            'creado_en' => now(),
        ]);

        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $tokenA = $empresaA->createToken('t')->plainTextToken;

        $this->getJson("/v1/webhooks/{$webhookB->id}/deliveries", ['Authorization' => "Bearer {$tokenA}"])->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgenteIngestaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrar_crea_un_agente_para_la_empresa_del_codigo(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);

        $respuesta = $this->postJson('/agente/registrar', [
            'instalacion_id' => 'pos-test-1',
            'cliente_codigo' => 'demo',
            'nombre_descriptivo' => 'Caja 1',
            'version_agente' => '1.0.0',
        ]);

        $respuesta->assertOk()->assertJsonStructure(['agente_id', 'token']);
        $this->assertDatabaseHas('agentes', ['instalacion_id' => 'pos-test-1', 'empresa_id' => $empresa->id]);
    }

    public function test_registrar_rechaza_codigo_de_cliente_desconocido(): void
    {
        $this->postJson('/agente/registrar', [
            'instalacion_id' => 'pos-test-1',
            'cliente_codigo' => 'no-existe',
        ])->assertNotFound();
    }

    public function test_registrar_rechaza_instalacion_ya_reclamada_por_otra_empresa(): void
    {
        Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        Empresa::create(['nombre' => 'Otra', 'codigo' => 'otra', 'plan' => 'piloto', 'activo' => true]);

        $this->postJson('/agente/registrar', ['instalacion_id' => 'pos-1', 'cliente_codigo' => 'demo'])->assertOk();

        $this->postJson('/agente/registrar', ['instalacion_id' => 'pos-1', 'cliente_codigo' => 'otra'])
            ->assertConflict();
    }

    public function test_heartbeat_requiere_token_valido(): void
    {
        $this->postJson('/agente/heartbeat', [])->assertUnauthorized();
    }

    public function test_heartbeat_actualiza_estado_y_upserta_impresoras(): void
    {
        [$agente, $token] = $this->registrarAgente();

        $this->postJson('/agente/heartbeat', [
            'impresoras' => [
                'receipt' => ['online' => true, 'tipo' => 'red', 'ip' => '192.168.1.1', 'puerto' => 9100],
            ],
        ], ['Authorization' => "Bearer {$token}"])->assertOk();

        $agente->refresh();
        $this->assertSame('online', $agente->estado);
        $this->assertDatabaseHas('impresoras', [
            'agente_id' => $agente->id,
            'alias' => 'receipt',
            'estado_heartbeat' => 'online',
            'ip' => '192.168.1.1',
        ]);
    }

    public function test_heartbeat_registra_evento_agente_online_solo_en_la_transicion(): void
    {
        [$agente, $token] = $this->registrarAgente();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/agente/heartbeat', [], $headers)->assertOk();
        $this->postJson('/agente/heartbeat', [], $headers)->assertOk();

        $this->assertSame(1, Evento::where('agente_id', $agente->id)->where('tipo_evento', 'agente.online')->count());
    }

    public function test_eventos_encola_y_procesa_creando_trabajo_y_evento(): void
    {
        [$agente, $token] = $this->registrarAgente();

        $respuesta = $this->postJson('/agente/eventos', [
            'tipo_evento' => 'trabajo.impreso',
            'job_id_externo' => 'job-1',
            'target' => 'receipt',
            'duracion_ms' => 500,
        ], ['Authorization' => "Bearer {$token}"]);

        $respuesta->assertStatus(202);
        $this->assertDatabaseHas('trabajos_impresion', [
            'agente_id' => $agente->id, 'job_id_externo' => 'job-1', 'estado' => 'impreso', 'duracion_ms' => 500,
        ]);
        $this->assertDatabaseHas('eventos', ['agente_id' => $agente->id, 'tipo_evento' => 'trabajo.impreso']);
    }

    public function test_eventos_es_idempotente_ante_reintentos_del_agente(): void
    {
        [, $token] = $this->registrarAgente();
        $body = ['tipo_evento' => 'trabajo.impreso', 'job_id_externo' => 'job-dup', 'target' => 'receipt'];
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/agente/eventos', $body, $headers)->assertStatus(202);
        $this->postJson('/agente/eventos', $body, $headers)->assertStatus(202);

        $this->assertSame(1, Evento::where('tipo_evento', 'trabajo.impreso')->count());
    }

    /** @return array{0: Agente, 1: string} */
    private function registrarAgente(): array
    {
        Empresa::firstOrCreate(['codigo' => 'demo'], ['nombre' => 'Demo', 'plan' => 'piloto', 'activo' => true]);

        $respuesta = $this->postJson('/agente/registrar', [
            'instalacion_id' => 'pos-'.uniqid(),
            'cliente_codigo' => 'demo',
        ]);

        return [Agente::find($respuesta->json('agente_id')), $respuesta->json('token')];
    }
}

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

        $respuesta = $this->postJson('/agent/register', [
            'installation_id' => 'pos-test-1',
            'client_code' => 'demo',
            'display_name' => 'Caja 1',
            'agent_version' => '1.0.0',
        ]);

        $respuesta->assertOk()->assertJsonStructure(['agent_id', 'token']);
        $this->assertDatabaseHas('agentes', ['instalacion_id' => 'pos-test-1', 'empresa_id' => $empresa->id]);
    }

    public function test_registrar_rechaza_codigo_de_cliente_desconocido(): void
    {
        $this->postJson('/agent/register', [
            'installation_id' => 'pos-test-1',
            'client_code' => 'no-existe',
        ])->assertNotFound();
    }

    public function test_registrar_rechaza_instalacion_ya_reclamada_por_otra_empresa(): void
    {
        Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        Empresa::create(['nombre' => 'Otra', 'codigo' => 'otra', 'plan' => 'piloto', 'activo' => true]);

        $this->postJson('/agent/register', ['installation_id' => 'pos-1', 'client_code' => 'demo'])->assertOk();

        $this->postJson('/agent/register', ['installation_id' => 'pos-1', 'client_code' => 'otra'])
            ->assertConflict();
    }

    public function test_heartbeat_requiere_token_valido(): void
    {
        $this->postJson('/agent/heartbeat', [])->assertUnauthorized();
    }

    public function test_heartbeat_actualiza_estado_y_upserta_impresoras(): void
    {
        [$agente, $token] = $this->registrarAgente();

        $this->postJson('/agent/heartbeat', [
            'printers' => [
                'receipt' => ['online' => true, 'type' => 'red', 'ip' => '192.168.1.1', 'port' => 9100],
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

    public function test_heartbeat_borra_impresoras_que_ya_no_se_reportan(): void
    {
        [$agente, $token] = $this->registrarAgente();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/agent/heartbeat', [
            'printers' => [
                'receipt' => ['online' => true],
                'wristband' => ['online' => true],
            ],
        ], $headers)->assertOk();
        $this->assertSame(2, $agente->impresoras()->count());

        // El agente reporta su set completo en cada heartbeat -- si
        // "wristband" ya no aparece es porque se borro localmente (boton
        // "Eliminar" en la ventana del agente) y debe desaparecer aca
        // tambien, no quedar "online" para siempre.
        $this->postJson('/agent/heartbeat', [
            'printers' => [
                'receipt' => ['online' => true],
            ],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('impresoras', ['agente_id' => $agente->id, 'alias' => 'receipt']);
        $this->assertDatabaseMissing('impresoras', ['agente_id' => $agente->id, 'alias' => 'wristband']);
    }

    public function test_heartbeat_sin_printers_no_borra_las_ya_reportadas(): void
    {
        [$agente, $token] = $this->registrarAgente();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/agent/heartbeat', [
            'printers' => ['receipt' => ['online' => true]],
        ], $headers)->assertOk();

        // Un heartbeat que directamente omite "printers" no dice nada sobre
        // el estado actual de las impresoras -- no debe interpretarse como
        // "ya no hay ninguna".
        $this->postJson('/agent/heartbeat', [], $headers)->assertOk();

        $this->assertDatabaseHas('impresoras', ['agente_id' => $agente->id, 'alias' => 'receipt']);
    }

    public function test_heartbeat_registra_evento_agente_online_solo_en_la_transicion(): void
    {
        [$agente, $token] = $this->registrarAgente();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/agent/heartbeat', [], $headers)->assertOk();
        $this->postJson('/agent/heartbeat', [], $headers)->assertOk();

        $this->assertSame(1, Evento::where('agente_id', $agente->id)->where('tipo_evento', 'agent.online')->count());
    }

    public function test_eventos_encola_y_procesa_creando_trabajo_y_evento(): void
    {
        [$agente, $token] = $this->registrarAgente();

        $respuesta = $this->postJson('/agent/events', [
            'event_type' => 'job.printed',
            'external_job_id' => 'job-1',
            'target' => 'receipt',
            'duration_ms' => 500,
        ], ['Authorization' => "Bearer {$token}"]);

        $respuesta->assertStatus(202);
        $this->assertDatabaseHas('trabajos_impresion', [
            'agente_id' => $agente->id, 'job_id_externo' => 'job-1', 'estado' => 'impreso', 'duracion_ms' => 500,
        ]);
        $this->assertDatabaseHas('eventos', ['agente_id' => $agente->id, 'tipo_evento' => 'job.printed']);
    }

    public function test_eventos_es_idempotente_ante_reintentos_del_agente(): void
    {
        [, $token] = $this->registrarAgente();
        $body = ['event_type' => 'job.printed', 'external_job_id' => 'job-dup', 'target' => 'receipt'];
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/agent/events', $body, $headers)->assertStatus(202);
        $this->postJson('/agent/events', $body, $headers)->assertStatus(202);

        $this->assertSame(1, Evento::where('tipo_evento', 'job.printed')->count());
    }

    /** @return array{0: Agente, 1: string} */
    private function registrarAgente(): array
    {
        Empresa::firstOrCreate(['codigo' => 'demo'], ['nombre' => 'Demo', 'plan' => 'piloto', 'activo' => true]);

        $respuesta = $this->postJson('/agent/register', [
            'installation_id' => 'pos-'.uniqid(),
            'client_code' => 'demo',
        ]);

        return [Agente::find($respuesta->json('agent_id')), $respuesta->json('token')];
    }
}

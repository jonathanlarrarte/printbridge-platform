<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\ComandoPrueba;
use App\Models\Empresa;
use App\Models\Impresora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrabajoRemotoTest extends TestCase
{
    use RefreshDatabase;

    private function empresaConAgenteYImpresora(string $codigo = 'demo'): array
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => $codigo, 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => "pos-{$codigo}", 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $impresora = Impresora::create(['agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'red', 'protocolo' => 'escpos', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        return [$empresa, $agente, $impresora];
    }

    public function test_envia_un_trabajo_html_y_queda_pendiente_para_el_agente(): void
    {
        [$empresa, $agente, $impresora] = $this->empresaConAgenteYImpresora();
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];

        $respuesta = $this->postJson('/v1/agents/print', [
            'agent_id' => $agente->id,
            'target' => 'receipt',
            'format' => 'html',
            'data' => '<h1>Recibo</h1>',
        ], $headers);

        $respuesta->assertStatus(202)->assertJsonStructure(['data' => ['external_job_id', 'message']]);
        $this->assertDatabaseHas('comandos_prueba', [
            'agente_id' => $agente->id,
            'impresora_id' => $impresora->id,
            'target' => 'receipt',
            'format' => 'html',
            'estado' => 'pendiente',
        ]);
    }

    public function test_acepta_un_external_job_id_propio_y_lo_devuelve_igual(): void
    {
        [$empresa, $agente] = $this->empresaConAgenteYImpresora();
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];

        $respuesta = $this->postJson('/v1/agents/print', [
            'agent_id' => $agente->id,
            'target' => 'receipt',
            'format' => 'raw',
            'data' => base64_encode('ESC/POS crudo'),
            'external_job_id' => 'orden-1001',
        ], $headers);

        $respuesta->assertStatus(202)->assertJsonPath('data.external_job_id', 'orden-1001');
        $this->assertDatabaseHas('comandos_prueba', ['agente_id' => $agente->id, 'job_id_externo' => 'orden-1001', 'format' => 'raw']);
    }

    public function test_reenviar_el_mismo_external_job_id_no_duplica_el_trabajo(): void
    {
        [$empresa, $agente] = $this->empresaConAgenteYImpresora();
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];
        $cuerpo = ['agent_id' => $agente->id, 'target' => 'receipt', 'format' => 'html', 'data' => '<p>x</p>', 'external_job_id' => 'orden-dup'];

        $this->postJson('/v1/agents/print', $cuerpo, $headers)->assertStatus(202);

        // Reintento tipico de red (el cliente no recibio la respuesta a tiempo
        // y reenvia el mismo request): no debe crear un segundo comando, o el
        // agente imprimiria el mismo recibo dos veces.
        $this->postJson('/v1/agents/print', $cuerpo, $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.external_job_id', 'orden-dup');

        $this->assertSame(1, ComandoPrueba::where('job_id_externo', 'orden-dup')->count());
    }

    public function test_el_mismo_external_job_id_en_otro_agente_no_choca(): void
    {
        // La unicidad es por agente, no global: dos empresas (o dos agentes
        // de la misma empresa) eligiendo el mismo external_job_id por
        // coincidencia no deben pisarse entre si. Se prueba directo contra
        // el modelo (no por HTTP) porque hacer dos requests autenticados
        // como empresas distintas en el mismo test de PHPUnit comparte la
        // misma instancia de la app -- el guard de Sanctum cachea el
        // usuario resuelto en el primer request y no vuelve a resolverlo en
        // el segundo, lo cual no refleja como funciona esto en produccion
        // (un proceso PHP nuevo por request, sin ese cacheo).
        [, $agenteA, $impresoraA] = $this->empresaConAgenteYImpresora('a');
        [, $agenteB, $impresoraB] = $this->empresaConAgenteYImpresora('b');

        foreach ([[$agenteA, $impresoraA], [$agenteB, $impresoraB]] as [$agente, $impresora]) {
            ComandoPrueba::create([
                'agente_id' => $agente->id,
                'impresora_id' => $impresora->id,
                'job_id_externo' => 'order-1',
                'target' => $impresora->alias,
                'format' => 'html',
                'data' => ['html' => '<p>x</p>'],
                'estado' => 'pendiente',
                'creado_en' => now(),
            ]);
        }

        $this->assertSame(2, ComandoPrueba::where('job_id_externo', 'order-1')->count());
    }

    public function test_no_se_puede_enviar_un_trabajo_a_un_agente_de_otra_empresa(): void
    {
        [, $agenteB] = $this->empresaConAgenteYImpresora('b');
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresaA->createToken('t', ['tenant'])->plainTextToken];

        $this->postJson('/v1/agents/print', [
            'agent_id' => $agenteB->id, 'target' => 'receipt', 'format' => 'html', 'data' => '<p>x</p>',
        ], $headers)->assertNotFound();
    }

    public function test_rechaza_un_target_que_no_existe_en_el_agente(): void
    {
        [$empresa, $agente] = $this->empresaConAgenteYImpresora();
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];

        $this->postJson('/v1/agents/print', [
            'agent_id' => $agente->id, 'target' => 'impresora-inexistente', 'format' => 'html', 'data' => '<p>x</p>',
        ], $headers)->assertNotFound();
    }

    public function test_rechaza_un_formato_no_soportado(): void
    {
        [$empresa, $agente] = $this->empresaConAgenteYImpresora();
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];

        $this->postJson('/v1/agents/print', [
            'agent_id' => $agente->id, 'target' => 'receipt', 'format' => 'pdf', 'data' => 'x',
        ], $headers)->assertStatus(422);
    }
}

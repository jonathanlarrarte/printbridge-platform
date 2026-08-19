<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PruebaImpresionController;
use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruebaImpresionTest extends TestCase
{
    use RefreshDatabase;

    public function test_encola_una_prueba_escpos_para_una_impresora(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $impresora = Impresora::create(['agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'red', 'protocolo' => 'escpos', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];

        $respuesta = $this->postJson("/v1/agentes/{$agente->id}/impresoras/{$impresora->id}/prueba", [], $headers);

        $respuesta->assertStatus(202)->assertJsonStructure(['data' => ['job_id_externo', 'mensaje']]);
        $this->assertDatabaseHas('comandos_prueba', [
            'agente_id' => $agente->id, 'impresora_id' => $impresora->id, 'target' => 'receipt', 'format' => 'escpos', 'estado' => 'pendiente',
        ]);
    }

    public function test_usa_formato_tspl_si_la_impresora_esta_configurada_asi(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $impresora = Impresora::create(['agente_id' => $agente->id, 'alias' => 'wristband', 'tipo' => 'usb', 'protocolo' => 'tspl', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];
        $this->postJson("/v1/agentes/{$agente->id}/impresoras/{$impresora->id}/prueba", [], $headers)->assertStatus(202);

        $this->assertDatabaseHas('comandos_prueba', ['impresora_id' => $impresora->id, 'format' => 'tspl']);
    }

    public function test_no_se_puede_pedir_prueba_de_una_impresora_de_otra_empresa(): void
    {
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $agenteB = Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $impresoraB = Impresora::create(['agente_id' => $agenteB->id, 'alias' => 'receipt', 'tipo' => 'red', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        $headers = ['Authorization' => 'Bearer '.$empresaA->createToken('t', ['tenant'])->plainTextToken];

        $this->postJson("/v1/agentes/{$agenteB->id}/impresoras/{$impresoraB->id}/prueba", [], $headers)->assertNotFound();
    }

    public function test_el_heartbeat_entrega_el_comando_pendiente_y_lo_marca_entregado(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'token_hash' => hash('sha256', 'plaintoken'), 'estado' => 'online', 'creado_en' => now()]);
        $impresora = Impresora::create(['agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'red', 'protocolo' => 'escpos', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        $comando = PruebaImpresionController::crearComando($agente, $impresora);

        $respuesta = $this->postJson('/agente/heartbeat', [], ['Authorization' => 'Bearer plaintoken']);

        $respuesta->assertOk();
        $this->assertCount(1, $respuesta->json('comandos_pendientes'));
        $this->assertSame($comando->job_id_externo, $respuesta->json('comandos_pendientes.0.id'));
        $this->assertDatabaseHas('comandos_prueba', ['id' => $comando->id, 'estado' => 'entregado']);

        // Un segundo heartbeat no lo debe volver a entregar.
        $segunda = $this->postJson('/agente/heartbeat', [], ['Authorization' => 'Bearer plaintoken']);
        $this->assertCount(0, $segunda->json('comandos_pendientes'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EstadisticaTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumen_da_404_antes_de_calcular(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];

        $this->getJson('/v1/stats/summary', $headers)
            ->assertStatus(404)
            ->assertJsonPath('error', 'stats have not been calculated yet');
    }

    public function test_stats_de_agente_da_404_antes_de_calcular(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];

        $this->getJson("/v1/stats/agents/{$agente->id}", $headers)
            ->assertStatus(404)
            ->assertJsonPath('error', 'stats have not been calculated yet for this agent');
    }

    public function test_resumen_refleja_trabajos_reales_tras_calcular(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'estado' => 'online', 'token_hash' => 'x', 'creado_en' => now()]);
        $impresora = Impresora::create(['agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'red', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        // 2 impresos (uno con duracion) + 1 fallo -- numeros elegidos para
        // que la tasa de exito (2/3) no sea un valor "redondo" por
        // casualidad, y asi detectar si se mezclan impreso/fallo_definitivo.
        TrabajoImpresion::create(['agente_id' => $agente->id, 'impresora_id' => $impresora->id, 'job_id_externo' => 'j1', 'target' => 'receipt', 'estado' => 'impreso', 'duracion_ms' => 400]);
        TrabajoImpresion::create(['agente_id' => $agente->id, 'impresora_id' => $impresora->id, 'job_id_externo' => 'j2', 'target' => 'receipt', 'estado' => 'impreso', 'duracion_ms' => 600]);
        TrabajoImpresion::create(['agente_id' => $agente->id, 'impresora_id' => $impresora->id, 'job_id_externo' => 'j3', 'target' => 'receipt', 'estado' => 'fallo_definitivo', 'error_mensaje' => 'Timeout']);

        Artisan::call('app:calcular-estadisticas');

        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];
        $respuesta = $this->getJson('/v1/stats/summary', $headers)->assertOk()->json();

        $porImpresora = collect($respuesta['success_rate_by_printer'])->firstWhere('printer_id', $impresora->id);
        $this->assertSame('receipt', $porImpresora['alias']);
        $this->assertSame(2, $porImpresora['printed']);
        $this->assertSame(1, $porImpresora['failed']);
        $this->assertSame(0.6667, $porImpresora['success_rate']);

        // assertEquals (no assertSame): round(500, 1) da el float 500.0,
        // pero json_encode lo serializa como "500" sin fraccion -- al
        // decodificar vuelve como int. Lo que importa es el valor, no el
        // tipo que sobrevive al viaje por JSON.
        $this->assertEquals(500.0, $respuesta['average_print_time_ms']);

        $this->assertEquals([['error_message' => 'Timeout', 'count' => 1]], $respuesta['error_distribution']);

        $uptimeAgente = collect($respuesta['uptime_by_agent'])->firstWhere('agent_id', $agente->id);
        // Sin eventos agent.online/agent.offline en la ventana, se usa el
        // estado actual del agente como aproximacion (ver calcularUptime).
        $this->assertEquals(100.0, $uptimeAgente['uptime_24h']);
    }

    public function test_stats_de_agente_solo_incluye_ese_agente(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agenteA = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-a', 'estado' => 'online', 'token_hash' => 'x', 'creado_en' => now()]);
        $agenteB = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-b', 'estado' => 'online', 'token_hash' => 'y', 'creado_en' => now()]);
        $impresoraA = Impresora::create(['agente_id' => $agenteA->id, 'alias' => 'receipt', 'tipo' => 'red', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);
        $impresoraB = Impresora::create(['agente_id' => $agenteB->id, 'alias' => 'receipt', 'tipo' => 'red', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);

        TrabajoImpresion::create(['agente_id' => $agenteA->id, 'impresora_id' => $impresoraA->id, 'job_id_externo' => 'a1', 'target' => 'receipt', 'estado' => 'impreso']);
        TrabajoImpresion::create(['agente_id' => $agenteB->id, 'impresora_id' => $impresoraB->id, 'job_id_externo' => 'b1', 'target' => 'receipt', 'estado' => 'fallo_definitivo']);

        Artisan::call('app:calcular-estadisticas');

        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];
        $respuesta = $this->getJson("/v1/stats/agents/{$agenteA->id}", $headers)->assertOk()->json();

        $this->assertCount(1, $respuesta['uptime_by_agent']);
        $this->assertSame($agenteA->id, $respuesta['uptime_by_agent'][0]['agent_id']);

        $porImpresora = collect($respuesta['success_rate_by_printer'])->firstWhere('printer_id', $impresoraA->id);
        $this->assertSame(1, $porImpresora['printed']);
        $this->assertSame(0, $porImpresora['failed']);
        $this->assertNull(collect($respuesta['success_rate_by_printer'])->firstWhere('printer_id', $impresoraB->id));
    }

    public function test_no_se_pueden_ver_las_estadisticas_de_otra_empresa(): void
    {
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $agenteB = Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'estado' => 'online', 'token_hash' => 'x', 'creado_en' => now()]);
        Impresora::create(['agente_id' => $agenteB->id, 'alias' => 'receipt', 'tipo' => 'red', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);
        TrabajoImpresion::create(['agente_id' => $agenteB->id, 'job_id_externo' => 'b1', 'target' => 'receipt', 'estado' => 'impreso']);

        Artisan::call('app:calcular-estadisticas');

        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $tokenA = $empresaA->createToken('t')->plainTextToken;

        // Empresa A no tiene snapshot propio todavia -- tiene que dar 404,
        // nunca el snapshot de B.
        $this->getJson('/v1/stats/summary', ['Authorization' => "Bearer {$tokenA}"])->assertStatus(404);
        $this->getJson("/v1/stats/agents/{$agenteB->id}", ['Authorization' => "Bearer {$tokenA}"])->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectarAgentesOfflineTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_offline_al_agente_y_a_sus_impresoras(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create([
            'empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'token_hash' => 'x',
            'estado' => 'online', 'ultimo_heartbeat' => now()->subMinutes(5), 'creado_en' => now(),
        ]);
        $impresora = Impresora::create([
            'agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'usb',
            'estado_heartbeat' => 'online', 'actualizado_en' => now()->subMinutes(5),
        ]);

        $this->artisan('app:detectar-agentes-offline');

        // Sin esto una impresora se queda pegada en "online" para siempre:
        // ese estado es solo el ultimo heartbeat, y si el agente ya no
        // reporta no hay forma de saber el estado real -- offline es la
        // unica respuesta honesta.
        $this->assertSame('offline', $agente->fresh()->estado);
        $this->assertSame('offline', $impresora->fresh()->estado_heartbeat);
    }

    public function test_no_toca_agentes_con_heartbeat_reciente(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create([
            'empresa_id' => $empresa->id, 'instalacion_id' => 'pos-1', 'token_hash' => 'x',
            'estado' => 'online', 'ultimo_heartbeat' => now(), 'creado_en' => now(),
        ]);
        $impresora = Impresora::create([
            'agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'usb',
            'estado_heartbeat' => 'online', 'actualizado_en' => now(),
        ]);

        $this->artisan('app:detectar-agentes-offline');

        $this->assertSame('online', $agente->fresh()->estado);
        $this->assertSame('online', $impresora->fresh()->estado_heartbeat);
    }
}

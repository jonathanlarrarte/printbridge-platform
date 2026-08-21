<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Multi-tenancy (seccion 10 del doc): agentes/eventos se filtran por el
 * global scope BelongsToTenant; impresoras/trabajos_impresion (sin
 * empresa_id propio) se filtran atravesando la relacion con Agente.
 */
class ApiPublicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_raiz_es_publica_y_apunta_a_la_documentacion(): void
    {
        $this->getJson('/v1')
            ->assertOk()
            ->assertJsonStructure(['documentation', 'integration_guide', 'sign_up', 'authentication']);
    }

    public function test_v1_requiere_autenticacion(): void
    {
        $this->getJson('/v1/agents')->assertUnauthorized();
    }

    public function test_una_empresa_no_ve_agentes_de_otra(): void
    {
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);

        Agente::create(['empresa_id' => $empresaA->id, 'instalacion_id' => 'pos-a', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $agenteB = Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'y', 'estado' => 'online', 'creado_en' => now()]);

        $tokenA = $empresaA->createToken('t')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$tokenA}"];

        $respuesta = $this->getJson('/v1/agents', $headers);
        $respuesta->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('pos-a', $respuesta->json('data.0.installation_id'));

        $this->getJson("/v1/agents/{$agenteB->id}", $headers)->assertNotFound();
    }

    public function test_trabajos_se_escopan_por_empresa_via_relacion_agente(): void
    {
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $agenteA = Agente::create(['empresa_id' => $empresaA->id, 'instalacion_id' => 'pos-a', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $agenteB = Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'y', 'estado' => 'online', 'creado_en' => now()]);

        TrabajoImpresion::create(['agente_id' => $agenteA->id, 'job_id_externo' => 'j1', 'target' => 'receipt', 'estado' => 'impreso']);
        TrabajoImpresion::create(['agente_id' => $agenteB->id, 'job_id_externo' => 'j2', 'target' => 'receipt', 'estado' => 'impreso']);

        $tokenA = $empresaA->createToken('t')->plainTextToken;

        $this->getJson('/v1/jobs', ['Authorization' => "Bearer {$tokenA}"])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_job_id', 'j1');
    }

    public function test_trabajos_incluyen_nombre_de_agente_y_marca_de_prueba(): void
    {
        $empresa = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $agenteConNombre = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-a', 'nombre_descriptivo' => 'Caja 3', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $agenteSinNombre = Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'y', 'estado' => 'online', 'creado_en' => now()]);

        TrabajoImpresion::create(['agente_id' => $agenteConNombre->id, 'job_id_externo' => 'orden-123', 'target' => 'receipt', 'estado' => 'impreso']);
        TrabajoImpresion::create(['agente_id' => $agenteSinNombre->id, 'job_id_externo' => 'test-print-'.Str::uuid(), 'target' => 'receipt', 'estado' => 'impreso']);

        $token = $empresa->createToken('t')->plainTextToken;
        $respuesta = $this->getJson('/v1/jobs', ['Authorization' => "Bearer {$token}"])->assertOk();

        // Nombre real si esta configurado, sino cae al installation_id.
        $conNombre = collect($respuesta->json('data'))->firstWhere('external_job_id', 'orden-123');
        $this->assertSame('Caja 3', $conNombre['agent_name']);
        $this->assertFalse($conNombre['is_test']);

        $sinNombre = collect($respuesta->json('data'))->first(fn ($t) => str_starts_with($t['external_job_id'], 'test-print-'));
        $this->assertSame('pos-b', $sinNombre['agent_name']);
        $this->assertTrue($sinNombre['is_test']);
    }

    public function test_v1_agents_no_pagina_a_15_por_defecto(): void
    {
        // Regresion: sin un per_page explicito, paginate() por defecto de
        // Laravel corta en 15 -- una empresa con mas agentes que eso
        // (una cadena real, no un piloto) solo veia los primeros 15 en el
        // dashboard, sin ningun paginador en la UI para llegar al resto.
        $empresa = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        for ($i = 0; $i < 20; $i++) {
            Agente::create(['empresa_id' => $empresa->id, 'instalacion_id' => "pos-{$i}", 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        }

        $token = $empresa->createToken('t')->plainTextToken;

        $this->getJson('/v1/agents', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }

    public function test_borrar_agente_libera_su_installation_id_para_otra_empresa(): void
    {
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $agente = Agente::create(['empresa_id' => $empresaA->id, 'instalacion_id' => 'pos-mal-asociado', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        $impresora = Impresora::create(['agente_id' => $agente->id, 'alias' => 'receipt', 'tipo' => 'red', 'estado_heartbeat' => 'online', 'actualizado_en' => now()]);
        TrabajoImpresion::create(['agente_id' => $agente->id, 'impresora_id' => $impresora->id, 'job_id_externo' => 'j1', 'target' => 'receipt', 'estado' => 'impreso']);

        // Registrar el mismo installation_id contra la empresa equivocada
        // (B, mientras el agente sigue en A) tiene que rechazarse -- este
        // es exactamente el escenario que "eliminar" resuelve.
        $this->postJson('/agent/register', ['installation_id' => 'pos-mal-asociado', 'client_code' => 'b'])
            ->assertConflict();

        $tokenA = $empresaA->createToken('t')->plainTextToken;
        $this->deleteJson("/v1/agents/{$agente->id}", [], ['Authorization' => "Bearer {$tokenA}"])
            ->assertNoContent();

        $this->assertDatabaseMissing('agentes', ['id' => $agente->id]);
        $this->assertDatabaseMissing('impresoras', ['id' => $impresora->id]);
        $this->assertDatabaseMissing('trabajos_impresion', ['agente_id' => $agente->id]);

        // Ahora que el installation_id quedo libre, B si puede registrarlo.
        $this->postJson('/agent/register', ['installation_id' => 'pos-mal-asociado', 'client_code' => 'b'])
            ->assertOk();
    }

    public function test_no_se_puede_borrar_un_agente_de_otra_empresa(): void
    {
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $agenteB = Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'y', 'estado' => 'online', 'creado_en' => now()]);

        $tokenA = $empresaA->createToken('t')->plainTextToken;
        $this->deleteJson("/v1/agents/{$agenteB->id}", [], ['Authorization' => "Bearer {$tokenA}"])
            ->assertNotFound();

        $this->assertDatabaseHas('agentes', ['id' => $agenteB->id]);
    }

    public function test_estadisticas_agente_de_otra_empresa_da_404(): void
    {
        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => true]);
        $agenteB = Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'y', 'estado' => 'online', 'creado_en' => now()]);

        $tokenA = $empresaA->createToken('t')->plainTextToken;

        $this->getJson("/v1/stats/agents/{$agenteB->id}", ['Authorization' => "Bearer {$tokenA}"])
            ->assertNotFound();
    }
}

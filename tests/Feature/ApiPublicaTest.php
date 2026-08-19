<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\TrabajoImpresion;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

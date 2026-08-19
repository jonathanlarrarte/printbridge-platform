<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\TrabajoImpresion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResumenAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumen_agrega_datos_de_todas_las_empresas(): void
    {
        $plataforma = Empresa::create(['nombre' => 'Plataforma', 'codigo' => 'plataforma', 'plan' => 'interno', 'activo' => true]);
        $admin = Usuario::create(['empresa_id' => $plataforma->id, 'nombre' => 'Admin', 'email' => 'a@a.com', 'rol' => 'admin', 'password' => Hash::make('clave12345'), 'es_super_admin' => true]);
        $token = $this->postJson('/login', ['email' => $admin->email, 'password' => 'clave12345'])->json('token');

        $empresaA = Empresa::create(['nombre' => 'A', 'codigo' => 'a', 'plan' => 'piloto', 'activo' => true]);
        $empresaB = Empresa::create(['nombre' => 'B', 'codigo' => 'b', 'plan' => 'piloto', 'activo' => false]);
        $agenteA = Agente::create(['empresa_id' => $empresaA->id, 'instalacion_id' => 'pos-a', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);
        Agente::create(['empresa_id' => $empresaB->id, 'instalacion_id' => 'pos-b', 'token_hash' => 'y', 'estado' => 'offline', 'creado_en' => now()]);

        TrabajoImpresion::create(['agente_id' => $agenteA->id, 'job_id_externo' => 'j1', 'target' => 'receipt', 'estado' => 'impreso']);
        TrabajoImpresion::create(['agente_id' => $agenteA->id, 'job_id_externo' => 'j2', 'target' => 'receipt', 'estado' => 'fallo_definitivo']);

        $respuesta = $this->getJson('/v1/admin/resumen', ['Authorization' => "Bearer {$token}"]);

        $respuesta->assertOk();
        $this->assertSame(3, $respuesta->json('data.empresas.total')); // plataforma + a + b
        $this->assertSame(2, $respuesta->json('data.empresas.activas'));
        $this->assertSame(1, $respuesta->json('data.empresas.pendientes'));
        $this->assertSame(2, $respuesta->json('data.agentes.total'));
        $this->assertSame(1, $respuesta->json('data.agentes.online'));
        $this->assertSame(1, $respuesta->json('data.trabajos_24h.impresos'));
        $this->assertSame(1, $respuesta->json('data.trabajos_24h.fallidos'));

        // Regresion: withCount('agentes') sin desactivar el global scope de
        // Agente contaba solo los agentes de la PROPIA empresa del super
        // admin autenticado (Plataforma, 0 agentes), no los de "A" (1).
        $topA = collect($respuesta->json('data.top_empresas'))->firstWhere('codigo', 'a');
        $this->assertSame(1, $topA['agentes_count']);
    }

    public function test_resumen_requiere_super_admin(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t', ['tenant'])->plainTextToken];

        $this->getJson('/v1/admin/resumen', $headers)->assertStatus(403);
    }
}

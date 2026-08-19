<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function tokenSuperAdmin(): string
    {
        $empresa = Empresa::create(['nombre' => 'Plataforma', 'codigo' => 'plataforma', 'plan' => 'interno', 'activo' => true]);
        $usuario = Usuario::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Admin', 'email' => 'admin@plataforma.test',
            'rol' => 'admin', 'password' => Hash::make('clave12345'), 'es_super_admin' => true,
        ]);

        return $this->postJson('/login', ['email' => $usuario->email, 'password' => 'clave12345'])->json('token');
    }

    private function tokenUsuarioNormal(): string
    {
        $empresa = Empresa::create(['nombre' => 'Comun', 'codigo' => 'comun', 'plan' => 'piloto', 'activo' => true]);
        $usuario = Usuario::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Nadie Especial', 'email' => 'nadie@comun.test',
            'rol' => 'admin', 'password' => Hash::make('clave12345'), 'es_super_admin' => false,
        ]);

        return $this->postJson('/login', ['email' => $usuario->email, 'password' => 'clave12345'])->json('token');
    }

    public function test_login_de_super_admin_marca_es_super_admin_en_la_respuesta(): void
    {
        $empresa = Empresa::create(['nombre' => 'Plataforma', 'codigo' => 'plataforma', 'plan' => 'interno', 'activo' => true]);
        Usuario::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Admin', 'email' => 'admin@plataforma.test',
            'rol' => 'admin', 'password' => Hash::make('clave12345'), 'es_super_admin' => true,
        ]);

        $this->postJson('/login', ['email' => 'admin@plataforma.test', 'password' => 'clave12345'])
            ->assertOk()
            ->assertJsonPath('usuario.es_super_admin', true);
    }

    public function test_un_usuario_normal_no_puede_entrar_al_panel_admin(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenUsuarioNormal()];

        $this->getJson('/v1/admin/empresas', $headers)->assertStatus(403);
    }

    public function test_sin_token_no_se_puede_entrar_al_panel_admin(): void
    {
        $this->getJson('/v1/admin/empresas')->assertUnauthorized();
    }

    public function test_super_admin_ve_todas_las_empresas_no_solo_la_propia(): void
    {
        $tokenAdmin = $this->tokenSuperAdmin();
        $clienteUno = Empresa::create(['nombre' => 'Cliente Uno', 'codigo' => 'cliente-uno', 'plan' => 'piloto', 'activo' => true]);
        Empresa::create(['nombre' => 'Cliente Dos', 'codigo' => 'cliente-dos', 'plan' => 'piloto', 'activo' => false]);
        Agente::create(['empresa_id' => $clienteUno->id, 'instalacion_id' => 'pos-1', 'token_hash' => 'x', 'estado' => 'online', 'creado_en' => now()]);

        $respuesta = $this->getJson('/v1/admin/empresas', ['Authorization' => "Bearer {$tokenAdmin}"]);

        $respuesta->assertOk();
        // La propia empresa del super admin + las 2 creadas.
        $this->assertGreaterThanOrEqual(3, count($respuesta->json('data')));

        // Regresion: withCount('agentes') sin withoutGlobalScopes() en el
        // subquery contaba solo los agentes de la empresa del admin
        // autenticado (0), no los de "Cliente Uno" (1).
        $clienteUnoListado = collect($respuesta->json('data'))->firstWhere('codigo', 'cliente-uno');
        $this->assertSame(1, $clienteUnoListado['agentes_count']);
    }

    public function test_super_admin_crea_una_empresa_ya_activa(): void
    {
        $tokenAdmin = $this->tokenSuperAdmin();

        $crear = $this->postJson('/v1/admin/empresas', ['nombre' => 'Alta Manual'], ['Authorization' => "Bearer {$tokenAdmin}"]);

        $crear->assertCreated()->assertJsonPath('data.activo', true);
        $this->assertDatabaseHas('empresas', ['nombre' => 'Alta Manual', 'activo' => true]);
    }

    public function test_super_admin_activa_una_empresa_pendiente(): void
    {
        $tokenAdmin = $this->tokenSuperAdmin();
        $empresa = Empresa::create(['nombre' => 'Pendiente', 'codigo' => 'pendiente', 'plan' => 'piloto', 'activo' => false]);

        $this->patchJson("/v1/admin/empresas/{$empresa->id}", ['activo' => true], ['Authorization' => "Bearer {$tokenAdmin}"])
            ->assertOk()
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'activo' => true]);
    }

    public function test_super_admin_ve_el_detalle_con_agentes_e_impresoras_de_cualquier_empresa(): void
    {
        $tokenAdmin = $this->tokenSuperAdmin();
        $empresa = Empresa::create(['nombre' => 'Con Agente', 'codigo' => 'con-agente', 'plan' => 'piloto', 'activo' => true]);
        Agente::create([
            'empresa_id' => $empresa->id, 'instalacion_id' => 'pos-x', 'token_hash' => 'x',
            'estado' => 'online', 'creado_en' => now(),
        ]);

        $respuesta = $this->getJson("/v1/admin/empresas/{$empresa->id}", ['Authorization' => "Bearer {$tokenAdmin}"]);

        $respuesta->assertOk()
            ->assertJsonPath('data.empresa.codigo', 'con-agente')
            ->assertJsonCount(1, 'data.agentes');
    }

    public function test_super_admin_genera_un_api_key_en_nombre_de_otra_empresa(): void
    {
        $tokenAdmin = $this->tokenSuperAdmin();
        $empresa = Empresa::create(['nombre' => 'Cliente', 'codigo' => 'cliente', 'plan' => 'piloto', 'activo' => true]);

        $crear = $this->postJson("/v1/admin/empresas/{$empresa->id}/api-keys", ['nombre' => 'generada-por-admin'], ['Authorization' => "Bearer {$tokenAdmin}"]);
        $crear->assertCreated()->assertJsonStructure(['data' => ['id', 'nombre'], 'token']);

        // El token generado debe servir para autenticarse como ESA empresa.
        // auth()->forgetGuards(): Illuminate\Auth\RequestGuard cachea el
        // usuario resuelto por instancia de contenedor (comentario propio
        // de RequestGuard::user(): "we do not want to fetch the user data
        // on every call"), y en tests varias llamadas dentro del mismo
        // metodo comparten esa instancia -- sin esto, esta segunda llamada
        // devolveria el actor del request anterior, no el del token nuevo.
        auth()->forgetGuards();
        $tokenGenerado = $crear->json('token');
        $this->getJson('/v1/empresa', ['Authorization' => "Bearer {$tokenGenerado}"])
            ->assertOk()
            ->assertJsonPath('data.codigo', 'cliente');
    }

    public function test_empresa_desactivada_pierde_acceso_a_la_api_aunque_el_token_ya_exista(): void
    {
        $empresa = Empresa::create(['nombre' => 'Se Desactiva', 'codigo' => 'se-desactiva', 'plan' => 'piloto', 'activo' => true]);
        $token = $empresa->createToken('t')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/v1/agentes', $headers)->assertOk();

        $empresa->update(['activo' => false]);

        // Mismo motivo que arriba: sin esto, RequestGuard devolveria el
        // objeto Empresa ya resuelto (con activo=true desde memoria), no
        // el valor actualizado en la base.
        auth()->forgetGuards();
        $this->getJson('/v1/agentes', $headers)->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupYApiKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_crea_empresa_inactiva_pendiente_de_aprobacion(): void
    {
        $respuesta = $this->postJson('/signup', [
            'nombre_empresa' => 'Café Central',
            'nombre_usuario' => 'Julieta',
            'email' => 'julieta@cafecentral.test',
            'password' => 'clave12345',
        ]);

        $respuesta->assertCreated()->assertJsonStructure(['mensaje', 'empresa' => ['id', 'nombre', 'codigo']]);
        $respuesta->assertJsonMissing(['token']);
        $this->assertSame('cafe-central', $respuesta->json('empresa.codigo'));
        $this->assertDatabaseHas('usuarios', ['email' => 'julieta@cafecentral.test', 'rol' => 'admin']);
        $this->assertDatabaseHas('empresas', ['codigo' => 'cafe-central', 'activo' => false]);
    }

    public function test_no_se_puede_loguear_hasta_que_un_admin_active_la_empresa(): void
    {
        $this->postJson('/signup', [
            'nombre_empresa' => 'Café Central', 'nombre_usuario' => 'Julieta',
            'email' => 'julieta@cafecentral.test', 'password' => 'clave12345',
        ]);

        $this->postJson('/login', ['email' => 'julieta@cafecentral.test', 'password' => 'clave12345'])
            ->assertStatus(403);

        $empresa = Empresa::where('codigo', 'cafe-central')->first();
        $empresa->update(['activo' => true]);

        $this->postJson('/login', ['email' => 'julieta@cafecentral.test', 'password' => 'clave12345'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_signup_genera_codigos_distintos_para_nombres_repetidos(): void
    {
        $datos = fn ($email) => [
            'nombre_empresa' => 'Café Central',
            'nombre_usuario' => 'Admin',
            'email' => $email,
            'password' => 'clave12345',
        ];

        $primero = $this->postJson('/signup', $datos('a@test.com'))->json('empresa.codigo');
        $segundo = $this->postJson('/signup', $datos('b@test.com'))->json('empresa.codigo');

        $this->assertNotSame($primero, $segundo);
    }

    public function test_signup_rechaza_email_ya_usado(): void
    {
        $datos = [
            'nombre_empresa' => 'Empresa A', 'nombre_usuario' => 'Admin',
            'email' => 'dup@test.com', 'password' => 'clave12345',
        ];
        $this->postJson('/signup', $datos)->assertCreated();
        $this->postJson('/signup', $datos)->assertStatus(422);
    }

    public function test_el_codigo_de_una_empresa_no_activada_no_deja_registrar_agentes(): void
    {
        $codigo = $this->postJson('/signup', [
            'nombre_empresa' => 'Empresa Agente', 'nombre_usuario' => 'Admin',
            'email' => 'x@test.com', 'password' => 'clave12345',
        ])->json('empresa.codigo');

        $this->postJson('/agente/registrar', [
            'instalacion_id' => 'pos-1', 'cliente_codigo' => $codigo,
        ])->assertStatus(403);
    }

    public function test_el_codigo_de_una_empresa_activada_si_deja_registrar_agentes(): void
    {
        $codigo = $this->postJson('/signup', [
            'nombre_empresa' => 'Empresa Agente 2', 'nombre_usuario' => 'Admin',
            'email' => 'y@test.com', 'password' => 'clave12345',
        ])->json('empresa.codigo');

        Empresa::where('codigo', $codigo)->first()->update(['activo' => true]);

        $this->postJson('/agente/registrar', [
            'instalacion_id' => 'pos-1', 'cliente_codigo' => $codigo,
        ])->assertOk();
    }

    public function test_api_keys_crud_completo(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('inicial')->plainTextToken];

        $this->getJson('/v1/api-keys', $headers)->assertOk()->assertJsonCount(1, 'data');

        $crear = $this->postJson('/v1/api-keys', ['nombre' => 'integracion'], $headers);
        $crear->assertCreated()->assertJsonStructure(['data' => ['id', 'nombre'], 'token']);

        $this->getJson('/v1/api-keys', $headers)->assertOk()->assertJsonCount(2, 'data');

        $id = $crear->json('data.id');
        $this->deleteJson("/v1/api-keys/{$id}", [], $headers)->assertNoContent();
        $this->getJson('/v1/api-keys', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_v1_empresa_devuelve_el_perfil_de_la_empresa_autenticada(): void
    {
        $empresa = Empresa::create(['nombre' => 'Demo', 'codigo' => 'demo', 'plan' => 'piloto', 'activo' => true]);
        $headers = ['Authorization' => 'Bearer '.$empresa->createToken('t')->plainTextToken];

        $this->getJson('/v1/empresa', $headers)
            ->assertOk()
            ->assertJsonPath('data.codigo', 'demo')
            ->assertJsonPath('data.nombre', 'Demo');
    }
}

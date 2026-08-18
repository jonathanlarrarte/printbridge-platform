<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea una empresa de prueba con codigo 'demo', un usuario admin, y un
 * token Sanctum para probar la API publica v1 con curl.
 */
class EmpresaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstOrCreate(
            ['codigo' => 'demo'],
            ['nombre' => 'Empresa Demo', 'plan' => 'piloto', 'activo' => true]
        );

        Usuario::firstOrCreate(
            ['email' => 'admin@demo.test'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Admin Demo', 'rol' => 'admin', 'password' => Hash::make('demo1234')]
        );

        $empresa->tokens()->where('name', 'cli-verificacion')->delete();
        $token = $empresa->createToken('cli-verificacion')->plainTextToken;

        $this->command->info("Empresa demo lista. codigo=demo, empresa_id={$empresa->id}");
        $this->command->info("Token API publica: {$token}");
    }
}

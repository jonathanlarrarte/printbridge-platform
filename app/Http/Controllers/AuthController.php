<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Login/signup del dashboard (seccion 2 del doc: el dashboard usa "el mismo
 * canal que un tercero", es decir, la API publica v1 con un token Sanctum).
 * Estos endpoints son lo unico que no pasa por ese canal: cambian
 * credenciales por un token de empresa para arrancar la sesion del SPA.
 */
class AuthController extends Controller
{
    /**
     * Alta de una empresa nueva (signup self-service del SaaS): crea la
     * empresa y su primer usuario (admin) atomicamente, y devuelve una
     * sesion ya arrancada -- igual forma que login().
     */
    public function signup(Request $request)
    {
        $datos = $request->validate([
            'nombre_empresa' => ['required', 'string', 'max:255'],
            'nombre_usuario' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $empresa = Empresa::create([
            'nombre' => $datos['nombre_empresa'],
            'codigo' => $this->generarCodigoUnico($datos['nombre_empresa']),
            'plan' => 'piloto',
            'activo' => true,
        ]);

        $usuario = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => $datos['nombre_usuario'],
            'email' => $datos['email'],
            'rol' => 'admin',
            'password' => Hash::make($datos['password']),
        ]);

        return response()->json([
            'token' => $empresa->createToken('dashboard:'.$usuario->id)->plainTextToken,
            'usuario' => ['id' => $usuario->id, 'nombre' => $usuario->nombre, 'email' => $usuario->email, 'rol' => $usuario->rol],
            'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo],
        ], 201);
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = Usuario::withoutGlobalScopes()->where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json(['error' => 'credenciales invalidas'], 401);
        }

        $empresa = $usuario->empresa;
        $token = $empresa->createToken('dashboard:'.$usuario->id)->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => ['id' => $usuario->id, 'nombre' => $usuario->nombre, 'email' => $usuario->email, 'rol' => $usuario->rol],
            'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * pos-test-abcd1234 -> "mi-empresa" -> "mi-empresa-x7q2" si ya existe.
     * Este codigo es el que el instalador del agente pide como
     * "codigo de cliente" para POST /agente/registrar (seccion 6.1).
     */
    private function generarCodigoUnico(string $nombreEmpresa): string
    {
        $base = Str::slug($nombreEmpresa) ?: 'empresa';
        $codigo = $base;
        $intentos = 0;

        while (Empresa::where('codigo', $codigo)->exists()) {
            $codigo = $base.'-'.Str::lower(Str::random(4));

            if (++$intentos > 20) {
                throw new \RuntimeException('No se pudo generar un codigo de empresa unico.');
            }
        }

        return $codigo;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Login/signup del dashboard (seccion 2 del doc: el dashboard usa "el mismo
 * canal que un tercero", es decir, la API publica v1 con un token Sanctum).
 * Estos endpoints son lo unico que no pasa por ese canal: cambian
 * credenciales por un token de empresa para arrancar la sesion del SPA.
 */
class AuthController extends Controller
{
    /**
     * Alta de una empresa nueva (signup self-service del SaaS). Queda
     * `activo = false` -- un super admin la tiene que activar antes de que
     * alguien pueda entrar (ver EmpresaAdminController@update). No devuelve
     * token: todavia no hay sesion que arrancar.
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
            'codigo' => Empresa::generarCodigoUnico($datos['nombre_empresa']),
            'plan' => 'piloto',
            'activo' => false,
        ]);

        Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => $datos['nombre_usuario'],
            'email' => $datos['email'],
            'rol' => 'admin',
            'password' => Hash::make($datos['password']),
        ]);

        return response()->json([
            'mensaje' => 'Cuenta creada. Un administrador de la plataforma tiene que activarla antes de que puedas ingresar.',
            'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo],
        ], 201);
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = Usuario::withoutGlobalScopes()->with('empresa')->where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json(['error' => 'credenciales invalidas'], 401);
        }

        if (! $usuario->empresa->activo) {
            return response()->json(['error' => 'Tu empresa todavia no fue activada por un administrador.'], 403);
        }

        $empresa = $usuario->empresa;
        // OJO: nunca usar '*' como ability -- Sanctum lo trata como
        // wildcard universal (PersonalAccessToken::can() le da un pase
        // libre a CUALQUIER ability, incluida 'super-admin'). 'tenant' es
        // la ability base de cualquier token de empresa; 'super-admin' se
        // suma aparte solo para el usuario que de verdad lo es.
        $abilidades = $usuario->es_super_admin ? ['tenant', 'super-admin'] : ['tenant'];
        $token = $empresa->createToken('dashboard:'.$usuario->id, $abilidades)->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
                'es_super_admin' => $usuario->es_super_admin,
            ],
            'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}

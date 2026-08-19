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
            'company_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $empresa = Empresa::create([
            'nombre' => $datos['company_name'],
            'codigo' => Empresa::generarCodigoUnico($datos['company_name']),
            'plan' => 'piloto',
            'activo' => false,
        ]);

        Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => $datos['user_name'],
            'email' => $datos['email'],
            'rol' => 'admin',
            'password' => Hash::make($datos['password']),
        ]);

        return response()->json([
            'message' => 'Account created. A platform administrator needs to activate it before you can sign in.',
            'company' => ['id' => $empresa->id, 'name' => $empresa->nombre, 'code' => $empresa->codigo],
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
            return response()->json(['error' => 'invalid credentials'], 401);
        }

        if (! $usuario->empresa->activo) {
            return response()->json(['error' => 'Your company has not been activated by an administrator yet.'], 403);
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
            'user' => [
                'id' => $usuario->id,
                'name' => $usuario->nombre,
                'email' => $usuario->email,
                'role' => $usuario->rol,
                'is_super_admin' => $usuario->es_super_admin,
            ],
            'company' => ['id' => $empresa->id, 'name' => $empresa->nombre, 'code' => $empresa->codigo],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}

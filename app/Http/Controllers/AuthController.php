<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Login del dashboard (seccion 2 del doc: el dashboard usa "el mismo canal
 * que un tercero", es decir, la API publica v1 con un token Sanctum). Este
 * endpoint es lo unico que no pasa por ese canal: cambia credenciales de
 * usuario por un token de empresa para arrancar la sesion del SPA.
 */
class AuthController extends Controller
{
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
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Gestion de las API keys de la empresa (seccion 5.9 y 6 del doc: "usando
 * Laravel Sanctum en modo token personal" -- estas keys SON los tokens de
 * Sanctum, no una tabla aparte, ver decision #2 del plan original). Cada
 * empresa puede tener varias, con nombre, para distinguir integraciones
 * (ej. "produccion", "test", "dashboard").
 */
class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $request->user();

        return response()->json([
            'data' => $empresa->tokens()->orderByDesc('id')->get()->map(fn ($t) => [
                'id' => $t->id,
                'nombre' => $t->name,
                'ultimo_uso' => $t->last_used_at,
                'creado_en' => $t->created_at,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $empresa = $request->user();
        // 'tenant', nunca '*' -- ver nota en AuthController@login.
        $nuevo = $empresa->createToken($datos['nombre'], ['tenant']);

        return response()->json([
            'data' => [
                'id' => $nuevo->accessToken->id,
                'nombre' => $nuevo->accessToken->name,
                'creado_en' => $nuevo->accessToken->created_at,
            ],
            // Unica vez que se devuelve en texto plano.
            'token' => $nuevo->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;

/**
 * El super admin genera/revoca API keys EN NOMBRE de cualquier empresa
 * (ej. para dar de alta una integracion vos mismo, sin depender de que el
 * cliente entre a su propio dashboard). Mismo mecanismo que
 * Api\ApiKeyController (son los mismos tokens Sanctum), solo que resuelto
 * por :empresaId en vez de por el usuario autenticado.
 */
class ApiKeyAdminController extends Controller
{
    public function store(Request $request, int $empresaId)
    {
        $datos = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $empresa = Empresa::withoutGlobalScopes()->findOrFail($empresaId);
        // 'tenant', nunca '*' -- un token generado por el admin para un
        // cliente es un token de tenant normal, no hereda 'super-admin'.
        $nuevo = $empresa->createToken($datos['name'], ['tenant']);

        return response()->json([
            'data' => ['id' => $nuevo->accessToken->id, 'name' => $nuevo->accessToken->name, 'created_at' => $nuevo->accessToken->created_at],
            'token' => $nuevo->plainTextToken,
        ], 201);
    }

    public function destroy(int $empresaId, int $id)
    {
        $empresa = Empresa::withoutGlobalScopes()->findOrFail($empresaId);
        $empresa->tokens()->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}

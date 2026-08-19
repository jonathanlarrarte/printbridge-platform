<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * Gestion de las API keys de la empresa (seccion 5.9 y 6 del doc: "usando
 * Laravel Sanctum en modo token personal" -- estas keys SON los tokens de
 * Sanctum, no una tabla aparte, ver decision #2 del plan original). Cada
 * empresa puede tener varias, con nombre, para distinguir integraciones
 * (ej. "produccion", "test", "dashboard").
 */
#[Group('API Keys')]
class ApiKeyController extends Controller
{
    /**
     * List API keys.
     */
    public function index(Request $request)
    {
        $empresa = $request->user();

        return response()->json([
            'data' => $empresa->tokens()->orderByDesc('id')->get()->map(fn ($t) => [
                'id' => $t->id,
                /** Label you gave this key when creating it (e.g. "production", "dashboard") -- purely for your own reference, not used for anything functionally. */
                'name' => $t->name,
                /** When this key last authenticated a request. Null if it's never been used. */
                'last_used_at' => $t->last_used_at,
                'created_at' => $t->created_at,
            ]),
        ]);
    }

    /**
     * Create an API key.
     *
     * The plaintext token is only ever returned in this response — it isn't
     * stored anywhere and can't be retrieved again later.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            /** A label to help you tell this key apart from others later (e.g. "production", "test"). */
            'name' => ['required', 'string', 'max:255'],
        ]);

        $empresa = $request->user();
        // 'tenant', nunca '*' -- ver nota en AuthController@login.
        $nuevo = $empresa->createToken($datos['name'], ['tenant']);

        return response()->json([
            'data' => [
                'id' => $nuevo->accessToken->id,
                'name' => $nuevo->accessToken->name,
                'created_at' => $nuevo->accessToken->created_at,
            ],
            /** The actual Bearer token -- shown here once, never again. This is what you put in `Authorization: Bearer <token>` for every `/v1/*` request. */
            'token' => $nuevo->plainTextToken,
        ], 201);
    }

    /**
     * Revoke an API key.
     */
    public function destroy(Request $request, int $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}

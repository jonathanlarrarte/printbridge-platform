<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los tokens de super admin son tokens de empresa normales (misma tabla,
 * mismo guard sanctum) con la ability extra 'super-admin' agregada en
 * AuthController@login solo cuando el usuario que se logueo tiene
 * usuarios.es_super_admin = true. No hace falta un tokenable ni un guard
 * aparte: alcanza con chequear esa ability en las rutas /v1/admin/*.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token || ! $token->can('super-admin')) {
            return response()->json(['error' => 'no autorizado'], 403);
        }

        return $next($request);
    }
}

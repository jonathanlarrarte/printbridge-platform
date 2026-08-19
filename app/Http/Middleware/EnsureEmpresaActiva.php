<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * empresas.activo se chequea en el login (AuthController), pero un token ya
 * emitido sigue siendo valido para Sanctum aunque el super admin desactive
 * la empresa despues -- este middleware es lo que realmente corta el acceso
 * a /v1/* en ese momento, no solo en el login.
 */
class EnsureEmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = $request->user();

        if ($empresa && ! $empresa->activo) {
            return response()->json(['error' => 'this company is deactivated'], 403);
        }

        return $next($request);
    }
}

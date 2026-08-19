<?php

namespace App\Http\Middleware;

use App\Models\Agente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica al agente Electron (no es un "usuario" de Laravel, no pasa por
 * Sanctum) contra el token propio de su instalación, generado en
 * POST /agente/registrar y guardado hasheado en agentes.token_hash.
 */
class AutenticarAgente
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'token de agente requerido'], 401);
        }

        $hash = hash('sha256', $token);

        $agente = Agente::withoutGlobalScopes()->with('empresa')->where('token_hash', $hash)->first();

        if (! $agente || ! hash_equals($agente->token_hash, $hash)) {
            return response()->json(['error' => 'token de agente invalido'], 401);
        }

        if (! $agente->empresa->activo) {
            return response()->json(['error' => 'la empresa de este agente esta desactivada'], 403);
        }

        $request->attributes->set('agente', $agente);

        return $next($request);
    }
}

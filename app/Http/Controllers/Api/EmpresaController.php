<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    /**
     * GET /v1/company — perfil de la empresa autenticada. El dashboard lo
     * usa sobre todo para mostrar el `code` que hace falta al instalar
     * un agente nuevo (seccion 6.1: POST /agent/register).
     */
    public function show(Request $request)
    {
        $empresa = $request->user();

        return response()->json(['data' => [
            'id' => $empresa->id,
            'name' => $empresa->nombre,
            'code' => $empresa->codigo,
            'plan' => $empresa->plan,
            'active' => $empresa->activo,
            'created_at' => $empresa->created_at,
        ]]);
    }
}

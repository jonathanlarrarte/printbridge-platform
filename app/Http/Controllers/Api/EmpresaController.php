<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    /**
     * GET /v1/empresa — perfil de la empresa autenticada. El dashboard lo
     * usa sobre todo para mostrar el `codigo` que hace falta al instalar
     * un agente nuevo (seccion 6.1: POST /agente/registrar).
     */
    public function show(Request $request)
    {
        $empresa = $request->user();

        return response()->json(['data' => [
            'id' => $empresa->id,
            'nombre' => $empresa->nombre,
            'codigo' => $empresa->codigo,
            'plan' => $empresa->plan,
            'activo' => $empresa->activo,
            'creado_en' => $empresa->created_at,
        ]]);
    }
}

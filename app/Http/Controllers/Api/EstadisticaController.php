<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstadisticaAgregada;

class EstadisticaController extends Controller
{
    /**
     * GET /v1/stats/summary — snapshot de toda la empresa
     * (agente_id null), pre-calculado por app:calcular-estadisticas.
     */
    public function resumen()
    {
        $estadistica = EstadisticaAgregada::whereNull('agente_id')->first();

        if (! $estadistica) {
            return response()->json(['error' => 'stats have not been calculated yet'], 404);
        }

        return response()->json([
            'calculated_at' => $estadistica->calculado_en,
            ...$estadistica->datos,
        ]);
    }

    /**
     * GET /v1/stats/agents/{id} — snapshot de un agente puntual.
     */
    public function agente(int $id)
    {
        $estadistica = EstadisticaAgregada::where('agente_id', $id)->first();

        if (! $estadistica) {
            return response()->json(['error' => 'stats have not been calculated yet for this agent'], 404);
        }

        return response()->json([
            'calculated_at' => $estadistica->calculado_en,
            ...$estadistica->datos,
        ]);
    }
}

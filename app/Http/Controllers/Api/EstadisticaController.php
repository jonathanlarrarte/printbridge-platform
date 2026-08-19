<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstadisticaAgregada;
use Dedoc\Scramble\Attributes\Group;

#[Group('Stats')]
class EstadisticaController extends Controller
{
    /**
     * Get company-wide stats.
     *
     * A pre-calculated snapshot covering every agent in your company,
     * refreshed periodically by a scheduled job.
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
     * Get stats for one agent.
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

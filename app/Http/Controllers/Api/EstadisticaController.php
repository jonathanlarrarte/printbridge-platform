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

        return response()->json($this->formatear($estadistica));
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

        return response()->json($this->formatear($estadistica));
    }

    /**
     * Los campos vienen tal cual se guardaron en `datos` (jsonb, ver
     * app:calcular-estadisticas) -- se listan uno por uno en vez de
     * spread(...$estadistica->datos) unicamente para que Scramble pueda
     * generar un schema real en vez de un objeto opaco.
     */
    private function formatear(EstadisticaAgregada $estadistica): array
    {
        $datos = $estadistica->datos;

        return [
            'calculated_at' => $estadistica->calculado_en,
            /** Success rate per printer, all-time (not a rolling window -- recalculated from the full history on every run). Each entry: `{ printer_id, alias, printed, failed, success_rate }`. */
            'success_rate_by_printer' => $datos['success_rate_by_printer'],
            /** Average time from "printing" to "printed" across every successful job, in milliseconds. Null if nothing has printed successfully yet. */
            'average_print_time_ms' => $datos['average_print_time_ms'],
            /** Uptime percentage per agent over 24h/7d/30d windows, reconstructed from `agent.online`/`agent.offline` events. Each entry: `{ agent_id, installation_id, uptime_24h, uptime_7d, uptime_30d }`. On `GET /v1/stats/agents/{id}` this array has exactly one entry. */
            'uptime_by_agent' => $datos['uptime_by_agent'],
            /** The most common failure messages, most frequent first. Each entry: `{ error_message, count }`. */
            'error_distribution' => $datos['error_distribution'],
            /** Job volume grouped by hour of day (0-23), all-time. Each entry: `{ hour, count }`. */
            'volume_by_hour' => $datos['volume_by_hour'],
            /** Job volume grouped by day of week (0 = Sunday ... 6 = Saturday). Each entry: `{ day, count }`. */
            'volume_by_day_of_week' => $datos['volume_by_day_of_week'],
        ];
    }
}

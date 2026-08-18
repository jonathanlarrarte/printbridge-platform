<?php

namespace App\Console\Commands;

use App\Models\Agente;
use App\Models\Empresa;
use App\Models\EstadisticaAgregada;
use App\Models\Evento;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Seccion 9 del doc: pre-calcula metricas cada 5-15 min y las deja en
 * estadisticas_agregadas, para que GET /v1/estadisticas/* nunca agregue en
 * tiempo real sobre la tabla cruda de eventos/trabajos.
 */
class CalcularEstadisticas extends Command
{
    protected $signature = 'app:calcular-estadisticas';

    protected $description = 'Recalcula y guarda las metricas agregadas por empresa y por agente';

    public function handle(): void
    {
        Empresa::withoutGlobalScopes()->each(function (Empresa $empresa) {
            $agentes = Agente::withoutGlobalScopes()->where('empresa_id', $empresa->id)->get();
            $agenteIds = $agentes->pluck('id')->all();

            $this->guardarSnapshot($empresa, null, $agenteIds, $agentes);

            foreach ($agentes as $agente) {
                $this->guardarSnapshot($empresa, $agente->id, [$agente->id], collect([$agente]));
            }
        });

        $this->info('Estadisticas recalculadas.');
    }

    private function guardarSnapshot(Empresa $empresa, ?int $agenteId, array $agenteIds, Collection $agentesParaUptime): void
    {
        $datos = [
            'tasa_exito_por_impresora' => $this->tasaExitoPorImpresora($agenteIds),
            'tiempo_promedio_impresion_ms' => $this->tiempoPromedioImpresionMs($agenteIds),
            'uptime_por_agente' => $agentesParaUptime->map(fn ($a) => $this->uptimeAgente($a))->all(),
            'distribucion_errores' => $this->distribucionErrores($agenteIds),
            'volumen_por_hora' => $this->volumenPorHora($agenteIds),
            'volumen_por_dia_semana' => $this->volumenPorDiaSemana($agenteIds),
        ];

        EstadisticaAgregada::withoutGlobalScopes()->updateOrCreate(
            ['empresa_id' => $empresa->id, 'agente_id' => $agenteId],
            ['datos' => $datos, 'calculado_en' => now()]
        );
    }

    private function tasaExitoPorImpresora(array $agenteIds): array
    {
        if (empty($agenteIds)) {
            return [];
        }

        $filas = TrabajoImpresion::whereIn('agente_id', $agenteIds)
            ->whereNotNull('impresora_id')
            ->whereIn('estado', ['impreso', 'fallo_definitivo'])
            ->selectRaw('impresora_id, estado, COUNT(*) as cantidad')
            ->groupBy('impresora_id', 'estado')
            ->get();

        $porImpresora = [];
        foreach ($filas as $fila) {
            $porImpresora[$fila->impresora_id][$fila->estado] = (int) $fila->cantidad;
        }

        $alias = Impresora::whereIn('id', array_keys($porImpresora))->pluck('alias', 'id');

        $resultado = [];
        foreach ($porImpresora as $impresoraId => $conteo) {
            $impresos = $conteo['impreso'] ?? 0;
            $fallos = $conteo['fallo_definitivo'] ?? 0;
            $total = $impresos + $fallos;

            $resultado[] = [
                'impresora_id' => $impresoraId,
                'alias' => $alias[$impresoraId] ?? null,
                'impresos' => $impresos,
                'fallos' => $fallos,
                'tasa_exito' => $total > 0 ? round($impresos / $total, 4) : null,
            ];
        }

        return $resultado;
    }

    private function tiempoPromedioImpresionMs(array $agenteIds): ?float
    {
        if (empty($agenteIds)) {
            return null;
        }

        $promedio = TrabajoImpresion::whereIn('agente_id', $agenteIds)
            ->where('estado', 'impreso')
            ->avg('duracion_ms');

        return is_null($promedio) ? null : round($promedio, 1);
    }

    private function distribucionErrores(array $agenteIds): array
    {
        if (empty($agenteIds)) {
            return [];
        }

        return TrabajoImpresion::whereIn('agente_id', $agenteIds)
            ->where('estado', 'fallo_definitivo')
            ->selectRaw("COALESCE(error_mensaje, '(sin mensaje)') as error_mensaje, COUNT(*) as cantidad")
            ->groupBy('error_mensaje')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($f) => ['error_mensaje' => $f->error_mensaje, 'cantidad' => (int) $f->cantidad])
            ->all();
    }

    private function volumenPorHora(array $agenteIds): array
    {
        if (empty($agenteIds)) {
            return [];
        }

        return TrabajoImpresion::whereIn('agente_id', $agenteIds)
            ->selectRaw('EXTRACT(HOUR FROM created_at)::int as hora, COUNT(*) as cantidad')
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->map(fn ($f) => ['hora' => $f->hora, 'cantidad' => (int) $f->cantidad])
            ->all();
    }

    private function volumenPorDiaSemana(array $agenteIds): array
    {
        if (empty($agenteIds)) {
            return [];
        }

        // Postgres EXTRACT(DOW ...): 0 = domingo ... 6 = sabado.
        return TrabajoImpresion::whereIn('agente_id', $agenteIds)
            ->selectRaw('EXTRACT(DOW FROM created_at)::int as dia, COUNT(*) as cantidad')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->map(fn ($f) => ['dia' => $f->dia, 'cantidad' => (int) $f->cantidad])
            ->all();
    }

    private function uptimeAgente(Agente $agente): array
    {
        $ahora = now();

        return [
            'agente_id' => $agente->id,
            'instalacion_id' => $agente->instalacion_id,
            'uptime_24h' => $this->calcularUptime($agente, $ahora->copy()->subDay(), $ahora),
            'uptime_7d' => $this->calcularUptime($agente, $ahora->copy()->subDays(7), $ahora),
            'uptime_30d' => $this->calcularUptime($agente, $ahora->copy()->subDays(30), $ahora),
        ];
    }

    /**
     * Reconstruye el timeline online/offline dentro de [desde, hasta] a
     * partir de los eventos agente.online/agente.offline (que solo se
     * registran en una transicion real, nunca en cada heartbeat) y suma
     * cuanto tiempo estuvo online. El estado justo antes del primer evento
     * de la ventana es, por definicion, el opuesto de ese evento.
     */
    private function calcularUptime(Agente $agente, Carbon $desde, Carbon $hasta): float
    {
        $eventos = Evento::withoutGlobalScopes()
            ->where('agente_id', $agente->id)
            ->whereIn('tipo_evento', ['agente.online', 'agente.offline'])
            ->whereBetween('creado_en', [$desde, $hasta])
            ->orderBy('creado_en')
            ->get();

        if ($eventos->isEmpty()) {
            return $agente->estado === 'online' ? 100.0 : 0.0;
        }

        $segundosOnline = 0;
        $cursor = $desde;
        $estadoActual = $eventos->first()->tipo_evento === 'agente.offline' ? 'online' : 'offline';

        foreach ($eventos as $evento) {
            if ($estadoActual === 'online') {
                // Carbon 3 devuelve el diff con signo por defecto; se fuerza
                // absoluto porque aca solo interesa la magnitud del tramo.
                $segundosOnline += $evento->creado_en->diffInSeconds($cursor, absolute: true);
            }
            $cursor = $evento->creado_en;
            $estadoActual = $evento->tipo_evento === 'agente.online' ? 'online' : 'offline';
        }

        if ($estadoActual === 'online') {
            $segundosOnline += $hasta->diffInSeconds($cursor, absolute: true);
        }

        $totalSegundos = $hasta->diffInSeconds($desde, absolute: true);

        return $totalSegundos > 0 ? round(($segundosOnline / $totalSegundos) * 100, 2) : 0.0;
    }
}

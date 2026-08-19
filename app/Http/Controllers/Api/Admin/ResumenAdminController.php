<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Dedoc\Scramble\Attributes\Group;

/**
 * "Muy general las empresas, como van, cuantos agentes" -- portada del
 * panel de admin. Cruza todos los tenants a proposito, calculado en vivo
 * (a diferencia de EstadisticaAgregada, que es por-empresa y se
 * precalcula): el volumen agregado de toda la plataforma es una consulta
 * mucho mas liviana que N consultas por-empresa.
 */
#[Group('Admin', weight: 100)]
class ResumenAdminController extends Controller
{
    /**
     * [Super admin] Platform-wide overview across every company.
     */
    public function show()
    {
        $totalEmpresas = Empresa::withoutGlobalScopes()->count();
        $empresasActivas = Empresa::withoutGlobalScopes()->where('activo', true)->count();

        $totalAgentes = Agente::withoutGlobalScopes()->count();
        $agentesOnline = Agente::withoutGlobalScopes()->where('estado', 'online')->count();

        $totalImpresoras = Impresora::count();
        $impresorasOnline = Impresora::where('estado_heartbeat', 'online')->count();

        $trabajos24h = TrabajoImpresion::where('created_at', '>=', now()->subDay())
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        $impresos24h = (int) ($trabajos24h['impreso'] ?? 0);
        $fallidos24h = (int) ($trabajos24h['fallo_definitivo'] ?? 0);

        $volumenPorDia = TrabajoImpresion::where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as cantidad')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($f) => ['date' => $f->fecha, 'count' => (int) $f->cantidad]);

        // Ver nota en EmpresaAdminController@index: sin withoutGlobalScopes()
        // en el subquery, BelongsToTenant (Agente) cuenta solo los agentes
        // de la empresa del super admin autenticado, no los de cada fila.
        $topEmpresas = Empresa::withoutGlobalScopes()
            ->withCount(['agentes' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByDesc('agentes_count')
            ->limit(5)
            ->get(['id', 'nombre', 'codigo', 'activo'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->nombre, 'code' => $e->codigo, 'active' => $e->activo, 'agents_count' => $e->agentes_count]);

        return response()->json(['data' => [
            /** Across every company on the platform, regardless of who's calling. */
            'companies' => [
                'total' => $totalEmpresas,
                'active' => $empresasActivas,
                /** Signed up but not yet activated -- same meaning as `active: false` on the companies endpoints. */
                'pending' => $totalEmpresas - $empresasActivas,
            ],
            /** Across every company. `online` means a heartbeat arrived in the last 60s. */
            'agents' => ['total' => $totalAgentes, 'online' => $agentesOnline],
            /** Across every company's agents. */
            'printers' => ['total' => $totalImpresoras, 'online' => $impresorasOnline],
            /** Jobs completed in the last 24h, platform-wide (not per-company). */
            'jobs_24h' => [
                'printed' => $impresos24h,
                'failed' => $fallidos24h,
                /** Null if there were zero jobs in the window (avoids a division by zero, not a real 0%). */
                'success_rate' => ($impresos24h + $fallidos24h) > 0 ? round($impresos24h / ($impresos24h + $fallidos24h), 4) : null,
            ],
            /** Daily job counts for the last 7 days, platform-wide -- what the admin dashboard's bar chart is built from. */
            'volume_by_day' => $volumenPorDia,
            /** The 5 companies with the most agents, for a quick "who's actually using this" glance. */
            'top_companies' => $topEmpresas,
        ]]);
    }
}

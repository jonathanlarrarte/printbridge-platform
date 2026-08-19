<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;

/**
 * "Muy general las empresas, como van, cuantos agentes" -- portada del
 * panel de admin. Cruza todos los tenants a proposito, calculado en vivo
 * (a diferencia de EstadisticaAgregada, que es por-empresa y se
 * precalcula): el volumen agregado de toda la plataforma es una consulta
 * mucho mas liviana que N consultas por-empresa.
 */
class ResumenAdminController extends Controller
{
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
            ->map(fn ($f) => ['fecha' => $f->fecha, 'cantidad' => (int) $f->cantidad]);

        // Ver nota en EmpresaAdminController@index: sin withoutGlobalScopes()
        // en el subquery, BelongsToTenant (Agente) cuenta solo los agentes
        // de la empresa del super admin autenticado, no los de cada fila.
        $topEmpresas = Empresa::withoutGlobalScopes()
            ->withCount(['agentes' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByDesc('agentes_count')
            ->limit(5)
            ->get(['id', 'nombre', 'codigo', 'activo'])
            ->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre, 'codigo' => $e->codigo, 'activo' => $e->activo, 'agentes_count' => $e->agentes_count]);

        return response()->json(['data' => [
            'empresas' => [
                'total' => $totalEmpresas,
                'activas' => $empresasActivas,
                'pendientes' => $totalEmpresas - $empresasActivas,
            ],
            'agentes' => ['total' => $totalAgentes, 'online' => $agentesOnline],
            'impresoras' => ['total' => $totalImpresoras, 'online' => $impresorasOnline],
            'trabajos_24h' => [
                'impresos' => $impresos24h,
                'fallidos' => $fallidos24h,
                'tasa_exito' => ($impresos24h + $fallidos24h) > 0 ? round($impresos24h / ($impresos24h + $fallidos24h), 4) : null,
            ],
            'volumen_por_dia' => $volumenPorDia,
            'top_empresas' => $topEmpresas,
        ]]);
    }
}

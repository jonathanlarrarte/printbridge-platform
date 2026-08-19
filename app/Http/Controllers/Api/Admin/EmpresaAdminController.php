<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgenteResource;
use App\Models\Agente;
use App\Models\Empresa;
use App\Models\EstadisticaAgregada;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * Panel de super admin (seccion "yo quiero ser el super admin" -- ve y
 * administra TODAS las empresas, no solo la propia). Todo aca usa
 * withoutGlobalScopes() a proposito: BelongsToTenant existe justamente
 * para que un endpoint nuevo no pueda "olvidarse" de filtrar por empresa,
 * pero este es el unico lugar donde cruzar ese limite es el objetivo.
 */
#[Group('Admin', weight: 100)]
class EmpresaAdminController extends Controller
{
    /**
     * [Super admin] List every company on the platform.
     */
    public function index()
    {
        // withCount('agentes') sin mas: el global scope BelongsToTenant de
        // Agente se cuela en el subquery cuando hay un usuario autenticado
        // (que siempre lo hay aca), contando solo los agentes de la propia
        // empresa del super admin en vez de los de cada fila listada.
        $empresas = Empresa::withoutGlobalScopes()
            ->withCount(['agentes' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $empresas->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->nombre,
            'code' => $e->codigo,
            'plan' => $e->plan,
            'active' => $e->activo,
            'agents_count' => $e->agentes_count,
            'created_at' => $e->created_at,
        ])]);
    }

    /**
     * [Super admin] Create a company.
     *
     * Unlike self-service signup, companies created here are active
     * immediately.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:50'],
        ]);

        $empresa = Empresa::create([
            'nombre' => $datos['name'],
            'codigo' => Empresa::generarCodigoUnico($datos['name']),
            'plan' => $datos['plan'] ?? 'piloto',
            // Alta manual por el super admin: activa de una, a diferencia
            // del signup self-service (que arranca inactivo).
            'activo' => true,
        ]);

        return response()->json(['data' => [
            'id' => $empresa->id, 'name' => $empresa->nombre, 'code' => $empresa->codigo,
            'plan' => $empresa->plan, 'active' => $empresa->activo, 'created_at' => $empresa->created_at,
        ]], 201);
    }

    /**
     * [Super admin] Get a company's detail: agents, printers, stats, and API keys.
     */
    public function show(int $id)
    {
        $empresa = Empresa::withoutGlobalScopes()->findOrFail($id);
        $agentes = Agente::withoutGlobalScopes()->where('empresa_id', $id)->with('impresoras.ultimoTrabajo')->orderBy('id')->get();
        $estadisticas = EstadisticaAgregada::withoutGlobalScopes()
            ->where('empresa_id', $id)->whereNull('agente_id')->first();

        return response()->json(['data' => [
            'company' => [
                'id' => $empresa->id, 'name' => $empresa->nombre, 'code' => $empresa->codigo,
                'plan' => $empresa->plan, 'active' => $empresa->activo, 'created_at' => $empresa->created_at,
            ],
            'agents' => AgenteResource::collection($agentes),
            'stats' => $estadisticas?->datos,
            'stats_calculated_at' => $estadisticas?->calculado_en,
            'api_keys' => $empresa->tokens()->orderByDesc('id')->get()->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'last_used_at' => $t->last_used_at, 'created_at' => $t->created_at,
            ]),
        ]]);
    }

    /**
     * [Super admin] Activate/deactivate a company, or change its plan.
     */
    public function update(Request $request, int $id)
    {
        $empresa = Empresa::withoutGlobalScopes()->findOrFail($id);

        $datos = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'plan' => ['sometimes', 'string', 'max:50'],
        ]);

        $empresa->update([
            'activo' => $datos['active'] ?? $empresa->activo,
            'plan' => $datos['plan'] ?? $empresa->plan,
        ]);

        return response()->json(['data' => [
            'id' => $empresa->id, 'name' => $empresa->nombre, 'code' => $empresa->codigo,
            'plan' => $empresa->plan, 'active' => $empresa->activo,
        ]]);
    }
}

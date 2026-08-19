<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgenteResource;
use App\Models\Agente;
use App\Models\Empresa;
use App\Models\EstadisticaAgregada;
use Illuminate\Http\Request;

/**
 * Panel de super admin (seccion "yo quiero ser el super admin" -- ve y
 * administra TODAS las empresas, no solo la propia). Todo aca usa
 * withoutGlobalScopes() a proposito: BelongsToTenant existe justamente
 * para que un endpoint nuevo no pueda "olvidarse" de filtrar por empresa,
 * pero este es el unico lugar donde cruzar ese limite es el objetivo.
 */
class EmpresaAdminController extends Controller
{
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
            'nombre' => $e->nombre,
            'codigo' => $e->codigo,
            'plan' => $e->plan,
            'activo' => $e->activo,
            'agentes_count' => $e->agentes_count,
            'creado_en' => $e->created_at,
        ])]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:50'],
        ]);

        $empresa = Empresa::create([
            'nombre' => $datos['nombre'],
            'codigo' => Empresa::generarCodigoUnico($datos['nombre']),
            'plan' => $datos['plan'] ?? 'piloto',
            // Alta manual por el super admin: activa de una, a diferencia
            // del signup self-service (que arranca inactivo).
            'activo' => true,
        ]);

        return response()->json(['data' => [
            'id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo,
            'plan' => $empresa->plan, 'activo' => $empresa->activo, 'creado_en' => $empresa->created_at,
        ]], 201);
    }

    public function show(int $id)
    {
        $empresa = Empresa::withoutGlobalScopes()->findOrFail($id);
        $agentes = Agente::withoutGlobalScopes()->where('empresa_id', $id)->with('impresoras.ultimoTrabajo')->orderBy('id')->get();
        $estadisticas = EstadisticaAgregada::withoutGlobalScopes()
            ->where('empresa_id', $id)->whereNull('agente_id')->first();

        return response()->json(['data' => [
            'empresa' => [
                'id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo,
                'plan' => $empresa->plan, 'activo' => $empresa->activo, 'creado_en' => $empresa->created_at,
            ],
            'agentes' => AgenteResource::collection($agentes),
            'estadisticas' => $estadisticas?->datos,
            'estadisticas_calculado_en' => $estadisticas?->calculado_en,
            'api_keys' => $empresa->tokens()->orderByDesc('id')->get()->map(fn ($t) => [
                'id' => $t->id, 'nombre' => $t->name, 'ultimo_uso' => $t->last_used_at, 'creado_en' => $t->created_at,
            ]),
        ]]);
    }

    public function update(Request $request, int $id)
    {
        $empresa = Empresa::withoutGlobalScopes()->findOrFail($id);

        $datos = $request->validate([
            'activo' => ['sometimes', 'boolean'],
            'plan' => ['sometimes', 'string', 'max:50'],
        ]);

        $empresa->update($datos);

        return response()->json(['data' => [
            'id' => $empresa->id, 'nombre' => $empresa->nombre, 'codigo' => $empresa->codigo,
            'plan' => $empresa->plan, 'activo' => $empresa->activo,
        ]]);
    }
}

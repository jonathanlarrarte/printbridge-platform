<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgenteResource;
use App\Http\Resources\ImpresoraResource;
use App\Models\Agente;
use Dedoc\Scramble\Attributes\Group;

#[Group('Agents')]
class AgenteController extends Controller
{
    /**
     * List agents.
     *
     * Results are already scoped to your company by the BelongsToTenant
     * global scope on the Agente model.
     */
    public function index()
    {
        return AgenteResource::collection(Agente::with('impresoras.ultimoTrabajo')->orderBy('id')->paginate());
    }

    /**
     * Get an agent.
     */
    public function show(int $id)
    {
        // findOrFail (no route-model-binding) para garantizar que el global
        // scope de tenant ya se evalua con Auth::user() resuelto por el
        // middleware auth:sanctum, que corre antes de este metodo.
        $agente = Agente::with('impresoras.ultimoTrabajo')->findOrFail($id);

        return new AgenteResource($agente);
    }

    /**
     * List an agent's printers.
     */
    public function impresoras(int $id)
    {
        $agente = Agente::findOrFail($id);

        return ImpresoraResource::collection($agente->impresoras()->with('ultimoTrabajo')->get());
    }
}

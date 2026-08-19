<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgenteResource;
use App\Http\Resources\ImpresoraResource;
use App\Models\Agente;

class AgenteController extends Controller
{
    /**
     * GET /v1/agents — la lista ya viene filtrada por empresa gracias al
     * global scope BelongsToTenant en el modelo Agente.
     */
    public function index()
    {
        return AgenteResource::collection(Agente::with('impresoras.ultimoTrabajo')->orderBy('id')->paginate());
    }

    public function show(int $id)
    {
        // findOrFail (no route-model-binding) para garantizar que el global
        // scope de tenant ya se evalua con Auth::user() resuelto por el
        // middleware auth:sanctum, que corre antes de este metodo.
        $agente = Agente::with('impresoras.ultimoTrabajo')->findOrFail($id);

        return new AgenteResource($agente);
    }

    public function impresoras(int $id)
    {
        $agente = Agente::findOrFail($id);

        return ImpresoraResource::collection($agente->impresoras()->with('ultimoTrabajo')->get());
    }
}

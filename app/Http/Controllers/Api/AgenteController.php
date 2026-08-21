<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgenteResource;
use App\Http\Resources\ImpresoraResource;
use App\Models\Agente;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

#[Group('Agents')]
class AgenteController extends Controller
{
    /**
     * List agents.
     *
     * Results are already scoped to your company by the BelongsToTenant
     * global scope on the Agente model.
     */
    #[QueryParameter('per_page', description: 'Results per page, up to 200. Defaults to 200 -- this listing is meant to be viewed as a full fleet, not paged through.', type: 'integer')]
    public function index(Request $request)
    {
        // Default bien mas alto que el estandar de Laravel (15): a
        // diferencia de un listado paginado clasico, el dashboard de
        // Agentes es un "tablero" -- se espera ver la flota completa de un
        // vistazo, no ir pasando de pagina. 200 cubre cadenas grandes
        // (decenas de sucursales x varias cajas c/u) sin dejar de ser un
        // limite real.
        $porPagina = min((int) $request->query('per_page', 200), 200);

        return AgenteResource::collection(
            Agente::with('impresoras.ultimoTrabajo')->orderBy('id')->paginate($porPagina)
        );
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

    /**
     * Delete an agent.
     *
     * This is permanent and cascades: every printer, job, event, and
     * test-print for this agent is deleted with it -- there's no undo. The
     * main reason to use this is to free up an `installation_id` that was
     * accidentally registered to the wrong company, so it can be
     * re-registered elsewhere (`POST /agent/register` rejects an
     * `installation_id` that's already claimed by a *different* company
     * with a 409).
     */
    public function destroy(int $id)
    {
        $agente = Agente::findOrFail($id);
        $agente->delete();

        return response()->json(null, 204);
    }
}

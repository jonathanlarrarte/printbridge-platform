<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrabajoResource;
use App\Models\TrabajoImpresion;
use App\Support\JobStatus;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Jobs')]
class TrabajoController extends Controller
{
    /**
     * List print jobs.
     *
     * Filterable by agent, printer, status, and date range.
     */
    public function index(Request $request)
    {
        $datos = $request->validate([
            'agent_id' => ['nullable', 'integer'],
            'printer_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(JobStatus::valoresApi())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = TrabajoImpresion::whereHas('agente')
            ->when($datos['agent_id'] ?? null, fn ($q, $v) => $q->where('agente_id', $v))
            ->when($datos['printer_id'] ?? null, fn ($q, $v) => $q->where('impresora_id', $v))
            ->when($datos['status'] ?? null, fn ($q, $v) => $q->where('estado', JobStatus::toInternal($v)))
            ->when($datos['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($datos['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->orderByDesc('id');

        return TrabajoResource::collection($query->paginate());
    }

    /**
     * Get a print job.
     *
     * Includes its full event history.
     */
    public function show(int $id)
    {
        $trabajo = TrabajoImpresion::whereHas('agente')->with('eventos')->findOrFail($id);

        return new TrabajoResource($trabajo);
    }
}

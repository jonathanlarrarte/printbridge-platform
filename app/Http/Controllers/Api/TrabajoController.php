<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrabajoResource;
use App\Models\TrabajoImpresion;
use App\Support\JobStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrabajoController extends Controller
{
    /**
     * GET /v1/jobs — trabajos_impresion no tiene empresa_id propio, asi
     * que el aislamiento por tenant se logra con whereHas('agente'): el
     * global scope de Agente filtra la subquery automaticamente.
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

    public function show(int $id)
    {
        $trabajo = TrabajoImpresion::whereHas('agente')->with('eventos')->findOrFail($id);

        return new TrabajoResource($trabajo);
    }
}

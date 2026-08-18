<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrabajoResource;
use App\Models\TrabajoImpresion;
use Illuminate\Http\Request;

class TrabajoController extends Controller
{
    /**
     * GET /v1/trabajos — trabajos_impresion no tiene empresa_id propio, asi
     * que el aislamiento por tenant se logra con whereHas('agente'): el
     * global scope de Agente filtra la subquery automaticamente.
     */
    public function index(Request $request)
    {
        $datos = $request->validate([
            'agente_id' => ['nullable', 'integer'],
            'impresora_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $query = TrabajoImpresion::whereHas('agente')
            ->when($datos['agente_id'] ?? null, fn ($q, $v) => $q->where('agente_id', $v))
            ->when($datos['impresora_id'] ?? null, fn ($q, $v) => $q->where('impresora_id', $v))
            ->when($datos['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($datos['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($datos['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->orderByDesc('id');

        return TrabajoResource::collection($query->paginate());
    }

    public function show(int $id)
    {
        $trabajo = TrabajoImpresion::whereHas('agente')->with('eventos')->findOrFail($id);

        return new TrabajoResource($trabajo);
    }
}

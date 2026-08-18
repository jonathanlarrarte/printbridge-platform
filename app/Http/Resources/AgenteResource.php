<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgenteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instalacion_id' => $this->instalacion_id,
            'nombre_descriptivo' => $this->nombre_descriptivo,
            'estado' => $this->estado,
            'ultimo_heartbeat' => $this->ultimo_heartbeat,
            'version_agente' => $this->version_agente,
            'creado_en' => $this->creado_en,
            'impresoras' => ImpresoraResource::collection($this->whenLoaded('impresoras')),
        ];
    }
}

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
            'installation_id' => $this->instalacion_id,
            'display_name' => $this->nombre_descriptivo,
            'status' => $this->estado,
            'last_heartbeat_at' => $this->ultimo_heartbeat,
            'agent_version' => $this->version_agente,
            'created_at' => $this->creado_en,
            'printers' => ImpresoraResource::collection($this->whenLoaded('impresoras')),
        ];
    }
}

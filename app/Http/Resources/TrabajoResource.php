<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrabajoResource extends JsonResource
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
            'agente_id' => $this->agente_id,
            'impresora_id' => $this->impresora_id,
            'job_id_externo' => $this->job_id_externo,
            'target' => $this->target,
            'format' => $this->format,
            'estado' => $this->estado,
            'intentos' => $this->intentos,
            'error_mensaje' => $this->error_mensaje,
            'duracion_ms' => $this->duracion_ms,
            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
            'eventos' => EventoResource::collection($this->whenLoaded('eventos')),
        ];
    }
}

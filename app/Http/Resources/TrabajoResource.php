<?php

namespace App\Http\Resources;

use App\Support\JobStatus;
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
            'agent_id' => $this->agente_id,
            'printer_id' => $this->impresora_id,
            'external_job_id' => $this->job_id_externo,
            'target' => $this->target,
            'format' => $this->format,
            'status' => JobStatus::toApi($this->estado),
            'attempts' => $this->intentos,
            'error_message' => $this->error_mensaje,
            'duration_ms' => $this->duracion_ms,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'events' => EventoResource::collection($this->whenLoaded('eventos')),
        ];
    }
}

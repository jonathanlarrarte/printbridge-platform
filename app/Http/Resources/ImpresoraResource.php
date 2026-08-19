<?php

namespace App\Http\Resources;

use App\Support\JobStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImpresoraResource extends JsonResource
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
            'alias' => $this->alias,
            'type' => $this->tipo,
            'protocol' => $this->protocolo,
            'system_name' => $this->nombre_sistema,
            'ip' => $this->ip,
            'port' => $this->puerto,
            'status' => $this->estado_heartbeat,
            'updated_at' => $this->actualizado_en,
            'last_job' => $this->whenLoaded('ultimoTrabajo', fn () => $this->ultimoTrabajo ? [
                'status' => JobStatus::toApi($this->ultimoTrabajo->estado),
                'error_message' => $this->ultimoTrabajo->error_mensaje,
                'created_at' => $this->ultimoTrabajo->created_at,
            ] : null),
        ];
    }
}

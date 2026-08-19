<?php

namespace App\Http\Resources;

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
            'tipo' => $this->tipo,
            'protocolo' => $this->protocolo,
            'nombre_sistema' => $this->nombre_sistema,
            'ip' => $this->ip,
            'puerto' => $this->puerto,
            'estado_heartbeat' => $this->estado_heartbeat,
            'actualizado_en' => $this->actualizado_en,
            'ultimo_trabajo' => $this->whenLoaded('ultimoTrabajo', fn () => $this->ultimoTrabajo ? [
                'estado' => $this->ultimoTrabajo->estado,
                'error_mensaje' => $this->ultimoTrabajo->error_mensaje,
                'creado_en' => $this->ultimoTrabajo->created_at,
            ] : null),
        ];
    }
}

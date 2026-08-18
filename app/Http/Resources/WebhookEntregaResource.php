<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEntregaResource extends JsonResource
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
            'evento_id' => $this->evento_id,
            'intento' => $this->intento,
            'status_http' => $this->status_http,
            'respuesta_resumen' => $this->respuesta_resumen,
            'entregado_en' => $this->entregado_en,
            'exitosa' => ! is_null($this->entregado_en),
        ];
    }
}

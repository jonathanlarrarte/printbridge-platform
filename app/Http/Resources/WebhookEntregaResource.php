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
            'event_id' => $this->evento_id,
            'attempt' => $this->intento,
            'http_status' => $this->status_http,
            'response_summary' => $this->respuesta_resumen,
            'delivered_at' => $this->entregado_en,
            'successful' => ! is_null($this->entregado_en),
        ];
    }
}

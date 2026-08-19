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
            /** The platform event that triggered this delivery. */
            'event_id' => $this->evento_id,
            /** Which attempt this was (1-5) -- failed deliveries retry with backoff: 30s, 2min, 10min, 1h, 6h. */
            'attempt' => $this->intento,
            /** The HTTP status your endpoint returned. Null if the request never got a response at all (timeout, DNS failure, connection refused). */
            'http_status' => $this->status_http,
            /** First 500 characters of your endpoint's response body, or the network error message if there was no response. */
            'response_summary' => $this->respuesta_resumen,
            /** When this attempt succeeded. Null if it failed (see `successful`). */
            'delivered_at' => $this->entregado_en,
            /** Whether this specific attempt succeeded -- equivalent to `delivered_at` being non-null. */
            'successful' => ! is_null($this->entregado_en),
        ];
    }
}

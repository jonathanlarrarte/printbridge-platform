<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
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
            /** Where deliveries get POSTed, signed with `X-PrintBridge-Signature`. */
            'url' => $this->url,
            /** Which event types trigger a delivery to this webhook -- see `event_type` on the events resource for the full catalog. */
            'subscribed_events' => $this->eventos_suscritos,
            'active' => $this->activo,
            'created_at' => $this->creado_en,
            // El secreto solo se devuelve una vez, en la respuesta de creacion
            // (ver WebhookController::store) -- nunca en index/show.
        ];
    }
}

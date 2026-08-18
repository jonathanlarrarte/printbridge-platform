<?php

namespace App\Jobs;

use App\Models\Evento;
use App\Models\WebhookConfigurado;
use App\Models\WebhookEntrega;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Entrega un evento a un webhook configurado, con firma HMAC-SHA256
 * (seccion 8.2) y backoff exponencial: 30s, 2min, 10min, 1h, 6h — 5
 * intentos totales (seccion 8.3). Cada intento queda en webhook_entregas.
 */
class EntregarWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public int $webhookId,
        public int $eventoId,
    ) {}

    public function backoff(): array
    {
        return [30, 120, 600, 3600, 21600];
    }

    public function handle(): void
    {
        $webhook = WebhookConfigurado::withoutGlobalScopes()->findOrFail($this->webhookId);
        $evento = Evento::withoutGlobalScopes()->findOrFail($this->eventoId);

        if (! $webhook->activo) {
            return;
        }

        $payload = [
            'tipo_evento' => $evento->tipo_evento,
            'evento_id' => $evento->id,
            'trabajo_id' => $evento->trabajo_id,
            'agente_id' => $evento->agente_id,
            'creado_en' => $evento->creado_en?->toIso8601String(),
            'datos' => $evento->payload,
        ];
        $payloadJson = json_encode($payload);
        $firma = hash_hmac('sha256', $payloadJson, $webhook->secreto);

        $intento = $this->attempts();
        $statusHttp = null;
        $respuestaResumen = null;

        try {
            $respuesta = Http::withBody($payloadJson, 'application/json')
                ->withHeaders(['X-PrintBridge-Signature' => "sha256={$firma}"])
                ->timeout(10)
                ->post($webhook->url);

            $statusHttp = $respuesta->status();
            $respuestaResumen = mb_substr($respuesta->body(), 0, 500);

            WebhookEntrega::create([
                'webhook_id' => $webhook->id,
                'evento_id' => $evento->id,
                'intento' => $intento,
                'status_http' => $statusHttp,
                'respuesta_resumen' => $respuestaResumen,
                'entregado_en' => $respuesta->successful() ? now() : null,
            ]);

            $respuesta->throw();
        } catch (\Throwable $e) {
            if (is_null($statusHttp)) {
                // Fallo de red (timeout, DNS, conexion rechazada), no hubo respuesta HTTP.
                WebhookEntrega::create([
                    'webhook_id' => $webhook->id,
                    'evento_id' => $evento->id,
                    'intento' => $intento,
                    'status_http' => null,
                    'respuesta_resumen' => mb_substr($e->getMessage(), 0, 500),
                    'entregado_en' => null,
                ]);
            }

            throw new RuntimeException("Entrega de webhook {$webhook->id} fallo (intento {$intento}): {$e->getMessage()}", 0, $e);
        }
    }
}

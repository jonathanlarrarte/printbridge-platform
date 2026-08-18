<?php

namespace App\Services;

use App\Jobs\EntregarWebhook;
use App\Models\Agente;
use App\Models\Evento;
use App\Models\WebhookConfigurado;

/**
 * Punto compartido para registrar eventos que no vienen de
 * POST /agente/eventos (es decir, transiciones detectadas por la propia
 * plataforma: agente.online/agente.offline) y disparar sus webhooks
 * suscritos, reusando el mismo criterio de AgenteIngestaController@eventos
 * y ProcesarEventoAgente.
 */
class EventosPlataforma
{
    public static function registrarTransicionAgente(Agente $agente, string $tipoEvento): void
    {
        $evento = Evento::create([
            'empresa_id' => $agente->empresa_id,
            'agente_id' => $agente->id,
            'trabajo_id' => null,
            'tipo_evento' => $tipoEvento,
            'payload' => ['instalacion_id' => $agente->instalacion_id],
            'creado_en' => now(),
        ]);

        $webhooks = WebhookConfigurado::withoutGlobalScopes()
            ->where('empresa_id', $agente->empresa_id)
            ->where('activo', true)
            ->whereJsonContains('eventos_suscritos', $tipoEvento)
            ->get();

        foreach ($webhooks as $webhook) {
            EntregarWebhook::dispatch($webhook->id, $evento->id);
        }
    }
}

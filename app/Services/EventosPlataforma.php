<?php

namespace App\Services;

use App\Jobs\EntregarWebhook;
use App\Models\Agente;
use App\Models\Evento;
use App\Models\WebhookConfigurado;

/**
 * Punto compartido para registrar eventos que no vienen de
 * POST /agent/events (es decir, transiciones detectadas por la propia
 * plataforma: agent.online/agent.offline) y disparar sus webhooks
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
            'payload' => ['installation_id' => $agente->instalacion_id],
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

<?php

namespace App\Jobs;

use App\Models\Agente;
use App\Models\Evento;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Models\WebhookConfigurado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa en segundo plano un evento reportado por el agente (seccion 7 del
 * doc): resuelve/actualiza el trabajo de impresion, deja constancia
 * inmutable en la tabla eventos, y dispara los webhooks suscritos.
 */
class ProcesarEventoAgente implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $agenteId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $agente = Agente::withoutGlobalScopes()->findOrFail($this->agenteId);

        $trabajo = TrabajoImpresion::firstOrCreate(
            ['agente_id' => $agente->id, 'job_id_externo' => $this->payload['job_id_externo']],
            [
                'target' => $this->payload['target'],
                'format' => $this->payload['format'] ?? null,
                'estado' => 'pendiente',
            ]
        );

        $estado = match ($this->payload['tipo_evento']) {
            'trabajo.creado' => 'en_cola',
            'trabajo.imprimiendo' => 'imprimiendo',
            'trabajo.impreso' => 'impreso',
            'trabajo.fallo_definitivo' => 'fallo_definitivo',
        };

        $impresora = Impresora::where('agente_id', $agente->id)
            ->where('alias', $this->payload['target'])
            ->first();

        $actualizacion = ['estado' => $estado];

        if ($impresora) {
            $actualizacion['impresora_id'] = $impresora->id;
        }

        if ($estado === 'fallo_definitivo') {
            $actualizacion['error_mensaje'] = $this->payload['error_mensaje'] ?? null;
            $actualizacion['intentos'] = $trabajo->intentos + 1;
        }

        if ($estado === 'impreso' && isset($this->payload['duracion_ms'])) {
            $actualizacion['duracion_ms'] = $this->payload['duracion_ms'];
        }

        $trabajo->update($actualizacion);

        // insertOrIgnore respeta el indice unico parcial (trabajo_id, tipo_evento):
        // un reintento de red del agente no duplica la fila.
        $filasInsertadas = Evento::query()->insertOrIgnore([[
            'empresa_id' => $agente->empresa_id,
            'agente_id' => $agente->id,
            'trabajo_id' => $trabajo->id,
            'tipo_evento' => $this->payload['tipo_evento'],
            'payload' => json_encode($this->payload),
            'creado_en' => now(),
        ]]);

        // Solo se dispara el webhook si la fila es realmente nueva -- un
        // reintento de red del agente que resulta en un insert ignorado no
        // debe volver a notificar a los suscriptores (misma idempotencia
        // que el resto del pipeline).
        if ($filasInsertadas > 0) {
            $evento = Evento::withoutGlobalScopes()
                ->where('trabajo_id', $trabajo->id)
                ->where('tipo_evento', $this->payload['tipo_evento'])
                ->first();

            $webhooks = WebhookConfigurado::withoutGlobalScopes()
                ->where('empresa_id', $agente->empresa_id)
                ->where('activo', true)
                ->whereJsonContains('eventos_suscritos', $this->payload['tipo_evento'])
                ->get();

            foreach ($webhooks as $webhook) {
                EntregarWebhook::dispatch($webhook->id, $evento->id);
            }
        }
    }
}

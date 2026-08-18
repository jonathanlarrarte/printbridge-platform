<?php

namespace App\Console\Commands;

use App\Models\Agente;
use App\Services\EventosPlataforma;
use Illuminate\Console\Command;

/**
 * El agente manda heartbeat cada 15-30s (main/plataforma.js). Si no llega
 * nada en 60s (2x el intervalo mas lento), se considera offline. Sin esto
 * no hay forma de detectar una caida real (el agente no puede avisar que
 * se cayo) ni de calcular uptime real (seccion 9 del doc).
 */
class DetectarAgentesOffline extends Command
{
    protected $signature = 'app:detectar-agentes-offline';

    protected $description = 'Marca offline a los agentes cuyo heartbeat vencio, y registra el evento agente.offline';

    private const SEGUNDOS_LIMITE = 60;

    public function handle(): void
    {
        $limite = now()->subSeconds(self::SEGUNDOS_LIMITE);

        $agentes = Agente::withoutGlobalScopes()
            ->where('estado', 'online')
            ->where(function ($q) use ($limite) {
                $q->whereNull('ultimo_heartbeat')->orWhere('ultimo_heartbeat', '<', $limite);
            })
            ->get();

        foreach ($agentes as $agente) {
            $agente->update(['estado' => 'offline']);
            EventosPlataforma::registrarTransicionAgente($agente, 'agente.offline');
            $this->info("Agente {$agente->instalacion_id} marcado offline (sin heartbeat desde {$agente->ultimo_heartbeat}).");
        }
    }
}

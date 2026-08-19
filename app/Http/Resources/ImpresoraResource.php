<?php

namespace App\Http\Resources;

use App\Support\JobStatus;
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
            /** The logical name your POS prints to (e.g. `receipt`, `wristband`) -- this is the `target` field in the local print job, not any physical identifier. Configured once in the agent's window. */
            'alias' => $this->alias,
            /** How this printer is physically connected: `red` (network) or `usb`/`local`. */
            'type' => $this->tipo,
            /** The command language this printer speaks: `escpos` or `tspl`. Used as the default `format` when a local print job doesn't specify one. */
            'protocol' => $this->protocolo,
            /** The printer's name as registered with the Windows spooler -- only set for `usb`/`local` printers. */
            'system_name' => $this->nombre_sistema,
            /** Network address -- only set for `type: "red"` printers. */
            'ip' => $this->ip,
            /** Network port -- only set for `type: "red"` printers (typically 9100, standard raw printing). */
            'port' => $this->puerto,
            /** `online` or `offline`, from the agent's last heartbeat. Goes stale (not live) if the agent itself is offline -- check the parent agent's `status` too. */
            'status' => $this->estado_heartbeat,
            'updated_at' => $this->actualizado_en,
            /** The outcome of the most recent job sent to this printer -- only included when explicitly loaded (`GET /v1/agents/{id}/printers`). Null if this printer has never printed anything yet. */
            'last_job' => $this->whenLoaded('ultimoTrabajo', fn () => $this->ultimoTrabajo ? [
                /** `printed` or `failed` -- only ever a terminal status, since this reflects the *last completed* job. */
                'status' => JobStatus::toApi($this->ultimoTrabajo->estado),
                'error_message' => $this->ultimoTrabajo->error_mensaje,
                'created_at' => $this->ultimoTrabajo->created_at,
            ] : null),
        ];
    }
}

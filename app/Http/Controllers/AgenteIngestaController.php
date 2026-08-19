<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarEventoAgente;
use App\Models\Agente;
use App\Models\ComandoPrueba;
use App\Models\Empresa;
use App\Models\Impresora;
use App\Services\EventosPlataforma;
use App\Support\EventType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoints privados agente -> plataforma (seccion 6.1 del doc). El campo
 * de entrada/salida (JSON) esta en ingles a proposito (superficie publica
 * de la API); los nombres internos (metodos, variables, columnas) siguen
 * en espanol -- ver app/Support/JobStatus.php y EventType.php.
 */
class AgenteIngestaController extends Controller
{
    /**
     * POST /agent/register — bootstrap de identidad. No usa agente.auth
     * (todavia no existe el token): se valida con el codigo de cliente
     * capturado en el instalador del agente.
     */
    public function registrar(Request $request)
    {
        $datos = $request->validate([
            'installation_id' => ['required', 'string', 'max:255'],
            'client_code' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
        ]);

        $empresa = Empresa::where('codigo', $datos['client_code'])->first();

        if (! $empresa) {
            return response()->json(['error' => 'client code not recognized'], 404);
        }

        if (! $empresa->activo) {
            return response()->json(['error' => 'this company has not been activated by an administrator yet'], 403);
        }

        $agente = Agente::withoutGlobalScopes()->where('instalacion_id', $datos['installation_id'])->first();

        if ($agente && $agente->empresa_id !== $empresa->id) {
            return response()->json(['error' => 'this installation is already registered to another company'], 409);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        if ($agente) {
            $agente->update([
                'nombre_descriptivo' => $datos['display_name'] ?? $agente->nombre_descriptivo,
                'version_agente' => $datos['agent_version'] ?? $agente->version_agente,
                'token_hash' => $tokenHash,
            ]);
        } else {
            $agente = Agente::create([
                'empresa_id' => $empresa->id,
                'instalacion_id' => $datos['installation_id'],
                'nombre_descriptivo' => $datos['display_name'] ?? null,
                'version_agente' => $datos['agent_version'] ?? null,
                'token_hash' => $tokenHash,
                'estado' => 'offline',
                'creado_en' => now(),
            ]);
        }

        return response()->json([
            'agent_id' => $agente->id,
            'token' => $token,
        ]);
    }

    /**
     * POST /agent/heartbeat — protegido por agente.auth.
     */
    public function heartbeat(Request $request)
    {
        $agente = $request->attributes->get('agente');

        $datos = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:50'],
            'printers' => ['nullable', 'array'],
            'printers.*.online' => ['required', 'boolean'],
            'printers.*.type' => ['nullable', 'string', 'max:50'],
            'printers.*.ip' => ['nullable', 'string', 'max:100'],
            'printers.*.port' => ['nullable', 'integer'],
            'printers.*.system_name' => ['nullable', 'string', 'max:255'],
            'printers.*.protocol' => ['nullable', 'string', 'max:50'],
        ]);

        $estabaOffline = $agente->estado !== 'online';

        $agente->update([
            'estado' => 'online',
            'ultimo_heartbeat' => now(),
            'version_agente' => $datos['agent_version'] ?? $agente->version_agente,
        ]);

        if ($estabaOffline) {
            EventosPlataforma::registrarTransicionAgente($agente, EventType::AGENT_ONLINE);
        }

        foreach ($datos['printers'] ?? [] as $alias => $info) {
            Impresora::updateOrCreate(
                ['agente_id' => $agente->id, 'alias' => $alias],
                [
                    'tipo' => $info['type'] ?? null,
                    'ip' => $info['ip'] ?? null,
                    'puerto' => $info['port'] ?? null,
                    'nombre_sistema' => $info['system_name'] ?? null,
                    'protocolo' => $info['protocol'] ?? null,
                    'estado_heartbeat' => $info['online'] ? 'online' : 'offline',
                    'actualizado_en' => now(),
                ]
            );
        }

        // Unico "empuje" de la plataforma hacia el agente: van montados en
        // la respuesta del heartbeat que el agente YA manda cada 15-30s
        // (seccion 2 del doc -- el agente sigue siendo quien inicia la
        // conexion). Se marcan entregados aca mismo para no reenviarlos.
        $comandos = ComandoPrueba::where('agente_id', $agente->id)->where('estado', 'pendiente')->get();

        if ($comandos->isNotEmpty()) {
            ComandoPrueba::whereIn('id', $comandos->pluck('id'))->update(['estado' => 'entregado', 'entregado_en' => now()]);
        }

        return response()->json([
            'ok' => true,
            'pending_commands' => $comandos->map(fn ($c) => [
                'id' => $c->job_id_externo,
                'target' => $c->target,
                'format' => $c->format,
                'data' => $c->data,
            ]),
        ]);
    }

    /**
     * POST /agent/events — protegido por agente.auth. Responde 202 de
     * inmediato y encola el procesamiento pesado (seccion 7 del doc).
     */
    public function eventos(Request $request)
    {
        $agente = $request->attributes->get('agente');

        $datos = $request->validate([
            'event_type' => ['required', Rule::in(EventType::JOB_EVENTS)],
            'external_job_id' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:50'],
            'error_message' => ['nullable', 'string'],
            'duration_ms' => ['nullable', 'integer'],
        ]);

        ProcesarEventoAgente::dispatch($agente->id, [
            'tipo_evento' => $datos['event_type'],
            'job_id_externo' => $datos['external_job_id'],
            'target' => $datos['target'],
            'format' => $datos['format'] ?? null,
            'error_mensaje' => $datos['error_message'] ?? null,
            'duracion_ms' => $datos['duration_ms'] ?? null,
        ]);

        return response()->json(['status' => 'accepted'], 202);
    }
}

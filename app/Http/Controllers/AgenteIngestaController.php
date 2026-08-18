<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarEventoAgente;
use App\Models\Agente;
use App\Models\Empresa;
use App\Models\Impresora;
use App\Services\EventosPlataforma;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgenteIngestaController extends Controller
{
    /**
     * POST /agente/registrar — bootstrap de identidad. No usa agente.auth
     * (todavia no existe el token): se valida con el codigo de cliente
     * capturado en el instalador del agente.
     */
    public function registrar(Request $request)
    {
        $datos = $request->validate([
            'instalacion_id' => ['required', 'string', 'max:255'],
            'cliente_codigo' => ['required', 'string', 'max:255'],
            'nombre_descriptivo' => ['nullable', 'string', 'max:255'],
            'version_agente' => ['nullable', 'string', 'max:50'],
        ]);

        $empresa = Empresa::where('codigo', $datos['cliente_codigo'])->first();

        if (! $empresa) {
            return response()->json(['error' => 'codigo de cliente no reconocido'], 404);
        }

        $agente = Agente::withoutGlobalScopes()->where('instalacion_id', $datos['instalacion_id'])->first();

        if ($agente && $agente->empresa_id !== $empresa->id) {
            return response()->json(['error' => 'esta instalacion ya esta registrada en otra empresa'], 409);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        if ($agente) {
            $agente->update([
                'nombre_descriptivo' => $datos['nombre_descriptivo'] ?? $agente->nombre_descriptivo,
                'version_agente' => $datos['version_agente'] ?? $agente->version_agente,
                'token_hash' => $tokenHash,
            ]);
        } else {
            $agente = Agente::create([
                'empresa_id' => $empresa->id,
                'instalacion_id' => $datos['instalacion_id'],
                'nombre_descriptivo' => $datos['nombre_descriptivo'] ?? null,
                'version_agente' => $datos['version_agente'] ?? null,
                'token_hash' => $tokenHash,
                'estado' => 'offline',
                'creado_en' => now(),
            ]);
        }

        return response()->json([
            'agente_id' => $agente->id,
            'token' => $token,
        ]);
    }

    /**
     * POST /agente/heartbeat — protegido por agente.auth.
     */
    public function heartbeat(Request $request)
    {
        $agente = $request->attributes->get('agente');

        $datos = $request->validate([
            'version_agente' => ['nullable', 'string', 'max:50'],
            'impresoras' => ['nullable', 'array'],
            'impresoras.*.online' => ['required', 'boolean'],
            'impresoras.*.tipo' => ['nullable', 'string', 'max:50'],
            'impresoras.*.ip' => ['nullable', 'string', 'max:100'],
            'impresoras.*.puerto' => ['nullable', 'integer'],
            'impresoras.*.nombre_sistema' => ['nullable', 'string', 'max:255'],
            'impresoras.*.protocolo' => ['nullable', 'string', 'max:50'],
        ]);

        $estabaOffline = $agente->estado !== 'online';

        $agente->update([
            'estado' => 'online',
            'ultimo_heartbeat' => now(),
            'version_agente' => $datos['version_agente'] ?? $agente->version_agente,
        ]);

        if ($estabaOffline) {
            EventosPlataforma::registrarTransicionAgente($agente, 'agente.online');
        }

        foreach ($datos['impresoras'] ?? [] as $alias => $info) {
            Impresora::updateOrCreate(
                ['agente_id' => $agente->id, 'alias' => $alias],
                [
                    'tipo' => $info['tipo'] ?? null,
                    'ip' => $info['ip'] ?? null,
                    'puerto' => $info['puerto'] ?? null,
                    'nombre_sistema' => $info['nombre_sistema'] ?? null,
                    'protocolo' => $info['protocolo'] ?? null,
                    'estado_heartbeat' => $info['online'] ? 'online' : 'offline',
                    'actualizado_en' => now(),
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /agente/eventos — protegido por agente.auth. Responde 202 de
     * inmediato y encola el procesamiento pesado (seccion 7 del doc).
     */
    public function eventos(Request $request)
    {
        $agente = $request->attributes->get('agente');

        $datos = $request->validate([
            'tipo_evento' => ['required', Rule::in([
                'trabajo.creado', 'trabajo.imprimiendo', 'trabajo.impreso', 'trabajo.fallo_definitivo',
            ])],
            'job_id_externo' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:50'],
            'error_mensaje' => ['nullable', 'string'],
            'duracion_ms' => ['nullable', 'integer'],
        ]);

        ProcesarEventoAgente::dispatch($agente->id, $datos);

        return response()->json(['status' => 'aceptado'], 202);
    }
}

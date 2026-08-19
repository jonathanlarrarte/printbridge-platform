<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\ComandoPrueba;
use App\Models\Impresora;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[Group('Test Print')]
class PruebaImpresionController extends Controller
{
    /**
     * Queue a test print on one of an agent's printers.
     *
     * The agent picks this up on its next heartbeat (within 15-30s) and
     * queues it locally exactly like a real POS print job, so it goes
     * through the same retries and status reporting.
     */
    public function store(Request $request, int $agenteId, int $impresoraId)
    {
        $empresa = $request->user();

        $agente = Agente::where('empresa_id', $empresa->id)->findOrFail($agenteId);
        $impresora = Impresora::where('agente_id', $agente->id)->findOrFail($impresoraId);

        $comando = $this->crearComando($agente, $impresora);

        return response()->json(['data' => [
            /** ID for this test-print job. It follows the same lifecycle as any real print job (poll `GET /v1/jobs?agent_id=...` or subscribe to `job.printed`/`job.failed` webhooks to see the outcome), but you won't find it in your own POS records since PrintBridge generated it, not you. */
            'external_job_id' => $comando->job_id_externo,
            'message' => 'Queued. The agent will print it on its next heartbeat (up to 30s).',
        ]], 202);
    }

    public static function crearComando(Agente $agente, Impresora $impresora): ComandoPrueba
    {
        [$format, $data] = self::contenidoDePrueba($agente, $impresora);

        return ComandoPrueba::create([
            'agente_id' => $agente->id,
            'impresora_id' => $impresora->id,
            'job_id_externo' => 'prueba-'.Str::uuid(),
            'target' => $impresora->alias,
            'format' => $format,
            'data' => $data,
            'estado' => 'pendiente',
            'creado_en' => now(),
        ]);
    }

    /**
     * Reusa los generadores YA existentes del agente (generarRecibo /
     * generarBrazaleteTSPL, ver main/printers/) en vez de inventar bytes
     * crudos nuevos -- son los que ya se prueban con impresoras reales.
     */
    private static function contenidoDePrueba(Agente $agente, Impresora $impresora): array
    {
        $ahora = now()->format('d/m/Y H:i:s');

        if ($impresora->protocolo === 'tspl') {
            return ['tspl', [
                'nombre' => 'PRUEBA PRINTBRIDGE',
                'fecha' => $ahora,
                'codigo' => 'TEST-'.random_int(1000, 9999),
            ]];
        }

        // Default (incluye 'escpos' y protocolo desconocido/null): un
        // recibo corto via el generador ESC/POS existente.
        return ['escpos', [
            'negocio' => 'PrintBridge',
            'encabezado' => ["Prueba de impresión — {$impresora->alias}", "Agente: {$agente->instalacion_id}"],
            'items' => [['nombre' => 'Impresion de prueba', 'cantidad' => 1, 'precio' => 0]],
            'total' => 0,
            'moneda' => '',
            'pie' => ['Generado desde el dashboard', $ahora],
        ]];
    }
}

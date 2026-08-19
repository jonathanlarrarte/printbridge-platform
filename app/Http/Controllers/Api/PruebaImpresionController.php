<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\ComandoPrueba;
use App\Models\Impresora;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PruebaImpresionController extends Controller
{
    /**
     * POST /v1/agentes/{agenteId}/impresoras/{impresoraId}/prueba -- deja
     * un comando pendiente que el agente recoge en su proximo heartbeat
     * (cada 15-30s) y mete en su cola local igual que un trabajo real
     * (main/queue.js -> encolar()), asi que despues sigue el mismo camino
     * de reintentos y reporte que cualquier impresion del POS.
     */
    public function store(Request $request, int $agenteId, int $impresoraId)
    {
        $empresa = $request->user();

        $agente = Agente::where('empresa_id', $empresa->id)->findOrFail($agenteId);
        $impresora = Impresora::where('agente_id', $agente->id)->findOrFail($impresoraId);

        $comando = $this->crearComando($agente, $impresora);

        return response()->json(['data' => [
            'job_id_externo' => $comando->job_id_externo,
            'mensaje' => 'Encolado. El agente lo va a imprimir en su proximo heartbeat (hasta 30s).',
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

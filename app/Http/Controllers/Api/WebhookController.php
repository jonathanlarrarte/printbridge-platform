<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookEntregaResource;
use App\Http\Resources\WebhookResource;
use App\Models\WebhookConfigurado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    /**
     * Catalogo de la seccion 8.1 del doc. agente.online/offline e
     * impresora.online/offline quedan fuera por ahora: requieren un job
     * programado que detecte heartbeats vencidos (fase de estadisticas/
     * observabilidad), y no tiene sentido dejar suscribir a algo que nunca
     * se va a disparar.
     */
    public const EVENTOS_DISPONIBLES = [
        'trabajo.creado', 'trabajo.imprimiendo', 'trabajo.impreso', 'trabajo.fallo_definitivo',
    ];

    public function index()
    {
        return WebhookResource::collection(WebhookConfigurado::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'eventos_suscritos' => ['required', 'array', 'min:1'],
            'eventos_suscritos.*' => [Rule::in(self::EVENTOS_DISPONIBLES)],
        ]);

        $secreto = bin2hex(random_bytes(20));

        $webhook = WebhookConfigurado::create([
            'url' => $datos['url'],
            'eventos_suscritos' => $datos['eventos_suscritos'],
            'secreto' => $secreto,
            'activo' => true,
            'creado_en' => now(),
        ]);

        return response()->json([
            'data' => new WebhookResource($webhook),
            // Unica vez que se devuelve en texto plano -- lo necesita el
            // cliente para verificar X-PrintBridge-Signature (seccion 8.2).
            'secreto' => $secreto,
        ], 201);
    }

    public function destroy(int $id)
    {
        $webhook = WebhookConfigurado::findOrFail($id);
        $webhook->delete();

        return response()->json(null, 204);
    }

    public function entregas(int $id)
    {
        $webhook = WebhookConfigurado::findOrFail($id);

        return WebhookEntregaResource::collection(
            $webhook->entregas()->orderByDesc('id')->paginate()
        );
    }
}

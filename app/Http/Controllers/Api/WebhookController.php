<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookEntregaResource;
use App\Http\Resources\WebhookResource;
use App\Models\WebhookConfigurado;
use App\Support\EventType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function index()
    {
        return WebhookResource::collection(WebhookConfigurado::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'subscribed_events' => ['required', 'array', 'min:1'],
            'subscribed_events.*' => [Rule::in(EventType::ALL)],
        ]);

        $secreto = bin2hex(random_bytes(20));

        $webhook = WebhookConfigurado::create([
            'url' => $datos['url'],
            'eventos_suscritos' => $datos['subscribed_events'],
            'secreto' => $secreto,
            'activo' => true,
            'creado_en' => now(),
        ]);

        return response()->json([
            'data' => new WebhookResource($webhook),
            // Unica vez que se devuelve en texto plano -- lo necesita el
            // cliente para verificar X-PrintBridge-Signature (seccion 8.2).
            'secret' => $secreto,
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookEntregaResource;
use App\Http\Resources\WebhookResource;
use App\Models\WebhookConfigurado;
use App\Support\EventType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Webhooks')]
class WebhookController extends Controller
{
    /**
     * List webhooks.
     */
    public function index()
    {
        return WebhookResource::collection(WebhookConfigurado::orderBy('id')->get());
    }

    /**
     * Create a webhook.
     *
     * The returned `secret` is only ever shown in this response — save it
     * to verify the `X-PrintBridge-Signature` header on deliveries.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            /** Your HTTPS endpoint -- must respond within 10s or the attempt counts as failed. */
            'url' => ['required', 'url', 'max:2048'],
            /** Which event types to receive: `job.created`, `job.printing`, `job.printed`, `job.failed`, `agent.online`, `agent.offline`. */
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
            /** The HMAC-SHA256 signing secret for this webhook -- shown here once, never again. Store it now; you'll need it to verify `X-PrintBridge-Signature` on every delivery. */
            'secret' => $secreto,
        ], 201);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(int $id)
    {
        $webhook = WebhookConfigurado::findOrFail($id);
        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * List delivery attempts for a webhook.
     */
    public function entregas(int $id)
    {
        $webhook = WebhookConfigurado::findOrFail($id);

        return WebhookEntregaResource::collection(
            $webhook->entregas()->orderByDesc('id')->paginate()
        );
    }
}

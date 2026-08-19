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
     * This response only describes the webhook *configuration* you just
     * created (`data`) plus the signing `secret` — it says nothing about
     * what you'll actually receive later. When a subscribed event happens,
     * PrintBridge sends a **separate** HTTP POST to your `url`, with this
     * body:
     *
     * ```json
     * {
     *   "event_type": "job.printed",
     *   "event_id": 4821,
     *   "job_id": 1902,
     *   "agent_id": 6,
     *   "created_at": "2026-08-19T18:20:32+00:00",
     *   "payload": { "installation_id": "pos-desktop-o6gu6jt-dc8d9e41" }
     * }
     * ```
     *
     * `payload` varies by `event_type` -- for `job.*` events it's the raw
     * data the agent reported for that job event; for `agent.online`/
     * `agent.offline` it's just `{ installation_id }`.
     *
     * That request carries an `X-PrintBridge-Signature: sha256=<hex>` header
     * -- HMAC-SHA256 of the raw request body, keyed with the `secret` below.
     * Verify it before trusting the payload (see `verifyWebhookSignature` in
     * `@printbridge/sdk-js`, or the Printing Examples guide for the raw
     * HMAC computation in other languages). Delivery retries with backoff
     * (30s, 2min, 10min, 1h, 6h) up to 5 attempts if your endpoint doesn't
     * respond 2xx within 10s -- see `GET /v1/webhooks/{id}/deliveries` for
     * the attempt-by-attempt history.
     *
     * The returned `secret` (for signature verification, not for the
     * delivery body above) is only ever shown in this response.
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

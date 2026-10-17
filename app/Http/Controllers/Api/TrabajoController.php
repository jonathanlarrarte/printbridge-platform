<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrabajoResource;
use App\Models\Agente;
use App\Models\ComandoPrueba;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Support\JobStatus;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

#[Group('Jobs')]
class TrabajoController extends Controller
{
    /**
     * List print jobs.
     *
     * Filterable by agent, printer, status, and date range. This is a
     * growing log, not a bounded list -- always paginated (see `meta` in
     * the response: `current_page`, `last_page`, `total`), 25 per page by
     * default.
     */
    #[QueryParameter('page', description: 'Which page to return. Defaults to 1.', type: 'integer')]
    #[QueryParameter('per_page', description: 'Results per page, up to 100. Defaults to 25.', type: 'integer')]
    public function index(Request $request)
    {
        $datos = $request->validate([
            /** Only jobs belonging to this agent. */
            'agent_id' => ['nullable', 'integer'],
            /** Only jobs sent to this printer. */
            'printer_id' => ['nullable', 'integer'],
            /** Only jobs currently in this status. */
            'status' => ['nullable', 'string', Rule::in(JobStatus::valoresApi())],
            /** Only jobs created on or after this date/time (ISO 8601). */
            'from' => ['nullable', 'date'],
            /** Only jobs created on or before this date/time (ISO 8601). */
            'to' => ['nullable', 'date'],
        ]);

        $porPagina = min((int) $request->query('per_page', 25), 100);

        $query = TrabajoImpresion::whereHas('agente')
            ->with('agente')
            ->when($datos['agent_id'] ?? null, fn ($q, $v) => $q->where('agente_id', $v))
            ->when($datos['printer_id'] ?? null, fn ($q, $v) => $q->where('impresora_id', $v))
            ->when($datos['status'] ?? null, fn ($q, $v) => $q->where('estado', JobStatus::toInternal($v)))
            ->when($datos['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($datos['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->orderByDesc('id');

        return TrabajoResource::collection($query->paginate($porPagina));
    }

    /**
     * Get a print job.
     *
     * Includes its full event history.
     */
    public function show(int $id)
    {
        $trabajo = TrabajoImpresion::whereHas('agente')->with(['agente', 'eventos'])->findOrFail($id);

        return new TrabajoResource($trabajo);
    }

    /**
     * Send a print job.
     *
     * This goes through the agent's heartbeat (picked up within 15-30s), not
     * straight to the printer -- it's the channel for triggering a print
     * from somewhere that can't reach the POS's local network at all, like
     * a backend job or a webhook from a payment processor. If your code
     * runs *on* the POS itself, use the local print API instead (see the
     * POS Integration guide) -- it's instant and doesn't depend on this
     * platform being reachable.
     *
     * Accepts the same `format`/`data` shapes as the local print API,
     * `html` and `raw` included -- see the Printing Examples guide for a
     * full worked example of each in several languages. `target` is the
     * same printer alias the local channel uses, not a numeric ID -- the
     * body here is deliberately almost identical to a local print job, so
     * the only real difference when switching between them is the URL and
     * which token you authenticate with.
     */
    public function store(Request $request)
    {
        $empresa = $request->user();

        $datos = $request->validate([
            /** The agent to print on. Find it in `GET /v1/agents`, or copy the ID badge on that agent's card in the dashboard. */
            'agent_id' => ['required', 'integer'],
            /** The printer's alias on that agent -- the same `target` a local print job sends, not a numeric ID. */
            'target' => ['required', 'string'],
            /** `html`, `raw`, `escpos`, or `tspl`. `raw` must be base64-encoded -- see the "Common mistakes" note in the Printing Examples guide. */
            'format' => ['required', 'string', Rule::in(['html', 'raw', 'escpos', 'tspl'])],
            /** The content to print, shaped according to `format`: an HTML string (or `{html, width_px}`) for `html`, a base64 string for `raw`, or a structured object for `escpos`/`tspl`. */
            'data' => ['required'],
            /** Your own ID for this job, echoed back in `GET /v1/jobs` and in webhooks. Defaults to a generated UUID if omitted. Sending the same `external_job_id` again for this agent is treated as a retry of the same job, not a new print -- safe to do if you're not sure your first request made it through. */
            'external_job_id' => ['nullable', 'string', 'max:255'],
        ]);

        $agente = Agente::where('empresa_id', $empresa->id)->findOrFail($datos['agent_id']);
        $impresora = Impresora::where('agente_id', $agente->id)->where('alias', $datos['target'])->firstOrFail();

        if (! empty($datos['external_job_id'])) {
            $existente = ComandoPrueba::where('agente_id', $agente->id)->where('job_id_externo', $datos['external_job_id'])->first();
            if ($existente) {
                return response()->json(['data' => [
                    'external_job_id' => $existente->job_id_externo,
                    'message' => 'Already queued -- this external_job_id was already sent for this agent.',
                ]], 200);
            }
        }

        $comando = ComandoPrueba::create([
            'agente_id' => $agente->id,
            'impresora_id' => $impresora->id,
            'job_id_externo' => $datos['external_job_id'] ?? (string) Str::uuid(),
            'target' => $impresora->alias,
            'format' => $datos['format'],
            'data' => $datos['data'],
            'estado' => 'pendiente',
            'creado_en' => now(),
        ]);

        return response()->json(['data' => [
            /** Same ID you sent, or the generated one if you didn't send one -- poll `GET /v1/jobs?agent_id=...` or subscribe to `job.printed`/`job.failed` webhooks to see the outcome. */
            'external_job_id' => $comando->job_id_externo,
            'message' => 'Queued. The agent will print it on its next heartbeat (up to 30s).',
        ]], 202);
    }
}

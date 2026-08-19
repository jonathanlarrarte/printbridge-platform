---
id: pos-integration
title: POS Multi-Branch Integration
sidebar_position: 2
---

# POS Multi-Branch Integration

Technical reference guide for integrating PrintBridge into a chain with several
points of sale — the typical theme-park case: each register prints receipts on
an 80mm thermal printer and access wristbands on a TSC/TSPL printer.

## 1. Architecture of the full flow

There are two completely independent channels, and that separation is the most
important design decision for a multi-branch deployment: **printing never
depends on the internet**.

**Local channel — per register, real-time**

```
POS system at the register (your software)
  ⇄ ws://localhost:8181
  → PrintBridge Agent
  → USB / Network →
  80mm thermal printer + wristband printer
```

Runs entirely on the branch's local network. If the branch's internet goes
down, printing keeps working — the agent keeps a persistent queue on disk and
retries.

**Platform channel — centralized, every 15-30s**

```
Agent
  → outbound HTTPS →
  PrintBridge Platform
  ⇄ API v1 (Bearer token) ⇄
  Your back-office / this dashboard
```

The agent always *initiates* the connection (heartbeat, events) — no public IP
or inbound open ports are ever needed at the branch. This is what powers the
dashboard's [Agents](https://impryxa.vekronis.com/#/agentes) and
[Stats](https://impryxa.vekronis.com/#/estadisticas) pages, and what triggers
your [webhooks](https://impryxa.vekronis.com/#/webhooks).

For your integration team, the practical rule is: **each register's software
talks to ITS local agent** (channel 1) to print; **your back-office or
operations dashboard talks to the platform** (channel 2) for monitoring,
reporting, and alerts. These are two separate integrations, almost always in
different systems.

## 2. Example: one branch, two printers

Typical theme-park case — a ticket-sales register with:

- One **80mm ESC/POS** thermal printer (sales receipt), connected over the network.
- One **TSC (TSPL)** printer for access wristbands, connected over USB.

Agent configuration on that register (once, from its window):

| Alias | Type | Target | Format |
|---|---|---|---|
| `receipt` | Network | `192.168.1.50:9100` | `escpos` |
| `wristband` | USB / Local | TSC TE244 | `tspl` |

From here, the register's software prints to `receipt` or `wristband` by
logical name — it never needs to know the physical printer's IP, port, or
command language. See the
[full installation walkthrough](https://impryxa.vekronis.com/#/instalar-agente).

## 3. Local integration (POS ↔ agent)

For Vue/Laravel-specific examples of printing a plain string vs. HTML, see
[Printing Examples: Text & HTML](./printing-examples.md).

Each register's agent exposes two channels, each on two ports (both ports
configurable): plain on `8181`, and TLS on `8182` — use the secure one if
your POS itself is served over HTTPS, since browsers block plain
`ws://`/`http://` calls to localhost from an HTTPS page as mixed content.

- **WebSocket** (`ws://localhost:8181/ws` or `wss://localhost:8182/ws`) —
  recommended if your POS can hold a persistent connection: confirms
  queuing instantly and also pushes the final result (printed / failed)
  asynchronously over the same socket.
- **HTTP** (`POST /print` on either port) — simple fallback for classic
  request/response integrations that don't want to manage socket state
  (typical in existing PHP, Java, or .NET POS back-ends). Only confirms
  queuing; doesn't push the final result over this channel.

In both cases the body is the same job:
`{ id?, token, target, format, data }` — `token` is that installation's local
token (shown in the agent's window, "Token for POS integration" section — this
is different from the platform token).

### Why print to a "target" instead of an IP or driver?

The POS never talks directly to a printer — it talks to a **logical name**
(`receipt`, `wristband`, whatever you named it). That indirection is the
central design decision: your register's code doesn't need to know the
physical printer's brand, protocol, or network address, and that printer can
be swapped, moved to a different IP, or switched from USB to network **without
touching a single line of the POS** — you just reconfigure the alias in the
agent's window.

### How the agent resolves a `target`, step by step

1. **One-time configuration, per register:** in the agent's window you map
   each alias to a real physical printer — connection type (`red`/network or
   `usb`/local), `ip`+`puerto` or `nombre_sistema` depending on the type, and
   a default command format. This is saved locally as `config.printers[alias]`.
2. **The POS sends the job** over WebSocket or HTTP — both channels are just
   two ways into the same internal agent queue; there's no separate
   implementation from that point on.
3. **Alias resolution:** when processing the job, the agent looks up
   `config.printers[job.target]`. If the alias doesn't exist, the job fails
   right there (which is why the alias has to match exactly what's
   configured). From that entry it gets the physical connection and the
   default format if the job didn't send one explicitly.
4. **Command generation:** depending on the format (`escpos`, `tspl`, `html`,
   or `raw`) the agent builds the byte buffer specific to that printer from
   the job's generic `data` — real ESC/POS via a thermal-printing library, or
   raw TSPL commands (`SIZE`, `TEXT`, `BARCODE`, `PRINT`...) for TSC printers.
   The same `data` you send doesn't change even if you swap the physical
   printer behind the alias.
5. **Physical dispatch:** for `red` (network), the agent opens a direct TCP
   socket to `ip:puerto` (standard "raw" printing, typically port 9100). For
   `usb`/`local`, it goes to the native Windows spooler by the printer's
   system name.
6. **Persistent queue and retries:** every job is saved to disk before a print
   attempt. If the printer is off or jammed, the agent automatically retries
   every few seconds up to 5 times before reporting it as a final failure —
   all of this is invisible to the POS, which already got its queuing
   confirmation as soon as it sent the job.

### JavaScript (browser) — WebSocket, with the included SDK

`sdk-js/printbridge-sdk.js` in the agent repo already wraps the full protocol
(including reconnection):

```js
import { PrintBridge } from './printbridge-sdk';

const pb = new PrintBridge({ token: import.meta.env.VITE_PRINTBRIDGE_TOKEN });
await pb.conectar();

async function imprimirVentaCompleta(venta) {
  // 1. Receipt on the 80mm thermal printer
  await pb.print({
    target: 'receipt',
    format: 'escpos',
    data: {
      negocio: 'Parque Aventura',
      encabezado: ['NIT 900.123.456-7', `Caja ${venta.caja}`],
      items: venta.items,          // [{ nombre, cantidad, precio }]
      total: venta.total
    }
  });

  // 2. Wristband on the TSC (same register, other physical printer)
  await pb.print({
    target: 'wristband',
    format: 'tspl',
    data: {
      nombre: venta.cliente.nombre,
      codigo: venta.codigoAcceso,
      fecha: new Date().toLocaleDateString('es-CO')
    }
  });
}
```

### Python — WebSocket (websockets)

```python
import asyncio, json, uuid
import websockets

TOKEN = "this-register's-local-token"

async def imprimir(target, formato, data):
    async with websockets.connect("ws://localhost:8181/ws") as ws:
        job_id = str(uuid.uuid4())
        await ws.send(json.dumps({
            "id": job_id, "token": TOKEN, "target": target,
            "format": formato, "data": data
        }))
        respuesta = json.loads(await ws.recv())  # {"status":"encolado","jobId":...}
        if respuesta["status"] == "error":
            raise RuntimeError(respuesta.get("mensaje"))
        return respuesta

async def imprimir_venta(venta):
    await imprimir("receipt", "escpos", {
        "negocio": "Parque Aventura",
        "encabezado": [f"Caja {venta['caja']}"],
        "items": venta["items"],
        "total": venta["total"],
    })
    await imprimir("wristband", "tspl", {
        "nombre": venta["cliente"]["nombre"],
        "codigo": venta["codigo_acceso"],
        "fecha": venta["fecha"],
    })
```

### PHP — HTTP (fallback, no socket handling)

```php
function imprimir(string $target, string $formato, array $data): array
{
    $token = getenv('PRINTBRIDGE_TOKEN_CAJA');

    $ch = curl_init('http://localhost:8181/print');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'token' => $token, 'target' => $target,
            'format' => $formato, 'data' => $data,
        ]),
    ]);
    $respuesta = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($respuesta['error'])) {
        throw new RuntimeException($respuesta['error']);
    }
    return $respuesta; // ['status' => 'encolado', 'jobId' => '...']
}

imprimir('receipt', 'escpos', [
    'negocio' => 'Parque Aventura',
    'encabezado' => ["Caja {$venta->caja}"],
    'items' => $venta->items,
    'total' => $venta->total,
]);

imprimir('wristband', 'tspl', [
    'nombre' => $venta->cliente->nombre,
    'codigo' => $venta->codigoAcceso,
    'fecha' => date('d/m/Y'),
]);
```

### C# / .NET — HTTP (fallback)

```csharp
using System.Net.Http.Json;

public record TrabajoImpresion(string token, string target, string format, object data);

public class PrintBridgeClienteLocal
{
    private readonly HttpClient _http = new() { BaseAddress = new Uri("http://localhost:8181") };
    private readonly string _token = Environment.GetEnvironmentVariable("PRINTBRIDGE_TOKEN_CAJA")!;

    public async Task ImprimirAsync(string target, string format, object data)
    {
        var job = new TrabajoImpresion(_token, target, format, data);
        var resp = await _http.PostAsJsonAsync("/print", job);
        var body = await resp.Content.ReadFromJsonAsync<Dictionary<string, object>>();

        if (!resp.IsSuccessStatusCode)
            throw new Exception($"PrintBridge: {body?["error"]}");
    }
}

// Usage
var pb = new PrintBridgeClienteLocal();
await pb.ImprimirAsync("receipt", "escpos", new {
    negocio = "Parque Aventura",
    encabezado = new[] { $"Caja {venta.Caja}" },
    items = venta.Items,
    total = venta.Total,
});
await pb.ImprimirAsync("wristband", "tspl", new {
    nombre = venta.Cliente.Nombre,
    codigo = venta.CodigoAcceso,
    fecha = DateTime.Now.ToString("dd/MM/yyyy"),
});
```

### Java — HTTP (fallback, java.net.http)

```java
import java.net.URI;
import java.net.http.*;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.util.Map;

public class PrintBridgeLocal {
    private final HttpClient http = HttpClient.newHttpClient();
    private final ObjectMapper json = new ObjectMapper();
    private final String token = System.getenv("PRINTBRIDGE_TOKEN_CAJA");

    public void imprimir(String target, String formato, Object data) throws Exception {
        var cuerpo = json.writeValueAsString(Map.of(
            "token", token, "target", target, "format", formato, "data", data
        ));
        var request = HttpRequest.newBuilder()
            .uri(URI.create("http://localhost:8181/print"))
            .header("Content-Type", "application/json")
            .POST(HttpRequest.BodyPublishers.ofString(cuerpo))
            .build();

        var respuesta = http.send(request, HttpResponse.BodyHandlers.ofString());
        var datos = json.readValue(respuesta.body(), Map.class);
        if (datos.containsKey("error")) {
            throw new RuntimeException((String) datos.get("error"));
        }
    }
}

// Usage
PrintBridgeLocal pb = new PrintBridgeLocal();
pb.imprimir("receipt", "escpos", Map.of(
    "negocio", "Parque Aventura",
    "items", venta.getItems(),
    "total", venta.getTotal()
));
pb.imprimir("wristband", "tspl", Map.of(
    "nombre", venta.getCliente().getNombre(),
    "codigo", venta.getCodigoAcceso(),
    "fecha", java.time.LocalDate.now().toString()
));
```

## 4. Integration with the platform (API v1)

This is consumed by your **back-office** (not each register): your own
operations dashboard, a nightly reporting job, or a service that receives
alerts. Authenticate with your API key
(`Authorization: Bearer …`, generate one under
[Company → API keys](https://impryxa.vekronis.com/#/empresa)). Full reference
in the **[API Reference](/developers/reference/printbridge)**.

### Check the status of every branch

```js
// JavaScript (fetch)
const resp = await fetch('https://impryxa.vekronis.com/v1/agents', {
  headers: { Authorization: `Bearer ${API_KEY}` }
});
const { data: agents } = await resp.json();
agents.forEach(a =>
  console.log(a.display_name, a.status)
);
```

```python
# Python (requests)
import requests

resp = requests.get(
    "https://impryxa.vekronis.com/v1/agents",
    headers={"Authorization": f"Bearer {API_KEY}"},
)
for a in resp.json()["data"]:
    print(a["display_name"], a["status"])
```

```php
// PHP (curl)
$ch = curl_init("https://impryxa.vekronis.com/v1/agents");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey"],
]);
$agents = json_decode(curl_exec($ch), true)['data'];
foreach ($agents as $a) {
    echo "{$a['display_name']}: {$a['status']}\n";
}
```

```csharp
// C# (.NET, HttpClient)
var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new("Bearer", apiKey);

var resp = await http.GetFromJsonAsync
    <AgentsResponse>("https://impryxa.vekronis.com/v1/agents");
foreach (var a in resp.Data)
    Console.WriteLine($"{a.DisplayName}: {a.Status}");
```

### Aggregated stats for the whole chain

```
curl https://impryxa.vekronis.com/v1/stats/summary \
  -H "Authorization: Bearer $API_KEY"

# {"success_rate_by_printer":[...], "uptime_by_agent":[...],
#  "error_distribution":[...], "volume_by_hour":[...], ...}
```

### Automatic alerts (webhooks) — e.g. notify when a register goes down

Subscribe to `agent.offline` and `job.failed` from the dashboard's
[Webhooks](https://impryxa.vekronis.com/#/webhooks) page, and verify the
signature on your receiver:

```js
// Node/Express
app.post('/webhooks/printbridge',
  express.raw({ type: 'application/json' }),
  async (req, res) => {
    const firma = req.header('X-PrintBridge-Signature');
    const esperada = 'sha256=' + crypto
      .createHmac('sha256', SECRETO)
      .update(req.body).digest('hex');

    if (firma !== esperada) return res.sendStatus(401);

    const event = JSON.parse(req.body);
    if (event.event_type === 'agent.offline') {
      avisarPorSlack(`Register down: ${event.payload.installation_id}`);
    }
    res.sendStatus(200);
  });
```

```python
# Flask
@app.post("/webhooks/printbridge")
def webhook():
    firma = request.headers.get("X-PrintBridge-Signature", "")
    esperada = "sha256=" + hmac.new(
        SECRETO.encode(), request.data, hashlib.sha256
    ).hexdigest()

    if not hmac.compare_digest(firma, esperada):
        return "", 401

    event = request.get_json()
    if event["event_type"] == "agent.offline":
        avisar_por_slack(
            f"Down: {event['payload']['installation_id']}"
        )
    return "", 200
```

The platform's JS SDK (`sdk-js/` in the platform repo) already has
`verifyWebhookSignature()` implemented for you.

## 5. How each agent gets linked

Every register at every branch of your company uses **the same client
code** — isolation on the platform is per-company, not per-branch. You'll
find yours in the dashboard under
[Company](https://impryxa.vekronis.com/#/empresa).

1. Install the same `.exe` on every register at every branch.
2. In the agent's window, paste the platform URL + this client code, once per register.
3. The agent self-registers (`POST /agent/register`) and shows up in the dashboard's
   [Agents](https://impryxa.vekronis.com/#/agentes) page.

See the [full step-by-step walkthrough](https://impryxa.vekronis.com/#/instalar-agente)
(requirements, installation, troubleshooting) — it shows your actual client code
ready to copy.

## 6. Scaling to 10 branches

### Recommended naming

`display_name` is the only human-facing field per agent — use it to identify
branch + register at a glance, e.g. `"North Branch — Register 3"` or `"NB-03"`
if you prefer a short code. It's set at registration time (or your IT team can
set it via the silent installer).

### Unattended deployment

The NSIS installer supports silent mode — useful for a rollout of 10+
registers via your fleet management tooling (SCCM, Intune, or a script):

```
PrintBridge-Setup-1.0.0.exe /S
```

The platform URL and client code still need to be filled in once per register
from the agent's window (there's currently no way to preload them via the
installer — if your rollout is large, let us know and we'll prioritize it).

### Centralized monitoring across all 10

- [Agents](https://impryxa.vekronis.com/#/agentes) — online/offline status and
  last print for every printer, across all 10 branches together.
- [Stats](https://impryxa.vekronis.com/#/estadisticas) — success rate and
  aggregated volume for the whole chain.
- Webhook on `agent.offline` — automatic alert if an entire branch loses
  connection (heartbeat overdue >60s).

### An honest limitation of the current model

There's no native "branch" concept in the data today — only
`company → agents → printers`. For 10 locations, branch is identified by
naming convention (`display_name`) and by filtering `/v1/jobs` on
`agent_id`. This works well until you need, for example, different
permissions per branch — at that point it makes sense to add a real
`branches` entity grouping agents. Let us know if that's your case.

## 7. Checklist and troubleshooting

- Every register needs **outbound HTTPS** to the platform URL (not inbound —
  check the park's corporate proxy/firewall, not the branch's own).
- The local channel (`localhost:8181`) is *local to that machine*: if your POS
  runs on a different machine on the same network, it won't reach it — one
  agent per physical register.
- The local channel's `token` (per register) and your platform API key (per
  company) are different things — they don't mix.
- If a printer never reports `status: online`, check the alias — it has to
  match exactly between what's configured on the agent and the `target` your
  POS sends.
- For quick debugging without touching code: `GET http://localhost:8181/health`
  on any register confirms the agent is alive.

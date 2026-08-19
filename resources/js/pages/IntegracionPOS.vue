<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const empresa = ref(null);
const baseUrl = window.location.origin;

onMounted(async () => {
  try {
    empresa.value = (await api.empresa()).data;
  } catch {
    // la pagina funciona igual sin el codigo personalizado
  }
});

const codigo = () => empresa.value?.code || 'TU_CODIGO_DE_EMPRESA';

const SECCIONES = [
  ['arquitectura', '1. Arquitectura del flujo completo'],
  ['sucursal', '2. Ejemplo: una sucursal, dos impresoras'],
  ['nivel-local', '3. Integración local (POS ↔ agente)'],
  ['nivel-plataforma', '4. Integración con la plataforma (API v1)'],
  ['vinculacion', '5. Cómo se vincula cada agente'],
  ['diez-sucursales', '6. Escalando a 10 sucursales'],
  ['checklist', '7. Checklist y troubleshooting'],
];
</script>

<template>
  <div class="max-w-4xl">
    <h1 class="mb-1 text-lg font-semibold">Integración para POS multi-sucursal</h1>
    <p class="mb-6 text-sm text-slate-500">
      Guía técnica de referencia para integrar PrintBridge en una cadena con varios puntos de
      venta — el caso típico de un parque de diversiones: cada caja imprime recibos en una
      térmica de 80mm y brazaletes de acceso en una impresora TSC/TSPL.
    </p>

    <nav class="mb-8 rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
      <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Contenido</p>
      <ul class="grid gap-1 sm:grid-cols-2">
        <li v-for="[id, titulo] in SECCIONES" :key="id">
          <a :href="`#${id}`" class="text-slate-600 hover:text-slate-900 hover:underline">{{ titulo }}</a>
        </li>
      </ul>
    </nav>

    <!-- 1. ARQUITECTURA -->
    <section id="arquitectura" class="mb-8 scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-base font-semibold text-slate-800">1. Arquitectura del flujo completo</h2>
      <p class="mb-4 text-sm text-slate-600">
        Hay dos canales completamente independientes, y esa separación es la decisión de diseño
        más importante para un despliegue multi-sucursal: <strong>imprimir nunca depende de
        internet</strong>.
      </p>

      <div class="mb-4 space-y-3">
        <div class="rounded-lg border border-slate-200 p-3">
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Canal local — por caja, en tiempo real</p>
          <p class="font-mono text-xs text-slate-700">
            Sistema POS de la caja <span class="text-slate-400">(tu software)</span>
            &nbsp;⇄ ws://localhost:8181&nbsp;
            <span class="text-slate-400">→</span>
            &nbsp;Agente PrintBridge&nbsp;
            <span class="text-slate-400">→ USB / Red →</span>
            &nbsp;Impresora térmica 80mm + Impresora de brazaletes
          </p>
          <p class="mt-2 text-xs text-slate-500">
            Corre 100% en la red local de la sucursal. Si se cae el internet del local, se sigue
            imprimiendo — el agente guarda una cola persistente en disco y reintenta.
          </p>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Canal plataforma — centralizado, cada 15-30s</p>
          <p class="font-mono text-xs text-slate-700">
            Agente&nbsp;
            <span class="text-slate-400">→ HTTPS saliente →</span>
            &nbsp;PrintBridge Platform&nbsp;
            <span class="text-slate-400">⇄ API v1 (Bearer token) ⇄</span>
            &nbsp;Tu back-office / este dashboard
          </p>
          <p class="mt-2 text-xs text-slate-500">
            El agente <em>inicia</em> siempre la conexión (heartbeat, eventos) — nunca hace falta
            IP pública ni abrir puertos entrantes en la sucursal. Esto es lo que ves en
            <router-link :to="{ name: 'agentes' }" class="underline">Agentes</router-link>,
            <router-link :to="{ name: 'estadisticas' }" class="underline">Estadísticas</router-link>
            y lo que dispara tus <router-link :to="{ name: 'webhooks' }" class="underline">webhooks</router-link>.
          </p>
        </div>
      </div>

      <p class="text-sm text-slate-600">
        Para tu equipo de integración, la regla práctica es: <strong>el software de cada caja
        habla con SU agente local</strong> (canal 1) para imprimir; <strong>tu back-office o
        panel de operaciones habla con la plataforma</strong> (canal 2) para monitoreo, reportes
        y alertas. Son dos integraciones distintas, casi siempre en sistemas distintos.
      </p>
    </section>

    <!-- 2. EJEMPLO DE SUCURSAL -->
    <section id="sucursal" class="mb-8 scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-base font-semibold text-slate-800">2. Ejemplo: una sucursal, dos impresoras</h2>
      <p class="mb-3 text-sm text-slate-600">
        Caso típico de parque de diversiones — una caja de venta de entradas con:
      </p>
      <ul class="mb-4 list-inside list-disc space-y-1 text-sm text-slate-600">
        <li>Una impresora térmica <strong>ESC/POS de 80mm</strong> (recibo de venta), conectada por red.</li>
        <li>Una impresora <strong>TSC (TSPL)</strong> de brazaletes/pulseras de acceso, conectada por USB.</li>
      </ul>

      <p class="mb-2 text-sm text-slate-600">Configuración del agente en esa caja (una sola vez, desde su ventana):</p>
      <table class="mb-4 w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
          <tr><th class="py-1 pr-4">Alias</th><th class="py-1 pr-4">Tipo</th><th class="py-1 pr-4">Destino</th><th class="py-1">Formato</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono text-xs">
          <tr><td class="py-2 pr-4">receipt</td><td class="py-2 pr-4">Red</td><td class="py-2 pr-4">192.168.1.50:9100</td><td class="py-2">escpos</td></tr>
          <tr><td class="py-2 pr-4">wristband</td><td class="py-2 pr-4">USB / Local</td><td class="py-2 pr-4">TSC TE244</td><td class="py-2">tspl</td></tr>
        </tbody>
      </table>
      <p class="text-sm text-slate-600">
        A partir de acá, el software de la caja imprime a <code class="rounded bg-slate-100 px-1">receipt</code>
        o a <code class="rounded bg-slate-100 px-1">wristband</code> por su nombre lógico — nunca necesita
        saber la IP, el puerto ni el lenguaje de comandos de la impresora física. Ver el
        <router-link :to="{ name: 'instalar-agente' }" class="underline">paso a paso completo de instalación</router-link>.
      </p>
    </section>

    <!-- 3. NIVEL LOCAL -->
    <section id="nivel-local" class="mb-8 scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-base font-semibold text-slate-800">3. Integración local (POS ↔ agente)</h2>
      <p class="mb-2 text-sm text-slate-600">
        El agente de cada caja expone dos canales en <code class="rounded bg-slate-100 px-1">localhost:8181</code>
        (puerto configurable):
      </p>
      <ul class="mb-4 list-inside list-disc space-y-1 text-sm text-slate-600">
        <li><strong>WebSocket</strong> (<code class="rounded bg-slate-100 px-1">ws://localhost:8181/ws</code>) — recomendado
          si tu POS puede mantener una conexión persistente: confirma el encolado al instante y además
          empuja el resultado final (impreso / falló) de forma asíncrona por el mismo socket.</li>
        <li><strong>HTTP</strong> (<code class="rounded bg-slate-100 px-1">POST /print</code>) — fallback simple para
          integraciones clásicas request/response que no quieren manejar estado de socket (típico en
          back-ends PHP, Java o .NET de sistemas POS ya existentes). Solo confirma el encolado; no
          empuja el resultado final por esa vía.</li>
      </ul>
      <p class="mb-4 text-sm text-slate-600">
        En ambos casos el cuerpo es el mismo job:
        <code class="rounded bg-slate-100 px-1">{ id?, token, target, format, data }</code> — <code class="rounded bg-slate-100 px-1">token</code>
        es el token local de esa instalación (se muestra en la ventana del agente, sección "Token
        para la integración del POS" — es distinto del token de la plataforma).
      </p>

      <h3 class="mb-2 mt-6 text-sm font-semibold text-slate-700">JavaScript (navegador) — WebSocket, con el SDK incluido</h3>
      <p class="mb-2 text-sm text-slate-600">
        <code class="rounded bg-slate-100 px-1">sdk-js/printbridge-sdk.js</code> en el repo del agente
        ya envuelve el protocolo completo (reconexión incluida):
      </p>
      <pre class="mb-6 overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">import { PrintBridge } from './printbridge-sdk';

const pb = new PrintBridge({ token: import.meta.env.VITE_PRINTBRIDGE_TOKEN });
await pb.conectar();

async function imprimirVentaCompleta(venta) {
  // 1. Recibo en la termica de 80mm
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

  // 2. Brazalete en la TSC (misma caja, otra impresora fisica)
  await pb.print({
    target: 'wristband',
    format: 'tspl',
    data: {
      nombre: venta.cliente.nombre,
      codigo: venta.codigoAcceso,
      fecha: new Date().toLocaleDateString('es-CO')
    }
  });
}</pre>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">Python — WebSocket (websockets)</h3>
      <pre class="mb-6 overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">import asyncio, json, uuid
import websockets

TOKEN = "el-token-local-de-esta-caja"

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
    })</pre>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">PHP — HTTP (fallback, sin manejar sockets)</h3>
      <pre class="mb-6 overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">function imprimir(string $target, string $formato, array $data): array
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
]);</pre>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">C# / .NET — HTTP (fallback)</h3>
      <pre class="mb-6 overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">using System.Net.Http.Json;

public record TrabajoImpresion(string token, string target, string format, object data);

public class PrintBridgeClienteLocal
{
    private readonly HttpClient _http = new() { BaseAddress = new Uri("http://localhost:8181") };
    private readonly string _token = Environment.GetEnvironmentVariable("PRINTBRIDGE_TOKEN_CAJA")!;

    public async Task ImprimirAsync(string target, string format, object data)
    {
        var job = new TrabajoImpresion(_token, target, format, data);
        var resp = await _http.PostAsJsonAsync("/print", job);
        var body = await resp.Content.ReadFromJsonAsync&lt;Dictionary&lt;string, object&gt;&gt;();

        if (!resp.IsSuccessStatusCode)
            throw new Exception($"PrintBridge: {body?["error"]}");
    }
}

// Uso
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
});</pre>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">Java — HTTP (fallback, java.net.http)</h3>
      <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">import java.net.URI;
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

// Uso
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
));</pre>
    </section>

    <!-- 4. NIVEL PLATAFORMA -->
    <section id="nivel-plataforma" class="mb-8 scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-base font-semibold text-slate-800">4. Integración con la plataforma (API v1)</h2>
      <p class="mb-4 text-sm text-slate-600">
        Esto lo consume tu <strong>back-office</strong> (no cada caja): un dashboard de operaciones
        propio, un job nocturno de reportes, o un servicio que reciba alertas. Autenticación con tu
        API key (<code class="rounded bg-slate-100 px-1">Authorization: Bearer …</code>, generala en
        <router-link :to="{ name: 'empresa' }" class="underline">Empresa → API keys</router-link>).
        Referencia completa en <a :href="`${baseUrl}/docs/api`" target="_blank" rel="noopener" class="underline">/docs/api</a>.
      </p>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">Consultar el estado de todas las sucursales</h3>
      <div class="mb-6 grid gap-3 sm:grid-cols-2">
        <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">// JavaScript (fetch)
const resp = await fetch('{{ baseUrl }}/v1/agents', {
  headers: { Authorization: `Bearer ${API_KEY}` }
});
const { data: agents } = await resp.json();
agents.forEach(a =>
  console.log(a.display_name, a.status)
);</pre>
        <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100"># Python (requests)
import requests

resp = requests.get(
    "{{ baseUrl }}/v1/agents",
    headers={"Authorization": f"Bearer {API_KEY}"},
)
for a in resp.json()["data"]:
    print(a["display_name"], a["status"])</pre>
        <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">// PHP (curl)
$ch = curl_init("{{ baseUrl }}/v1/agents");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey"],
]);
$agents = json_decode(curl_exec($ch), true)['data'];
foreach ($agents as $a) {
    echo "{$a['display_name']}: {$a['status']}\n";
}</pre>
        <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">// C# (.NET, HttpClient)
var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new("Bearer", apiKey);

var resp = await http.GetFromJsonAsync
    &lt;AgentsResponse&gt;("{{ baseUrl }}/v1/agents");
foreach (var a in resp.Data)
    Console.WriteLine($"{a.DisplayName}: {a.Status}");</pre>
      </div>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">Estadísticas agregadas de la cadena</h3>
      <pre class="mb-6 overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">curl {{ baseUrl }}/v1/stats/summary \
  -H "Authorization: Bearer $API_KEY"

# {"success_rate_by_printer":[...], "uptime_by_agent":[...],
#  "error_distribution":[...], "volume_by_hour":[...], ...}</pre>

      <h3 class="mb-2 text-sm font-semibold text-slate-700">Alertas automáticas (webhooks) — ej. avisar si una caja se cae</h3>
      <p class="mb-3 text-sm text-slate-600">
        Suscribite a <code class="rounded bg-slate-100 px-1">agent.offline</code> y
        <code class="rounded bg-slate-100 px-1">job.failed</code> desde
        <router-link :to="{ name: 'webhooks' }" class="underline">Webhooks</router-link>, y verificá
        la firma en tu receptor:
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">// Node/Express
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
      avisarPorSlack(`Caja caida: ${event.payload.installation_id}`);
    }
    res.sendStatus(200);
  });</pre>
        <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100"># Flask
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
            f"Caida: {event['payload']['installation_id']}"
        )
    return "", 200</pre>
      </div>
      <p class="mt-3 text-sm text-slate-600">
        El SDK JS de la plataforma (<code class="rounded bg-slate-100 px-1">sdk-js/</code> en este
        repo) ya trae <code class="rounded bg-slate-100 px-1">verifyWebhookSignature()</code> resuelto.
      </p>
    </section>

    <!-- 5. VINCULACION -->
    <section id="vinculacion" class="mb-8 scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-base font-semibold text-slate-800">5. Cómo se vincula cada agente</h2>
      <p class="mb-3 text-sm text-slate-600">
        Todas las cajas de todas las sucursales de tu empresa usan <strong>el mismo código de
        cliente</strong> — el aislamiento en la plataforma es por empresa, no por sucursal:
      </p>
      <div v-if="empresa" class="mb-4 flex items-center gap-2">
        <code class="flex-1 rounded-md bg-slate-100 px-3 py-2 text-sm">{{ empresa.code }}</code>
      </div>
      <ol class="mb-4 list-inside list-decimal space-y-1 text-sm text-slate-600">
        <li>Instalás el mismo <code class="rounded bg-slate-100 px-1">.exe</code> en cada caja de cada sucursal.</li>
        <li>En la ventana del agente, pegás la URL de la plataforma + este código de cliente, una vez por caja.</li>
        <li>El agente se autoregistra (<code class="rounded bg-slate-100 px-1">POST /agent/register</code>) y aparece en <router-link :to="{ name: 'agentes' }" class="underline">Agentes</router-link>.</li>
      </ol>
      <p class="text-sm text-slate-600">
        Ver el <router-link :to="{ name: 'instalar-agente' }" class="font-medium underline">paso a paso completo</router-link>
        (requisitos, instalación, troubleshooting).
      </p>
    </section>

    <!-- 6. DIEZ SUCURSALES -->
    <section id="diez-sucursales" class="mb-8 scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-base font-semibold text-slate-800">6. Escalando a 10 sucursales</h2>

      <h3 class="mb-1 text-sm font-semibold text-slate-700">Nomenclatura recomendada</h3>
      <p class="mb-3 text-sm text-slate-600">
        <code class="rounded bg-slate-100 px-1">display_name</code> es el único campo humano
        de cada agente — usalo para identificar sucursal + caja de un vistazo, ej.
        <code class="rounded bg-slate-100 px-1">"Sucursal Norte — Caja 3"</code> o
        <code class="rounded bg-slate-100 px-1">"SN-03"</code> si preferís un código corto. Se
        setea al registrar (o lo puede fijar tu equipo de IT en el instalador silencioso).
      </p>

      <h3 class="mb-1 text-sm font-semibold text-slate-700">Despliegue desatendido</h3>
      <p class="mb-3 text-sm text-slate-600">
        El instalador NSIS soporta modo silencioso — útil para un rollout de 10+ cajas vía tu
        herramienta de gestión de parque de equipos (SCCM, Intune, o un script):
      </p>
      <pre class="mb-4 overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">PrintBridge-Setup-1.0.0.exe /S</pre>
      <p class="mb-4 text-sm text-slate-600">
        La URL de plataforma y el código de cliente todavía hay que completarlos una vez por caja
        desde la ventana del agente (no hay hoy una forma de precargarlos vía instalador — si tu
        rollout es grande, avisanos y lo priorizamos).
      </p>

      <h3 class="mb-1 text-sm font-semibold text-slate-700">Monitoreo centralizado de las 10</h3>
      <ul class="mb-4 list-inside list-disc space-y-1 text-sm text-slate-600">
        <li><router-link :to="{ name: 'agentes' }" class="underline">Agentes</router-link> — estado online/offline y última impresión de cada impresora, de las 10 sucursales juntas.</li>
        <li><router-link :to="{ name: 'estadisticas' }" class="underline">Estadísticas</router-link> — tasa de éxito y volumen agregado de toda la cadena.</li>
        <li>Webhook a <code class="rounded bg-slate-100 px-1">agent.offline</code> — alerta automática si una sucursal entera pierde conexión (heartbeat vencido &gt;60s).</li>
      </ul>

      <h3 class="mb-1 text-sm font-semibold text-slate-700">Límite honesto del modelo actual</h3>
      <p class="text-sm text-slate-600">
        Hoy no existe un concepto nativo de "sucursal" en los datos — solo
        <code class="rounded bg-slate-100 px-1">empresa → agentes → impresoras</code>. Para 10
        locales, la sucursal se identifica por convención de nombres
        (<code class="rounded bg-slate-100 px-1">display_name</code>) y filtrando por
        <code class="rounded bg-slate-100 px-1">agent_id</code> en <code class="rounded bg-slate-100 px-1">/v1/jobs</code>.
        Funciona bien hasta que necesites, por ejemplo, permisos distintos por sucursal — en ese
        punto tiene sentido agregar una entidad <code class="rounded bg-slate-100 px-1">sucursales</code> real
        agrupando agentes. Contanos si es tu caso.
      </p>
    </section>

    <!-- 7. CHECKLIST -->
    <section id="checklist" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
      <h2 class="mb-3 text-base font-semibold text-amber-900">7. Checklist y troubleshooting</h2>
      <ul class="list-inside list-disc space-y-1 text-sm text-amber-800">
        <li>Cada caja necesita salida <strong>HTTPS saliente</strong> hacia la URL de la plataforma (no entrante — revisá proxy/firewall corporativo del parque, no del local).</li>
        <li>El canal local (<code class="rounded bg-amber-100 px-1">localhost:8181</code>) es <em>local a esa máquina</em>: si tu POS corre en otro equipo de la misma red, no le va a llegar — el agente va uno por caja física.</li>
        <li>El <code class="rounded bg-amber-100 px-1">token</code> del canal local (por caja) y tu API key de plataforma (por empresa) son cosas distintas — no se mezclan.</li>
        <li>Si una impresora nunca reporta <code class="rounded bg-amber-100 px-1">status: online</code>, revisá el alias — tiene que coincidir exacto entre lo configurado en el agente y el <code class="rounded bg-amber-100 px-1">target</code> que manda tu POS.</li>
        <li>Para debug rápido sin tocar código: <code class="rounded bg-amber-100 px-1">GET http://localhost:8181/health</code> en cualquier caja confirma que el agente está vivo.</li>
      </ul>
    </section>
  </div>
</template>

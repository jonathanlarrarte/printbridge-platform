---
id: printing-examples
title: Printing Examples (Text & HTML)
sidebar_position: 3
---

# Printing Examples: Text & HTML

Complete, working examples for sending a print job from a plain string and
from HTML, in Vue, Laravel, and plain PHP.

:::info Where these examples run
Every example here talks to the **local agent** on the register
(`ws://localhost:8181/ws` or `http://localhost:8181/print`) — not the
platform API (`/v1/*`). See [POS Multi-Branch Integration](./pos-integration.md#1-architecture-of-the-full-flow)
for why that split exists. This matters for the Laravel example below —
read the note there before copying it as-is.
:::

## What's a "job"?

Every time you ask the agent to print something, it doesn't print
immediately — it wraps your request into a **job** and pushes it onto a
local queue (persisted to disk, so it survives an app restart) before
touching the printer at all. This is what makes the whole thing reliable:
if the printer is off, jammed, or busy, your call still returns
instantly, and the agent retries the job in the background — up to 5
times — without you writing any retry logic yourself.

A job is just the object you send: `{ id?, token, target, format, data }`,
plus internal bookkeeping the agent adds (attempt count, timestamp,
status). `id` is optional — if you don't send one, the agent generates a
UUID and hands it back to you, which is how you correlate an async
result to the request that caused it.

**Job lifecycle** — these are the exact status strings you'll see, in
order:

| Status | When | Where you see it |
|---|---|---|
| `encolado` | Synchronously, the instant the agent accepts your request | The direct response (WS ack or HTTP response body) |
| `en_cola` | Same moment, pushed as an event | WebSocket only |
| `imprimiendo` | The queue picked up the job and is actively sending it to the printer | WebSocket only |
| `impreso` | Printed successfully | WebSocket only |
| `fallo_definitivo` | Failed on all 5 attempts | WebSocket only |

The **HTTP endpoint only ever gives you `encolado`** — it's fire-and-forget
by design (see [POS Integration §3](./pos-integration.md#3-local-integration-pos--agent)).
If you need to know whether a job actually printed or failed, use the
WebSocket connection instead, which pushes every subsequent status change
for as long as it stays open.

## Expected responses, exactly

**WebSocket — immediate ack**, sent the instant your message is received
(before the job has actually printed):

```json
{ "status": "encolado", "jobId": "3fa85f64-5717-4562-b3fc-2c963f66afa6" }
```

**WebSocket — lifecycle pushes**, sent later, asynchronously, as the job
moves through the queue (same `jobId` each time so you can match them up):

```json
{ "status": "en_cola", "jobId": "3fa85f64-..." }
{ "status": "imprimiendo", "jobId": "3fa85f64-..." }
{ "status": "impreso", "jobId": "3fa85f64-...", "detalle": { "enviado": true } }
```

Or, if every retry fails:

```json
{ "status": "fallo_definitivo", "jobId": "3fa85f64-...", "detalle": "Timeout conectando a 192.168.1.50:9100" }
```

**WebSocket — errors** (note the field is `mensaje`, not `error`):

```json
{ "status": "error", "mensaje": "JSON invalido" }
{ "status": "error", "jobId": "3fa85f64-...", "mensaje": "token invalido" }
```

**HTTP `POST /print` — success** (the only response you get; nothing further is pushed):

```json
{ "status": "encolado", "jobId": "3fa85f64-5717-4562-b3fc-2c963f66afa6" }
```

**HTTP `POST /print` — error** (HTTP 401, and note the field here is `error`, not `mensaje` — genuinely inconsistent with the WebSocket error shape above, so don't reuse the same error-parsing code for both channels):

```json
{ "error": "token invalido" }
```

## The three ways to print

| `format` | `data` is... | Best for |
|---|---|---|
| `raw` | A **base64-encoded** string, decoded and sent to the printer byte-for-byte | You already have exact printer command bytes (or plain text you've encoded yourself) |
| `escpos` | A structured object (`negocio`, `items`, `total`, ...) | Simple receipts, fast, crisp native text |
| `html` | An HTML string, rendered and printed as an image | Rich layouts, logos, custom fonts — see [POS Integration §3](./pos-integration.md#why-print-to-a-target-instead-of-an-ip-or-driver) for how this actually works under the hood |

All three go through the same job shape: `{ id?, token, target, format, data }`.

## 1. Plain string (`format: "raw"`)

The printer receives exactly the bytes you send, no formatting applied —
useful when you already have raw ESC/POS command bytes, or just want to
push plain lines of text.

:::danger `data` must be base64, not literal text
The agent runs `data` straight through `Buffer.from(data, 'base64')` before
sending it to the printer. Node's base64 decoder doesn't validate its
input — it silently skips characters that aren't valid base64 and decodes
whatever's left, so **sending literal text directly produces garbled bytes
on the printer with no error anywhere in the chain.** Always base64-encode
first.
:::

```js
// Vue / JavaScript — encode before sending
const texto = 'PARQUE AVENTURA\nEntrada general x2      $45.000\nTOTAL          $45.000\n\n\n';
const dataBase64 = btoa(String.fromCharCode(...new TextEncoder().encode(texto)));

{
  target: 'receipt',
  format: 'raw',
  data: dataBase64
}
```

```php
// PHP — same job, as an HTTP POST
$texto = "PARQUE AVENTURA\nEntrada general x2      \$45.000\nTOTAL          \$45.000\n\n\n";

$job = [
    'token' => $token,
    'target' => 'receipt',
    'format' => 'raw',
    'data' => base64_encode($texto), // sin esto, la impresora recibe basura
];
```

## 2. Structured text (`format: "escpos"`)

Native ESC/POS text — the printer's own fonts, so it's crisp and fast. Use
this for standard receipts where you don't need custom layout.

```json
{
  "target": "receipt",
  "format": "escpos",
  "data": {
    "negocio": "Parque Aventura",
    "encabezado": ["NIT 900.123.456-7", "Caja 3"],
    "items": [
      { "nombre": "Entrada general", "cantidad": 2, "precio": 45000 },
      { "nombre": "Brazalete acceso VIP", "cantidad": 1, "precio": 30000 }
    ],
    "total": 120000,
    "moneda": "$",
    "pie": ["Gracias por tu visita"]
  }
}
```

## 3. HTML (`format: "html"`)

Full CSS layout, rendered and sent as an image. See
[POS Integration §3](./pos-integration.md#why-print-to-a-target-instead-of-an-ip-or-driver)
for exactly how this pipeline works, and
[`recibo-html-ejemplo.html`](https://github.com/jonathanlarrarte/printer-agent/blob/main/main/printers/ejemplos/recibo-html-ejemplo.html)
in the agent repo for a full print-safe template (large bold fonts, correct
pixel width — small/thin text looks blurry once rasterized to 1-bit).

```json
{
  "target": "receipt",
  "format": "html",
  "data": {
    "html": "<html><body style='width:576px;font-family:Arial;font-weight:bold;font-size:28px'><h1>Parque Aventura</h1><p>Entrada general x2 — $45.000</p></body></html>",
    "ancho_px": 576
  }
}
```

---

## Vue.js

A self-contained composable using the browser's native `WebSocket` —
handles connecting, reconnecting, and sending both formats above. Runs in
your POS's UI on the register itself (a browser window, an Electron
renderer, whatever your POS front-end is).

```vue
<!-- composables/usePrintBridge.js -->
<script>
import { ref, onUnmounted } from 'vue';

export function usePrintBridge(token) {
  const conectado = ref(false);
  let ws = null;
  let reintentoTimer = null;

  function conectar() {
    ws = new WebSocket('ws://localhost:8181/ws');

    ws.onopen = () => { conectado.value = true; };
    ws.onclose = () => {
      conectado.value = false;
      // El agente puede reiniciarse (actualizacion, reboot) -- reconectar
      // en vez de dejar la UI del POS sin poder imprimir hasta que alguien
      // recargue la pagina a mano.
      reintentoTimer = setTimeout(conectar, 3000);
    };
    ws.onerror = () => ws.close();
  }

  // Resuelve cuando el job llega a un estado TERMINAL (impreso / fallo
  // definitivo / error) -- no en el primer mensaje que llega, que es solo
  // el ack de "encolado" (ver la seccion "Expected responses" mas arriba:
  // el WS empuja varios mensajes para el mismo jobId a medida que el job
  // avanza por la cola: encolado -> en_cola -> imprimiendo -> impreso).
  function print({ target, format, data }) {
    return new Promise((resolve, reject) => {
      if (!conectado.value) return reject(new Error('Agente no conectado'));

      const id = crypto.randomUUID();
      const onMessage = (evento) => {
        const respuesta = JSON.parse(evento.data);
        if (respuesta.jobId !== id) return; // otro job en vuelo, no es el nuestro

        if (respuesta.status === 'impreso') {
          ws.removeEventListener('message', onMessage);
          resolve(respuesta);
        } else if (respuesta.status === 'fallo_definitivo' || respuesta.status === 'error') {
          ws.removeEventListener('message', onMessage);
          reject(new Error(respuesta.mensaje || respuesta.detalle || 'Fallo al imprimir'));
        }
        // encolado / en_cola / imprimiendo: solo progreso, seguimos esperando
      };
      ws.addEventListener('message', onMessage);
      ws.send(JSON.stringify({ id, token, target, format, data }));
    });
  }

  conectar();
  onUnmounted(() => {
    clearTimeout(reintentoTimer);
    ws?.close();
  });

  return { conectado, print };
}
</script>
```

```vue
<!-- Uso en un componente -->
<script setup>
import { usePrintBridge } from './composables/usePrintBridge';

const { conectado, print } = usePrintBridge(import.meta.env.VITE_PRINTBRIDGE_TOKEN);

function aBase64(texto) {
  // format:'raw' decodifica `data` como base64 -- mandar texto literal sin
  // esto produce bytes corruptos en la impresora, sin ningun error.
  return btoa(String.fromCharCode(...new TextEncoder().encode(texto)));
}

async function imprimirTextoPlano() {
  await print({
    target: 'receipt',
    format: 'raw',
    data: aBase64('PARQUE AVENTURA\nEntrada general x2   $45.000\n\n\n'),
  });
}

async function imprimirHtml(venta) {
  const html = `
    <html><body style="width:576px;font-family:Arial;font-weight:bold;font-size:28px">
      <h1 style="text-align:center">Parque Aventura</h1>
      <p>Caja ${venta.caja} — ${new Date().toLocaleString()}</p>
      <hr>
      ${venta.items.map((i) => `<p>${i.cantidad}x ${i.nombre} — $${i.precio}</p>`).join('')}
      <h2>TOTAL: $${venta.total}</h2>
    </body></html>
  `;
  await print({ target: 'receipt', format: 'html', data: { html, ancho_px: 576 } });
}
</script>

<template>
  <button :disabled="!conectado" @click="imprimirTextoPlano">Imprimir recibo simple</button>
  <button :disabled="!conectado" @click="imprimirHtml(venta)">Imprimir recibo con diseño</button>
</template>
```

## Laravel

:::caution Only if Laravel runs on the register itself
`localhost:8181` is local to whichever machine runs the agent. A
centralized/cloud Laravel backend **cannot** reach a register's local
agent this way — only code running physically on that register can. This
example is for a local companion app (e.g. a Laravel app running on the
same Windows machine as a kiosk), not your main backend. If you need a
centralized system to trigger something on a specific register, that's
what the [platform API](/developers/reference/printbridge) and
[webhooks](./getting-started.md#5-webhooks) are for instead.
:::

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PrintBridgeLocal
{
    public function __construct(
        private string $token = '',
    ) {
        $this->token = $token ?: config('services.printbridge.token');
    }

    public function imprimirTexto(string $target, string $texto): array
    {
        // format:'raw' decodifica `data` como base64 -- mandar texto
        // literal sin esto produce bytes corruptos en la impresora, sin
        // ningun error en ningun lado de la cadena.
        return $this->imprimir($target, 'raw', base64_encode($texto));
    }

    public function imprimirRecibo(string $target, array $datos): array
    {
        return $this->imprimir($target, 'escpos', $datos);
    }

    public function imprimirHtml(string $target, string $html, int $anchoPx = 576): array
    {
        return $this->imprimir($target, 'html', ['html' => $html, 'ancho_px' => $anchoPx]);
    }

    private function imprimir(string $target, string $format, array|string $data): array
    {
        $respuesta = Http::timeout(5)->post('http://localhost:8181/print', [
            'token' => $this->token,
            'target' => $target,
            'format' => $format,
            'data' => $data,
        ]);

        if ($respuesta->failed()) {
            throw new \RuntimeException('PrintBridge: '.($respuesta->json('error') ?? 'error desconocido'));
        }

        return $respuesta->json(); // ['status' => 'encolado', 'jobId' => '...']
    }
}
```

```php
// Uso, por ejemplo desde un controlador de venta
$printer = new App\Services\PrintBridgeLocal();

$printer->imprimirTexto('receipt', "PARQUE AVENTURA\nEntrada general x2   \$45.000\n\n\n");

$printer->imprimirRecibo('receipt', [
    'negocio' => 'Parque Aventura',
    'items' => [
        ['nombre' => 'Entrada general', 'cantidad' => 2, 'precio' => 45000],
    ],
    'total' => 90000,
]);

$printer->imprimirHtml('receipt', view('recibos.venta', ['venta' => $venta])->render());
```

That last line is the interesting one: since Laravel already has a
templating engine, you can write the receipt as a normal **Blade view**
(`resources/views/recibos/venta.blade.php`) with regular HTML/CSS, render
it server-side with `view(...)->render()`, and send the resulting string
straight through as `format: 'html'` — no separate templating system to
maintain. Just follow the same sizing rules as any other HTML job: fixed
pixel width matching `ancho_px`, bold sans-serif, 24px+ body text (see
[§3 above](#3-html-format-html)).

## Plain PHP (no framework)

```php
<?php

function printBridgeImprimir(string $target, string $format, $data, string $token): array
{
    $ch = curl_init('http://localhost:8181/print');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'token' => $token, 'target' => $target,
            'format' => $format, 'data' => $data,
        ]),
    ]);
    $respuesta = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($respuesta['error'])) {
        throw new RuntimeException($respuesta['error']);
    }
    return $respuesta;
}

$token = getenv('PRINTBRIDGE_TOKEN_CAJA');

// String plano -- format:'raw' decodifica `data` como base64, asi que hay
// que codificar el texto primero (mandarlo literal produce bytes
// corruptos en la impresora, sin ningun error).
$texto = "PARQUE AVENTURA\nEntrada general x2   \$45.000\n\n\n";
printBridgeImprimir('receipt', 'raw', base64_encode($texto), $token);

// HTML
$html = file_get_contents(__DIR__.'/recibo.html'); // tu propia plantilla
printBridgeImprimir('receipt', 'html', ['html' => $html, 'ancho_px' => 576], $token);
```

## Common mistakes

- **Sending literal text with `format: "raw"`.** `data` is decoded as
  base64 before being sent to the printer — plain text produces garbled
  output with **no error anywhere**, since Node's base64 decoder doesn't
  validate its input. Always `base64_encode()` (PHP) or
  `btoa(...)` (JS) first. This is the easiest mistake to make here because
  it fails silently instead of throwing.
- **HTML text too small.** Anything under ~24px body text looks noticeably
  blurry once rasterized to 1-bit — see [§3](#3-html-format-html).
- **Wrong `ancho_px`.** Must match your printer's actual dot width (576 for
  80mm, 384 for 58mm at 203dpi) — mismatched width scales the whole receipt
  wrong.
- **Calling `localhost:8181` from a remote server.** Only works from code
  running on that specific register — see the Laravel caution above.
- **Forgetting the local token.** It's the per-installation token shown in
  the agent's window ("Token for POS integration"), not your platform API
  key — they're unrelated.

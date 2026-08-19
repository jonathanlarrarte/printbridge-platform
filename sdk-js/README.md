# @printbridge/sdk-js

SDK cliente para la API pública v1 de [PrintBridge Platform](../) (sección 6
del doc de arquitectura). Pensado para integraciones de terceros y para el
propio dashboard — es "el mismo canal" en ambos casos (sección 2 del doc).

Requiere Node 19+ o navegador moderno (usa `fetch` y `crypto.subtle`, ambos
globales).

## Uso

```js
import { PrintBridgeClient } from '@printbridge/sdk-js';

const client = new PrintBridgeClient({
  baseUrl: 'https://printbridge.tu-dominio.com',
  token: 'el-token-sanctum-de-tu-empresa',
});

const { data: agents } = await client.listAgents();
const { data: jobs } = await client.listJobs({ status: 'failed' });
const summary = await client.statsSummary();

const { data: webhook, secret } = await client.createWebhook(
  'https://tu-sistema.com/webhooks/printbridge',
  ['job.printed', 'job.failed']
);
// Guardá `secret` ahora -- no se vuelve a mostrar.
```

## Verificar la firma de un webhook recibido

Cada entrega de webhook incluye el header `X-PrintBridge-Signature`
(`sha256=...`), calculado sobre el **cuerpo crudo** de la petición (sección
8.2 del doc). Verificalo con el `secret` que te dio `createWebhook`:

```js
import { verifyWebhookSignature } from '@printbridge/sdk-js';

// Express, con el body sin parsear (express.raw()):
app.post('/webhooks/printbridge', express.raw({ type: 'application/json' }), async (req, res) => {
  const rawBody = req.body.toString('utf8');
  const signature = req.header('X-PrintBridge-Signature');

  if (!(await verifyWebhookSignature(rawBody, signature, process.env.PRINTBRIDGE_WEBHOOK_SECRET))) {
    return res.status(401).send('invalid signature');
  }

  const event = JSON.parse(rawBody);
  // ... procesar event.event_type, event.payload ...
  res.sendStatus(200);
});
```

## Referencia completa

Ver la referencia interactiva en `/developers` de tu instancia de la
plataforma (Docusaurus, generada desde el spec OpenAPI que expone
[Scramble](https://scramble.dedoc.co) en `/docs/api.json` — corré
`docs-site/deploy.sh` despues de cualquier cambio a la API para
regenerarla).

| Método | Descripción |
|---|---|
| `listAgents()` | `GET /v1/agents` |
| `getAgent(id)` | `GET /v1/agents/{id}` |
| `listPrinters(agentId)` | `GET /v1/agents/{id}/printers` |
| `listJobs(filters)` | `GET /v1/jobs` (filters: `agent_id`, `printer_id`, `status`, `from`, `to`) |
| `getJob(id)` | `GET /v1/jobs/{id}` |
| `statsSummary()` | `GET /v1/stats/summary` |
| `agentStats(agentId)` | `GET /v1/stats/agents/{id}` |
| `listWebhooks()` | `GET /v1/webhooks` |
| `createWebhook(url, subscribedEvents)` | `POST /v1/webhooks` |
| `deleteWebhook(id)` | `DELETE /v1/webhooks/{id}` |
| `webhookDeliveries(id)` | `GET /v1/webhooks/{id}/deliveries` |

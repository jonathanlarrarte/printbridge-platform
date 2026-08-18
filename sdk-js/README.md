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

const { data: agentes } = await client.listarAgentes();
const { data: trabajos } = await client.listarTrabajos({ estado: 'fallo_definitivo' });
const resumen = await client.estadisticasResumen();

const { data: webhook, secreto } = await client.crearWebhook(
  'https://tu-sistema.com/webhooks/printbridge',
  ['trabajo.impreso', 'trabajo.fallo_definitivo']
);
// Guardá `secreto` ahora -- no se vuelve a mostrar.
```

## Verificar la firma de un webhook recibido

Cada entrega de webhook incluye el header `X-PrintBridge-Signature`
(`sha256=...`), calculado sobre el **cuerpo crudo** de la petición (sección
8.2 del doc). Verificalo con el `secreto` que te dio `crearWebhook`:

```js
import { verificarFirmaWebhook } from '@printbridge/sdk-js';

// Express, con el body sin parsear (express.raw()):
app.post('/webhooks/printbridge', express.raw({ type: 'application/json' }), async (req, res) => {
  const cuerpoCrudo = req.body.toString('utf8');
  const firma = req.header('X-PrintBridge-Signature');

  if (!(await verificarFirmaWebhook(cuerpoCrudo, firma, process.env.PRINTBRIDGE_WEBHOOK_SECRET))) {
    return res.status(401).send('firma invalida');
  }

  const evento = JSON.parse(cuerpoCrudo);
  // ... procesar evento.tipo_evento, evento.datos ...
  res.sendStatus(200);
});
```

## Referencia completa

Ver la documentación OpenAPI interactiva en `/docs/api` de tu instancia de
la plataforma (generada automáticamente con [Scramble](https://scramble.dedoc.co)
desde las rutas reales — siempre refleja el estado actual de la API).

| Método | Descripción |
|---|---|
| `listarAgentes()` | `GET /v1/agentes` |
| `obtenerAgente(id)` | `GET /v1/agentes/{id}` |
| `listarImpresoras(agenteId)` | `GET /v1/agentes/{id}/impresoras` |
| `listarTrabajos(filtros)` | `GET /v1/trabajos` (filtros: `agente_id`, `impresora_id`, `estado`, `desde`, `hasta`) |
| `obtenerTrabajo(id)` | `GET /v1/trabajos/{id}` |
| `estadisticasResumen()` | `GET /v1/estadisticas/resumen` |
| `estadisticasAgente(agenteId)` | `GET /v1/estadisticas/agente/{id}` |
| `listarWebhooks()` | `GET /v1/webhooks` |
| `crearWebhook(url, eventosSuscritos)` | `POST /v1/webhooks` |
| `borrarWebhook(id)` | `DELETE /v1/webhooks/{id}` |
| `entregasWebhook(id)` | `GET /v1/webhooks/{id}/entregas` |

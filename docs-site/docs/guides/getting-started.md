---
id: getting-started
title: Getting Started
sidebar_position: 1
---

# Getting Started

## Multiple branches or points of sale?

Running a chain with several branches or checkout stations? See the
[POS multi-branch integration guide](./pos-integration.md) — full architecture,
a two-printer branch example, and code samples in JavaScript, Python, PHP, C#,
and Java.

## 1. Authentication

Every call to `/v1/*` needs a Bearer token. Generate one in the dashboard,
under [Company → API keys](https://impryxa.vekronis.com/#/empresa).

```
curl https://impryxa.vekronis.com/v1/agents \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## 2. Install an agent

Each agent runs on the physical machine with the printer attached, and
self-registers against `POST /agent/register` using your company's code.
Once connected it shows up in the dashboard's
[Agents](https://impryxa.vekronis.com/#/agentes) page.

See the [step-by-step installation guide](https://impryxa.vekronis.com/#/instalar-agente)
in the dashboard — it shows your company's actual code and platform URL
pre-filled, ready to copy.

## 3. Main endpoints

| Method | Route | Description |
|---|---|---|
| GET | `/v1/agents` | Lists your company's agents and their status |
| GET | `/v1/agents/{id}/printers` | Printers registered on an agent |
| GET | `/v1/jobs` | Print jobs (filters: `agent_id`, `status`, `from`, `to`) |
| GET | `/v1/stats/summary` | Success rate, uptime, volume, errors |
| GET | `/v1/webhooks` | Your configured webhooks |
| POST | `/v1/webhooks` | Register a new webhook |

See the full **[API Reference](/developers/reference/printbridge)** for every endpoint,
request/response shape, and example.

## 4. Client SDK (JS)

Included in the platform repo, under `sdk-js/`.

```js
import { PrintBridgeClient } from '@printbridge/sdk-js';

const client = new PrintBridgeClient({ baseUrl: 'https://impryxa.vekronis.com', token: 'YOUR_API_KEY' });
const { data: agents } = await client.listAgents();
```

## 5. Webhooks

Configure them in the dashboard's [Webhooks](https://impryxa.vekronis.com/#/webhooks)
page, or via `POST /v1/webhooks` (see [API Reference](/developers/reference/webhook-store)
for the full request/response). That response only describes the webhook
*configuration* you created — not what you'll actually receive.

When a subscribed event happens, PrintBridge sends a **separate** HTTP POST
to your `url`:

```json
{
  "event_type": "job.printed",
  "event_id": 4821,
  "job_id": 1902,
  "agent_id": 6,
  "created_at": "2026-08-19T18:20:32+00:00",
  "payload": { "installation_id": "pos-desktop-o6gu6jt-dc8d9e41" }
}
```

`payload` varies by `event_type` — for `job.*` events it's the raw data the
agent reported for that job event; for `agent.online`/`agent.offline` it's
just `{ installation_id }`.

Every delivery arrives signed in the `X-PrintBridge-Signature` header
(`sha256=<hex>`, HMAC-SHA256 over the raw body, keyed with the `secret`
shown once when you created the webhook). Verify it with
`verifyWebhookSignature` from the SDK before trusting the payload — see
[Printing Examples](./printing-examples.md) for the raw HMAC computation in
other languages. Failed deliveries (no 2xx within 10s) retry with backoff —
30s, 2min, 10min, 1h, 6h — up to 5 attempts; see
`GET /v1/webhooks/{id}/deliveries` for the attempt-by-attempt history.

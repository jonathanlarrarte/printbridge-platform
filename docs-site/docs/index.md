---
id: index
slug: /
title: PrintBridge Platform API
sidebar_position: 1
---

# PrintBridge Platform API

Technical reference for integrating with the PrintBridge Platform public API
(`/v1/*`) — for point-of-sale systems, back-office dashboards, and anything
else that needs to query agents, jobs, stats, or webhooks across your
company's printers.

## Two separate APIs

PrintBridge has **two distinct integration surfaces** — don't confuse them:

- **Local agent print API** (`ws://localhost:8181/ws`, `POST http://localhost:8181/print`) —
  what your POS uses to actually print a receipt or wristband on a specific
  cash register. This runs entirely on the local network of each branch and
  never touches the internet. See the
  [POS integration guide](./guides/pos-integration.md) for the full protocol
  and code samples.
- **Platform API** (`/v1/*`, this reference) — what your back-office,
  reporting jobs, or alerting systems use to ask the platform how agents,
  printers, and jobs are doing across every branch. Authenticated with a
  Bearer API key generated from the dashboard (**Company → API keys**).

## Authentication

Every `/v1/*` request needs:

```
Authorization: Bearer <your-api-key>
```

Generate a key in the dashboard under **Company → API keys**. Keys are
scoped to your company — you'll only ever see your own agents, jobs, and
webhooks.

## Where to start

- [Getting Started](./guides/getting-started.md) — authentication, main
  endpoints, the JS SDK, and webhooks.
- [POS Multi-Branch Integration](./guides/pos-integration.md) — full
  architecture, a two-printer branch example, and code samples in
  JavaScript, Python, PHP, C#, and Java.
- Browse the **API Reference** in the sidebar for every endpoint,
  request/response shape, and example.
- [Install and register an agent](https://impryxa.vekronis.com/#/instalar-agente) —
  interactive, step-by-step, in the dashboard (shows your company's actual
  code and platform URL, ready to copy).
- [`@printbridge/sdk-js`](https://github.com/jonathanlarrarte/printbridge-platform/tree/main/sdk-js) —
  official JS client, wraps this API and webhook signature verification.

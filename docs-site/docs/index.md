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
  never touches the internet. It is **not** documented here; see the
  [POS integration guide](https://impryxa.vekronis.com/#/integracion-pos) in
  the dashboard.
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

- Browse the **API Reference** in the sidebar for every endpoint, request/response
  shape, and example.
- [Install and register an agent](https://impryxa.vekronis.com/#/instalar-agente)
- [POS multi-branch integration guide](https://impryxa.vekronis.com/#/integracion-pos)
- [`@printbridge/sdk-js`](https://github.com/jonathanlarrarte/printbridge-platform/tree/main/sdk-js) —
  official JS client, wraps this API and webhook signature verification.

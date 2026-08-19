/**
 * SDK cliente para la API pública v1 de PrintBridge Platform (sección 6 del
 * doc de arquitectura). Requiere un token Sanctum de empresa (ver sección 6:
 * `Authorization: Bearer sk_live_...`, obtenido en el dashboard o entregado
 * por el administrador de la plataforma).
 */
export class PrintBridgeClient {
  /**
   * @param {{ baseUrl: string, token: string }} opciones
   *   baseUrl: raíz de la plataforma, ej. "https://printbridge.tu-dominio.com"
   *   token: token Sanctum de la empresa
   */
  constructor({ baseUrl, token }) {
    if (!baseUrl) throw new Error('PrintBridgeClient: baseUrl es requerido');
    if (!token) throw new Error('PrintBridgeClient: token es requerido');
    this.baseUrl = baseUrl.replace(/\/+$/, '');
    this.token = token;
  }

  async #request(ruta, opciones = {}) {
    const respuesta = await fetch(`${this.baseUrl}${ruta}`, {
      ...opciones,
      headers: {
        Authorization: `Bearer ${this.token}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(opciones.headers || {}),
      },
      body: opciones.body ? JSON.stringify(opciones.body) : undefined,
    });

    if (respuesta.status === 204) return null;

    const datos = await respuesta.json().catch(() => null);

    if (!respuesta.ok) {
      const error = new Error(datos?.error || `PrintBridge API error (${respuesta.status})`);
      error.status = respuesta.status;
      error.datos = datos;
      throw error;
    }

    return datos;
  }

  // -- Agents --
  listAgents() {
    return this.#request('/v1/agents');
  }

  getAgent(id) {
    return this.#request(`/v1/agents/${id}`);
  }

  listPrinters(agentId) {
    return this.#request(`/v1/agents/${agentId}/printers`);
  }

  // -- Jobs --
  /** @param {{ agent_id?: number, printer_id?: number, status?: string, from?: string, to?: string }} filters */
  listJobs(filters = {}) {
    const qs = new URLSearchParams(
      Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && v !== '')
    ).toString();
    return this.#request(`/v1/jobs${qs ? `?${qs}` : ''}`);
  }

  getJob(id) {
    return this.#request(`/v1/jobs/${id}`);
  }

  // -- Stats (sección 9 del doc) --
  statsSummary() {
    return this.#request('/v1/stats/summary');
  }

  agentStats(agentId) {
    return this.#request(`/v1/stats/agents/${agentId}`);
  }

  // -- Webhooks (sección 8 del doc) --
  listWebhooks() {
    return this.#request('/v1/webhooks');
  }

  /**
   * @param {string} url
   * @param {string[]} subscribedEvents ej. ['job.printed', 'job.failed']
   * @returns {Promise<{data: object, secret: string}>} `secret` solo se devuelve esta vez.
   */
  createWebhook(url, subscribedEvents) {
    return this.#request('/v1/webhooks', {
      method: 'POST',
      body: { url, subscribed_events: subscribedEvents },
    });
  }

  deleteWebhook(id) {
    return this.#request(`/v1/webhooks/${id}`, { method: 'DELETE' });
  }

  webhookDeliveries(id) {
    return this.#request(`/v1/webhooks/${id}/deliveries`);
  }
}

/**
 * Verifica la firma de un webhook recibido (sección 8.2 del doc).
 * @param {string} rawBody el body tal cual llegó (string, sin parsear)
 * @param {string} receivedSignature el valor del header X-PrintBridge-Signature (formato "sha256=...")
 * @param {string} secret el secreto devuelto al crear el webhook
 * @returns {Promise<boolean>}
 */
export async function verifyWebhookSignature(rawBody, receivedSignature, secret) {
  const [, firmaHex] = receivedSignature.split('=');
  if (!firmaHex) return false;

  const clave = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  const firma = await crypto.subtle.sign('HMAC', clave, new TextEncoder().encode(rawBody));
  const firmaCalculadaHex = [...new Uint8Array(firma)].map((b) => b.toString(16).padStart(2, '0')).join('');

  if (firmaCalculadaHex.length !== firmaHex.length) return false;

  // comparación en tiempo constante
  let diferencia = 0;
  for (let i = 0; i < firmaHex.length; i++) {
    diferencia |= firmaHex.charCodeAt(i) ^ firmaCalculadaHex.charCodeAt(i);
  }
  return diferencia === 0;
}

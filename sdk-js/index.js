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

  // -- Agentes --
  listarAgentes() {
    return this.#request('/v1/agentes');
  }

  obtenerAgente(id) {
    return this.#request(`/v1/agentes/${id}`);
  }

  listarImpresoras(agenteId) {
    return this.#request(`/v1/agentes/${agenteId}/impresoras`);
  }

  // -- Trabajos --
  /** @param {{ agente_id?: number, impresora_id?: number, estado?: string, desde?: string, hasta?: string }} filtros */
  listarTrabajos(filtros = {}) {
    const qs = new URLSearchParams(
      Object.entries(filtros).filter(([, v]) => v !== undefined && v !== null && v !== '')
    ).toString();
    return this.#request(`/v1/trabajos${qs ? `?${qs}` : ''}`);
  }

  obtenerTrabajo(id) {
    return this.#request(`/v1/trabajos/${id}`);
  }

  // -- Estadísticas (sección 9 del doc) --
  estadisticasResumen() {
    return this.#request('/v1/estadisticas/resumen');
  }

  estadisticasAgente(agenteId) {
    return this.#request(`/v1/estadisticas/agente/${agenteId}`);
  }

  // -- Webhooks (sección 8 del doc) --
  listarWebhooks() {
    return this.#request('/v1/webhooks');
  }

  /**
   * @param {string} url
   * @param {string[]} eventosSuscritos ej. ['trabajo.impreso', 'trabajo.fallo_definitivo']
   * @returns {Promise<{data: object, secreto: string}>} `secreto` solo se devuelve esta vez.
   */
  crearWebhook(url, eventosSuscritos) {
    return this.#request('/v1/webhooks', {
      method: 'POST',
      body: { url, eventos_suscritos: eventosSuscritos },
    });
  }

  borrarWebhook(id) {
    return this.#request(`/v1/webhooks/${id}`, { method: 'DELETE' });
  }

  entregasWebhook(id) {
    return this.#request(`/v1/webhooks/${id}/entregas`);
  }
}

/**
 * Verifica la firma de un webhook recibido (sección 8.2 del doc).
 * @param {string} cuerpoCrudo el body tal cual llegó (string, sin parsear)
 * @param {string} firmaRecibida el valor del header X-PrintBridge-Signature (formato "sha256=...")
 * @param {string} secreto el secreto devuelto al crear el webhook
 * @returns {Promise<boolean>}
 */
export async function verificarFirmaWebhook(cuerpoCrudo, firmaRecibida, secreto) {
  const [, firmaHex] = firmaRecibida.split('=');
  if (!firmaHex) return false;

  const clave = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(secreto),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  const firma = await crypto.subtle.sign('HMAC', clave, new TextEncoder().encode(cuerpoCrudo));
  const firmaCalculadaHex = [...new Uint8Array(firma)].map((b) => b.toString(16).padStart(2, '0')).join('');

  if (firmaCalculadaHex.length !== firmaHex.length) return false;

  // comparación en tiempo constante
  let diferencia = 0;
  for (let i = 0; i < firmaHex.length; i++) {
    diferencia |= firmaHex.charCodeAt(i) ^ firmaCalculadaHex.charCodeAt(i);
  }
  return diferencia === 0;
}

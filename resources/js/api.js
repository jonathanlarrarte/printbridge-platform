const TOKEN_KEY = 'printbridge_token';
const SESION_KEY = 'printbridge_sesion';

export function guardarSesion(token, usuario, empresa) {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(SESION_KEY, JSON.stringify({ usuario, empresa }));
}

export function limpiarSesion() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(SESION_KEY);
}

export function obtenerToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function obtenerSesion() {
  const crudo = localStorage.getItem(SESION_KEY);
  return crudo ? JSON.parse(crudo) : null;
}

class ApiError extends Error {
  constructor(mensaje, status) {
    super(mensaje);
    this.status = status;
  }
}

async function llamar(ruta, opciones = {}) {
  const token = obtenerToken();

  // Nota: bootstrap/app.php registra api.php con apiPrefix: '' (seccion 6.1
  // del doc: los endpoints de agente son /agente/*, no /api/agente/*), asi
  // que las rutas van sin prefijo /api.
  const respuesta = await fetch(ruta, {
    ...opciones,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(opciones.headers || {}),
    },
    body: opciones.body ? JSON.stringify(opciones.body) : undefined,
  });

  if (respuesta.status === 401) {
    limpiarSesion();
    window.location.hash = '#/login';
    throw new ApiError('sesion expirada', 401);
  }

  if (respuesta.status === 204) return null;

  const datos = await respuesta.json().catch(() => null);

  if (!respuesta.ok) {
    const primerError = datos?.errors ? Object.values(datos.errors)[0]?.[0] : null;
    throw new ApiError(primerError || datos?.error || datos?.message || 'error de red', respuesta.status);
  }

  return datos;
}

export const api = {
  signup: (nombre_empresa, nombre_usuario, email, password) =>
    llamar('/signup', { method: 'POST', body: { nombre_empresa, nombre_usuario, email, password } }),
  login: (email, password) => llamar('/login', { method: 'POST', body: { email, password } }),
  logout: () => llamar('/logout', { method: 'POST' }),

  empresa: () => llamar('/v1/empresa'),

  apiKeys: () => llamar('/v1/api-keys'),
  crearApiKey: (nombre) => llamar('/v1/api-keys', { method: 'POST', body: { nombre } }),
  borrarApiKey: (id) => llamar(`/v1/api-keys/${id}`, { method: 'DELETE' }),

  agentes: () => llamar('/v1/agentes'),
  agente: (id) => llamar(`/v1/agentes/${id}`),

  trabajos: (filtros = {}) => {
    const qs = new URLSearchParams(Object.entries(filtros).filter(([, v]) => v)).toString();
    return llamar(`/v1/trabajos${qs ? `?${qs}` : ''}`);
  },
  trabajo: (id) => llamar(`/v1/trabajos/${id}`),

  estadisticasResumen: () => llamar('/v1/estadisticas/resumen'),
  estadisticasAgente: (id) => llamar(`/v1/estadisticas/agente/${id}`),

  webhooks: () => llamar('/v1/webhooks'),
  crearWebhook: (url, eventos_suscritos) =>
    llamar('/v1/webhooks', { method: 'POST', body: { url, eventos_suscritos } }),
  borrarWebhook: (id) => llamar(`/v1/webhooks/${id}`, { method: 'DELETE' }),
  entregasWebhook: (id) => llamar(`/v1/webhooks/${id}/entregas`),
};

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';
import ConfirmarEliminacion from '../components/ConfirmarEliminacion.vue';

const agentes = ref([]);
const cargando = ref(true);
const error = ref('');
const estadoPrueba = reactive({}); // { [impresoraId]: 'enviando' | 'enviado' | 'error' }
const expandido = reactive({}); // { [agenteId]: boolean } -- detalle de impresoras colapsado por defecto

const busqueda = ref('');
const filtroEstado = ref('todos'); // 'todos' | 'online' | 'offline'

const agenteAEliminar = ref(null);
const eliminando = ref(false);

async function cargar() {
  // Solo muestra "Cargando..." la primera vez -- los refrescos automaticos
  // de fondo no deberian hacer parpadear la lista que ya esta en pantalla.
  if (!agentes.value.length) cargando.value = true;
  try {
    const respuesta = await api.agentes();
    agentes.value = respuesta.data;
    error.value = '';
  } catch {
    error.value = 'No se pudieron cargar los agentes.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
useAutoRefresh(cargar, 20000);

// Resumen de flota -- con muchos agentes, esto es lo primero que hace
// falta ver: "¿cuantos estan mal ahora mismo?", sin tener que contar
// tarjetas a ojo.
const resumen = computed(() => {
  const total = agentes.value.length;
  const online = agentes.value.filter((a) => a.status === 'online').length;
  const impresorasTotal = agentes.value.reduce((acc, a) => acc + a.printers.length, 0);
  const impresorasOnline = agentes.value.reduce((acc, a) => acc + a.printers.filter((p) => p.status === 'online').length, 0);
  return { total, online, offline: total - online, impresorasTotal, impresorasOnline };
});

const agentesFiltrados = computed(() => {
  const termino = busqueda.value.trim().toLowerCase();
  return agentes.value.filter((a) => {
    if (filtroEstado.value !== 'todos' && a.status !== filtroEstado.value) return false;
    if (!termino) return true;
    const nombre = (a.display_name || '').toLowerCase();
    return nombre.includes(termino) || a.installation_id.toLowerCase().includes(termino);
  });
});

function formatoFecha(f) {
  return f ? new Date(f).toLocaleString() : '—';
}

// "hace X" en vez de la fecha completa -- mucho mas rapido de escanear
// cuando hay muchas tarjetas en pantalla a la vez.
function formatoRelativo(f) {
  if (!f) return 'nunca';
  const segundos = Math.floor((Date.now() - new Date(f).getTime()) / 1000);
  if (segundos < 60) return 'hace un momento';
  if (segundos < 3600) return `hace ${Math.floor(segundos / 60)} min`;
  if (segundos < 86400) return `hace ${Math.floor(segundos / 3600)} h`;
  return `hace ${Math.floor(segundos / 86400)} d`;
}

function badgeUltimoTrabajo(ultimoTrabajo) {
  if (!ultimoTrabajo) return { texto: 'sin datos', clase: 'bg-slate-100 text-slate-400' };
  if (ultimoTrabajo.status === 'printed') return { texto: '✓ última prueba ok', clase: 'bg-emerald-100 text-emerald-700' };
  if (ultimoTrabajo.status === 'failed') return { texto: '✗ falló', clase: 'bg-red-100 text-red-700' };
  return { texto: ultimoTrabajo.status, clase: 'bg-amber-100 text-amber-700' };
}

async function enviarPrueba(agenteId, impresoraId) {
  estadoPrueba[impresoraId] = 'enviando';
  try {
    await api.enviarPruebaImpresion(agenteId, impresoraId);
    estadoPrueba[impresoraId] = 'enviado';
    setTimeout(() => cargar(), 20000); // el agente la recoge en su proximo heartbeat (hasta 30s)
  } catch {
    estadoPrueba[impresoraId] = 'error';
  }
}

async function confirmarEliminacion() {
  eliminando.value = true;
  try {
    await api.borrarAgente(agenteAEliminar.value.id);
    agenteAEliminar.value = null;
    await cargar();
  } catch {
    error.value = 'No se pudo eliminar el agente.';
  } finally {
    eliminando.value = false;
  }
}
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Agentes</h1>
      <router-link :to="{ name: 'instalar-agente' }" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-medium text-white shadow-sm shadow-slate-900/20 transition hover:bg-slate-800 hover:shadow-md">
        + Instalar agente
      </router-link>
    </div>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>

    <div v-else-if="!agentes.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
      <p class="mb-3 text-sm text-slate-500">Todavía no hay agentes reportando a esta empresa.</p>
      <router-link :to="{ name: 'instalar-agente' }" class="inline-block rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Ver cómo instalar tu primer agente →
      </router-link>
    </div>

    <template v-else>
      <!-- Resumen de flota -->
      <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Agentes</p>
          <p class="mt-0.5 text-xl font-semibold text-slate-900">{{ resumen.total }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Online</p>
          <p class="mt-0.5 text-xl font-semibold text-emerald-600">{{ resumen.online }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Offline</p>
          <p class="mt-0.5 text-xl font-semibold" :class="resumen.offline ? 'text-red-600' : 'text-slate-900'">{{ resumen.offline }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Impresoras online</p>
          <p class="mt-0.5 text-xl font-semibold text-slate-900">{{ resumen.impresorasOnline }}<span class="text-sm font-normal text-slate-400">/{{ resumen.impresorasTotal }}</span></p>
        </div>
      </div>

      <!-- Busqueda + filtro -->
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative flex-1 sm:max-w-xs">
          <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
          </svg>
          <input
            v-model="busqueda"
            type="text"
            placeholder="Buscar por nombre o instalación…"
            class="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-slate-500 focus:outline-none"
          />
        </div>
        <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
          <button
            v-for="opcion in [['todos', 'Todos'], ['online', 'Online'], ['offline', 'Offline']]"
            :key="opcion[0]"
            type="button"
            class="rounded-md px-3 py-1.5 font-medium transition"
            :class="filtroEstado === opcion[0] ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            @click="filtroEstado = opcion[0]"
          >
            {{ opcion[1] }}
          </button>
        </div>
      </div>

      <p v-if="!agentesFiltrados.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
        Ningún agente coincide con este filtro.
      </p>

      <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div
          v-for="agente in agentesFiltrados"
          :key="agente.id"
          class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:shadow-md"
          :class="agente.status === 'online' ? 'hover:shadow-emerald-900/10' : 'hover:shadow-slate-900/10'"
        >
          <!-- Barra de acento superior: refleja el estado del agente de un vistazo -->
          <div
            class="absolute inset-x-0 top-0 h-1"
            :class="agente.status === 'online' ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-slate-300 to-slate-400'"
          ></div>

          <div class="p-4 pt-5">
            <div class="mb-1 flex items-start justify-between gap-2">
              <h2 class="truncate text-sm font-medium text-slate-900" :title="agente.display_name || agente.installation_id">
                {{ agente.display_name || agente.installation_id }}
              </h2>
              <button
                type="button"
                class="shrink-0 rounded-md p-1 text-slate-300 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100"
                title="Eliminar agente"
                @click="agenteAEliminar = agente"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>

            <div class="mb-3 flex items-center gap-2 text-xs text-slate-400">
              <span class="inline-flex items-center gap-1">
                <span class="relative flex h-1.5 w-1.5">
                  <span v-if="agente.status === 'online'" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="agente.status === 'online' ? 'bg-emerald-500' : 'bg-red-500'"></span>
                </span>
                <span :class="agente.status === 'online' ? 'text-emerald-700' : 'text-red-700'">{{ agente.status }}</span>
              </span>
              <span>·</span>
              <span :title="formatoFecha(agente.last_heartbeat_at)">{{ formatoRelativo(agente.last_heartbeat_at) }}</span>
            </div>

            <button
              type="button"
              class="flex w-full items-center justify-between rounded-lg bg-slate-50 px-2.5 py-2 text-xs text-slate-600 transition hover:bg-slate-100"
              @click="expandido[agente.id] = !expandido[agente.id]"
            >
              <span>
                {{ agente.printers.length }} {{ agente.printers.length === 1 ? 'impresora' : 'impresoras' }}
                <span v-if="agente.printers.length" class="text-slate-400">· {{ agente.printers.filter((p) => p.status === 'online').length }} online</span>
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform" :class="{ 'rotate-180': expandido[agente.id] }" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
              </svg>
            </button>

            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
            >
              <div v-if="expandido[agente.id]" class="mt-3 space-y-3">
                <dl class="space-y-1 text-xs text-slate-500">
                  <div class="flex justify-between"><dt>Instalación</dt><dd class="font-mono">{{ agente.installation_id }}</dd></div>
                  <div class="flex justify-between"><dt>Versión</dt><dd>{{ agente.agent_version || '—' }}</dd></div>
                </dl>

                <ul v-if="agente.printers.length" class="space-y-2">
                  <li v-for="imp in agente.printers" :key="imp.id" class="rounded-lg border border-slate-100 bg-slate-50/60 p-2.5 transition hover:border-slate-200 hover:bg-white">
                    <div class="flex items-center justify-between text-sm">
                      <span class="inline-flex items-center gap-1.5">
                        <svg v-if="imp.type === 'red'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM2 14a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2z"/></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/><path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z"/></svg>
                        {{ imp.alias }} <span class="text-slate-400">({{ imp.type }})</span>
                      </span>
                      <span class="rounded-full px-2 py-0.5 text-xs" :class="imp.status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                        {{ imp.status }}
                      </span>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between">
                      <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="badgeUltimoTrabajo(imp.last_job).clase"
                        :title="imp.last_job?.error_message || ''"
                      >
                        {{ badgeUltimoTrabajo(imp.last_job).texto }}
                      </span>
                      <button
                        class="text-xs font-medium text-slate-600 hover:text-slate-900 disabled:opacity-50"
                        :disabled="estadoPrueba[imp.id] === 'enviando'"
                        @click="enviarPrueba(agente.id, imp.id)"
                      >
                        {{ { enviando: 'Enviando…', enviado: 'Enviada ✓', error: 'Error, reintentar' }[estadoPrueba[imp.id]] || 'Enviar prueba' }}
                      </button>
                    </div>
                  </li>
                </ul>
                <p v-else class="text-xs text-slate-400">Sin impresoras configuradas.</p>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </template>

    <ConfirmarEliminacion
      v-if="agenteAEliminar"
      titulo="Eliminar agente"
      :palabra-confirmacion="agenteAEliminar.installation_id"
      :procesando="eliminando"
      :mensaje="`Vas a eliminar <strong>${agenteAEliminar.display_name || agenteAEliminar.installation_id}</strong> de forma permanente -- sus impresoras, historial de trabajos y eventos se borran con él. No se puede deshacer.<br><br>Usalo si este agente quedó asociado por error a la empresa equivocada: una vez eliminado, su código de instalación queda libre para registrarse de nuevo (en esta empresa u otra).`"
      @confirmar="confirmarEliminacion"
      @cancelar="agenteAEliminar = null"
    />
  </div>
</template>

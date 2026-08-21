<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';
import ConfirmarEliminacion from '../components/ConfirmarEliminacion.vue';

const agentes = ref([]);
const cargando = ref(true);
const error = ref('');
const estadoPrueba = reactive({}); // { [impresoraId]: 'enviando' | 'enviado' | 'error' }

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

function formatoFecha(f) {
  return f ? new Date(f).toLocaleString() : '—';
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
    <div class="mb-6 flex items-center justify-between">
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

    <div v-else class="grid gap-4 sm:grid-cols-2">
      <div
        v-for="agente in agentes"
        :key="agente.id"
        class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg"
        :class="agente.status === 'online' ? 'hover:shadow-emerald-900/10' : 'hover:shadow-slate-900/10'"
      >
        <!-- Barra de acento superior: refleja el estado del agente de un vistazo -->
        <div
          class="absolute inset-x-0 top-0 h-1"
          :class="agente.status === 'online' ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-slate-300 to-slate-400'"
        ></div>

        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-medium text-slate-900">{{ agente.display_name || agente.installation_id }}</h2>
          <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="agente.status === 'online' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
            <span class="relative flex h-1.5 w-1.5">
              <span v-if="agente.status === 'online'" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="agente.status === 'online' ? 'bg-emerald-500' : 'bg-red-500'"></span>
            </span>
            {{ agente.status }}
          </span>
        </div>
        <dl class="mb-4 space-y-1 text-sm text-slate-500">
          <div class="flex justify-between"><dt>Instalación</dt><dd class="font-mono text-xs">{{ agente.installation_id }}</dd></div>
          <div class="flex justify-between"><dt>Versión</dt><dd>{{ agente.agent_version || '—' }}</dd></div>
          <div class="flex justify-between"><dt>Último heartbeat</dt><dd>{{ formatoFecha(agente.last_heartbeat_at) }}</dd></div>
        </dl>

        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Impresoras</p>
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
        <p v-else class="text-sm text-slate-400">Sin impresoras configuradas.</p>

        <button
          type="button"
          class="mt-4 w-full rounded-lg border border-transparent py-1.5 text-xs font-medium text-slate-400 opacity-0 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 group-hover:opacity-100"
          @click="agenteAEliminar = agente"
        >
          Eliminar agente
        </button>
      </div>
    </div>

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

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';

const agentes = ref([]);
const cargando = ref(true);
const error = ref('');
const estadoPrueba = reactive({}); // { [impresoraId]: 'enviando' | 'enviado' | 'error' }

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
  if (ultimoTrabajo.status === 'printed') return { texto: '✓ última prueba ok', clase: 'bg-green-100 text-green-700' };
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
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Agentes</h1>
      <router-link :to="{ name: 'instalar-agente' }" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
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
      <div v-for="agente in agentes" :key="agente.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-medium">{{ agente.display_name || agente.installation_id }}</h2>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="agente.status === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
          >
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
          <li v-for="imp in agente.printers" :key="imp.id" class="rounded-lg border border-slate-100 p-2">
            <div class="flex items-center justify-between text-sm">
              <span>{{ imp.alias }} <span class="text-slate-400">({{ imp.type }})</span></span>
              <span class="rounded-full px-2 py-0.5 text-xs" :class="imp.status === 'online' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
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
      </div>
    </div>
  </div>
</template>

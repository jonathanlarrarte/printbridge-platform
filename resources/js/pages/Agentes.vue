<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const agentes = ref([]);
const cargando = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    const respuesta = await api.agentes();
    agentes.value = respuesta.data;
  } catch {
    error.value = 'No se pudieron cargar los agentes.';
  } finally {
    cargando.value = false;
  }
});

function formatoFecha(f) {
  return f ? new Date(f).toLocaleString() : '—';
}
</script>

<template>
  <div>
    <h1 class="mb-6 text-lg font-semibold">Agentes</h1>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
    <p v-else-if="!agentes.length" class="text-sm text-slate-500">Todavía no hay agentes reportando.</p>

    <div v-else class="grid gap-4 sm:grid-cols-2">
      <div v-for="agente in agentes" :key="agente.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-medium">{{ agente.nombre_descriptivo || agente.instalacion_id }}</h2>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="agente.estado === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
          >
            {{ agente.estado }}
          </span>
        </div>
        <dl class="mb-4 space-y-1 text-sm text-slate-500">
          <div class="flex justify-between"><dt>Instalación</dt><dd class="font-mono text-xs">{{ agente.instalacion_id }}</dd></div>
          <div class="flex justify-between"><dt>Versión</dt><dd>{{ agente.version_agente || '—' }}</dd></div>
          <div class="flex justify-between"><dt>Último heartbeat</dt><dd>{{ formatoFecha(agente.ultimo_heartbeat) }}</dd></div>
        </dl>

        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Impresoras</p>
        <ul v-if="agente.impresoras.length" class="space-y-1">
          <li v-for="imp in agente.impresoras" :key="imp.id" class="flex items-center justify-between text-sm">
            <span>{{ imp.alias }} <span class="text-slate-400">({{ imp.tipo }})</span></span>
            <span
              class="rounded-full px-2 py-0.5 text-xs"
              :class="imp.estado_heartbeat === 'online' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
            >
              {{ imp.estado_heartbeat }}
            </span>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-400">Sin impresoras configuradas.</p>
      </div>
    </div>
  </div>
</template>

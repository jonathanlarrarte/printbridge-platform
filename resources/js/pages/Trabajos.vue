<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';

const trabajos = ref([]);
const agentes = ref([]);
const cargando = ref(true);
const error = ref('');
const filtroEstado = ref('');
const filtroAgente = ref('');
const mostrarPruebas = ref(false);

// Ocultas por defecto: son ruido para una vista pensada para mostrar
// trabajos reales del POS, no lo que el propio dashboard genero al
// apretar "Enviar prueba".
const trabajosVisibles = computed(() =>
  mostrarPruebas.value ? trabajos.value : trabajos.value.filter((t) => !t.is_test)
);

const ESTADOS = ['pending', 'queued', 'printing', 'printed', 'failed'];

async function cargar() {
  if (!trabajos.value.length) cargando.value = true;
  try {
    const respuesta = await api.trabajos({
      status: filtroEstado.value || undefined,
      agent_id: filtroAgente.value || undefined,
    });
    trabajos.value = respuesta.data;
    error.value = '';
  } catch {
    error.value = 'No se pudieron cargar los trabajos.';
  } finally {
    cargando.value = false;
  }
}

onMounted(async () => {
  cargar();
  try {
    agentes.value = (await api.agentes()).data;
  } catch {
    // el filtro por agente simplemente no aparece si esto falla
  }
});
watch([filtroEstado, filtroAgente], cargar);
useAutoRefresh(cargar, 20000);

function badge(estado) {
  return {
    printed: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-700',
    printing: 'bg-amber-100 text-amber-700',
  }[estado] || 'bg-slate-100 text-slate-600';
}
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Trabajos de impresión</h1>
      <div class="flex gap-2">
        <select v-model="filtroAgente" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
          <option value="">Todos los puntos</option>
          <option v-for="a in agentes" :key="a.id" :value="a.id">{{ a.display_name || a.installation_id }}</option>
        </select>
        <select v-model="filtroEstado" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
          <option value="">Todos los estados</option>
          <option v-for="e in ESTADOS" :key="e" :value="e">{{ e }}</option>
        </select>
        <label class="flex items-center gap-1.5 text-sm text-slate-600">
          <input v-model="mostrarPruebas" type="checkbox" class="rounded border-slate-300" />
          Mostrar pruebas de impresión
        </label>
      </div>
    </div>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
    <p v-else-if="!trabajosVisibles.length" class="text-sm text-slate-500">Sin trabajos para este filtro.</p>

    <table v-else class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white text-sm shadow-sm">
      <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
        <tr>
          <th class="px-4 py-3">Job</th>
          <th class="px-4 py-3">Agente</th>
          <th class="px-4 py-3">Destino</th>
          <th class="px-4 py-3">Estado</th>
          <th class="px-4 py-3">Duración</th>
          <th class="px-4 py-3">Creado</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="t in trabajosVisibles" :key="t.id" :class="{ 'opacity-60': t.is_test }">
          <td class="px-4 py-3 font-mono text-xs" :class="t.is_test ? 'text-slate-400' : ''">{{ t.external_job_id }}</td>
          <td class="px-4 py-3">{{ t.agent_name }}</td>
          <td class="px-4 py-3">{{ t.target }}</td>
          <td class="px-4 py-3">
            <span class="rounded-full px-2 py-0.5 text-xs" :class="badge(t.status)">{{ t.status }}</span>
          </td>
          <td class="px-4 py-3">{{ t.duration_ms ? `${t.duration_ms} ms` : '—' }}</td>
          <td class="px-4 py-3 text-slate-500">{{ new Date(t.created_at).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

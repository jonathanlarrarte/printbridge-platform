<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';

const trabajos = ref([]);
const agentes = ref([]);
const cargando = ref(true);
const error = ref('');
const filtroEstado = ref('');
const filtroAgente = ref('');

// Esto es un log que crece sin limite (a diferencia de Agentes, que es una
// flota acotada) -- paginacion real, no "traer todo con un limite grande".
const pagina = ref(1);
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

// El id de una prueba de impresion no le sirve a nadie a simple vista --
// queda oculto por fila hasta que se pide verlo con el ojito, en vez de
// sacar toda la fila de la lista.
const idsRevelados = reactive({});

const ESTADOS = ['pending', 'queued', 'printing', 'printed', 'failed'];

async function cargar() {
  if (!trabajos.value.length) cargando.value = true;
  try {
    const respuesta = await api.trabajos({
      status: filtroEstado.value || undefined,
      agent_id: filtroAgente.value || undefined,
      page: pagina.value,
    });
    trabajos.value = respuesta.data;
    Object.assign(meta, respuesta.meta);
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
// Cambiar un filtro vuelve siempre a la pagina 1 -- la pagina 3 de un
// filtro anterior probablemente no exista para el nuevo.
watch([filtroEstado, filtroAgente], () => {
  pagina.value = 1;
  cargar();
});
watch(pagina, cargar);
useAutoRefresh(cargar, 20000);

function irAPagina(n) {
  if (n < 1 || n > meta.last_page) return;
  pagina.value = n;
}

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
      </div>
    </div>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
    <p v-else-if="!trabajos.length" class="text-sm text-slate-500">Sin trabajos para este filtro.</p>

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
        <tr v-for="t in trabajos" :key="t.id">
          <td class="px-4 py-3 font-mono text-xs">
            <span v-if="!t.is_test">{{ t.external_job_id }}</span>
            <span v-else class="inline-flex items-center gap-1.5">
              <span class="text-slate-400">{{ idsRevelados[t.id] ? t.external_job_id : '••••••••••••' }}</span>
              <button
                type="button"
                class="text-slate-400 hover:text-slate-700"
                :title="idsRevelados[t.id] ? 'Ocultar' : 'Mostrar'"
                @click="idsRevelados[t.id] = !idsRevelados[t.id]"
              >
                {{ idsRevelados[t.id] ? '🙈' : '👁' }}
              </button>
            </span>
          </td>
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

    <div v-if="!cargando && trabajos.length" class="mt-4 flex items-center justify-between text-sm text-slate-500">
      <p>Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}</p>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded-md border border-slate-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="meta.current_page <= 1"
          @click="irAPagina(meta.current_page - 1)"
        >
          Anterior
        </button>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button
          type="button"
          class="rounded-md border border-slate-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="meta.current_page >= meta.last_page"
          @click="irAPagina(meta.current_page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>
  </div>
</template>

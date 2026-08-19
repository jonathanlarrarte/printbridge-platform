<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';

const datos = ref(null);
const cargando = ref(true);
const error = ref('');

async function cargar() {
  if (!datos.value) cargando.value = true;
  try {
    datos.value = await api.estadisticasResumen();
    error.value = '';
  } catch (e) {
    error.value = e.status === 404 ? 'Todavía no hay estadísticas calculadas (el job corre cada 5 min).' : 'No se pudieron cargar las estadísticas.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
// Las estadisticas se precalculan cada 5 min del lado del servidor -- no
// tiene sentido pollear cada 20s como el resto de las paginas.
useAutoRefresh(cargar, 60000);

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
</script>

<template>
  <div>
    <h1 class="mb-6 text-lg font-semibold">Estadísticas</h1>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="error" class="text-sm text-slate-500">{{ error }}</p>

    <div v-else class="grid gap-6 sm:grid-cols-2">
      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Tasa de éxito por impresora</h2>
        <p v-if="!datos.success_rate_by_printer.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <ul v-else class="space-y-2 text-sm">
          <li v-for="i in datos.success_rate_by_printer" :key="i.printer_id" class="flex justify-between">
            <span>{{ i.alias }}</span>
            <span>{{ (i.success_rate * 100).toFixed(1) }}% ({{ i.printed }}/{{ i.printed + i.failed }})</span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Tiempo promedio de impresión</h2>
        <p class="text-2xl font-semibold">
          {{ datos.average_print_time_ms ? `${Math.round(datos.average_print_time_ms)} ms` : '—' }}
        </p>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Uptime por agente</h2>
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
            <tr><th class="py-1">Agente</th><th class="py-1">24h</th><th class="py-1">7d</th><th class="py-1">30d</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="u in datos.uptime_by_agent" :key="u.agent_id">
              <td class="py-2">{{ u.installation_id }}</td>
              <td class="py-2">{{ u.uptime_24h }}%</td>
              <td class="py-2">{{ u.uptime_7d }}%</td>
              <td class="py-2">{{ u.uptime_30d }}%</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Distribución de errores</h2>
        <p v-if="!datos.error_distribution.length" class="text-sm text-slate-400">Sin fallos registrados.</p>
        <ul v-else class="space-y-2 text-sm">
          <li v-for="e in datos.error_distribution" :key="e.error_message" class="flex justify-between">
            <span class="truncate pr-2">{{ e.error_message }}</span>
            <span>{{ e.count }}</span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Volumen por día de la semana</h2>
        <p v-if="!datos.volume_by_day_of_week.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <ul v-else class="space-y-2 text-sm">
          <li v-for="d in datos.volume_by_day_of_week" :key="d.day" class="flex justify-between">
            <span>{{ DIAS[d.day] }}</span>
            <span>{{ d.count }}</span>
          </li>
        </ul>
      </section>
    </div>

    <p v-if="datos" class="mt-6 text-xs text-slate-400">Calculado: {{ new Date(datos.calculated_at).toLocaleString() }}</p>
  </div>
</template>

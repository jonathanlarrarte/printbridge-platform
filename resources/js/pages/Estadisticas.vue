<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../api';
import { useAutoRefresh } from '../composables/useAutoRefresh';

const datos = ref(null);
const agentes = ref([]);
const agenteSeleccionado = ref('');
const cargando = ref(true);
const error = ref('');

async function cargar() {
  if (!datos.value) cargando.value = true;
  try {
    datos.value = agenteSeleccionado.value
      ? await api.estadisticasAgente(agenteSeleccionado.value)
      : await api.estadisticasResumen();
    error.value = '';
  } catch (e) {
    error.value = e.status === 404 ? 'Todavía no hay estadísticas calculadas (el job corre cada 5 min).' : 'No se pudieron cargar las estadísticas.';
    datos.value = null;
  } finally {
    cargando.value = false;
  }
}

onMounted(async () => {
  cargar();
  try {
    agentes.value = (await api.agentes()).data;
  } catch {
    // el selector de punto simplemente no aparece si esto falla
  }
});
watch(agenteSeleccionado, cargar);
useAutoRefresh(cargar, 60000);

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Estadísticas</h1>
      <select v-model="agenteSeleccionado" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        <option value="">Todos los puntos (general)</option>
        <option v-for="a in agentes" :key="a.id" :value="a.id">{{ a.display_name || a.installation_id }}</option>
      </select>
    </div>

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

      <section v-if="!agenteSeleccionado" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Uptime por punto</h2>
        <p v-if="!datos.uptime_by_agent.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <table v-else class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
            <tr><th class="py-1">Punto</th><th class="py-1">24h</th><th class="py-1">7d</th><th class="py-1">30d</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="u in datos.uptime_by_agent" :key="u.agent_id">
              <td class="py-2">
                <button class="font-medium text-slate-900 hover:underline" @click="agenteSeleccionado = u.agent_id">
                  {{ u.agent_name }}
                </button>
              </td>
              <td class="py-2">{{ u.uptime_24h }}%</td>
              <td class="py-2">{{ u.uptime_7d }}%</td>
              <td class="py-2">{{ u.uptime_30d }}%</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-else class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Uptime de este punto</h2>
        <p v-if="!datos.uptime_by_agent.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <div v-else class="grid grid-cols-3 gap-4 text-center">
          <div v-for="[etiqueta, valor] in [['24h', datos.uptime_by_agent[0].uptime_24h], ['7d', datos.uptime_by_agent[0].uptime_7d], ['30d', datos.uptime_by_agent[0].uptime_30d]]" :key="etiqueta">
            <p class="text-2xl font-semibold">{{ valor }}%</p>
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ etiqueta }}</p>
          </div>
        </div>
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

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Volumen por hora del día</h2>
        <p v-if="!datos.volume_by_hour.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <div v-else class="flex h-32 items-end gap-1">
          <div
            v-for="h in datos.volume_by_hour"
            :key="h.hour"
            class="flex flex-1 flex-col items-center gap-1"
            :title="`${h.hour}:00 — ${h.count} trabajos`"
          >
            <div
              class="w-full rounded-t bg-[#256abf]"
              :style="{ height: `${Math.max(4, Math.round((h.count / Math.max(1, ...datos.volume_by_hour.map((x) => x.count))) * 100))}px` }"
            ></div>
            <span class="text-[10px] text-slate-400">{{ h.hour }}</span>
          </div>
        </div>
      </section>
    </div>

    <p v-if="datos" class="mt-6 text-xs text-slate-400">Calculado: {{ new Date(datos.calculated_at).toLocaleString() }}</p>
  </div>
</template>

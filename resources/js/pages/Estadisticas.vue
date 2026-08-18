<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const datos = ref(null);
const cargando = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    datos.value = await api.estadisticasResumen();
  } catch (e) {
    error.value = e.status === 404 ? 'Todavía no hay estadísticas calculadas (el job corre cada 5 min).' : 'No se pudieron cargar las estadísticas.';
  } finally {
    cargando.value = false;
  }
});

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
        <p v-if="!datos.tasa_exito_por_impresora.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <ul v-else class="space-y-2 text-sm">
          <li v-for="i in datos.tasa_exito_por_impresora" :key="i.impresora_id" class="flex justify-between">
            <span>{{ i.alias }}</span>
            <span>{{ (i.tasa_exito * 100).toFixed(1) }}% ({{ i.impresos }}/{{ i.impresos + i.fallos }})</span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Tiempo promedio de impresión</h2>
        <p class="text-2xl font-semibold">
          {{ datos.tiempo_promedio_impresion_ms ? `${Math.round(datos.tiempo_promedio_impresion_ms)} ms` : '—' }}
        </p>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Uptime por agente</h2>
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
            <tr><th class="py-1">Agente</th><th class="py-1">24h</th><th class="py-1">7d</th><th class="py-1">30d</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="u in datos.uptime_por_agente" :key="u.agente_id">
              <td class="py-2">{{ u.instalacion_id }}</td>
              <td class="py-2">{{ u.uptime_24h }}%</td>
              <td class="py-2">{{ u.uptime_7d }}%</td>
              <td class="py-2">{{ u.uptime_30d }}%</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Distribución de errores</h2>
        <p v-if="!datos.distribucion_errores.length" class="text-sm text-slate-400">Sin fallos registrados.</p>
        <ul v-else class="space-y-2 text-sm">
          <li v-for="e in datos.distribucion_errores" :key="e.error_mensaje" class="flex justify-between">
            <span class="truncate pr-2">{{ e.error_mensaje }}</span>
            <span>{{ e.cantidad }}</span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Volumen por día de la semana</h2>
        <p v-if="!datos.volumen_por_dia_semana.length" class="text-sm text-slate-400">Sin datos todavía.</p>
        <ul v-else class="space-y-2 text-sm">
          <li v-for="d in datos.volumen_por_dia_semana" :key="d.dia" class="flex justify-between">
            <span>{{ DIAS[d.dia] }}</span>
            <span>{{ d.cantidad }}</span>
          </li>
        </ul>
      </section>
    </div>

    <p v-if="datos" class="mt-6 text-xs text-slate-400">Calculado: {{ new Date(datos.calculado_en).toLocaleString() }}</p>
  </div>
</template>

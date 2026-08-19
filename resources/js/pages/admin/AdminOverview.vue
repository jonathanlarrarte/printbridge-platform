<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../../api';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const datos = ref(null);
const cargando = ref(true);
const error = ref('');

async function cargar() {
  if (!datos.value) cargando.value = true;
  try {
    datos.value = (await api.adminResumen()).data;
    error.value = '';
  } catch (e) {
    error.value = e.message;
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
useAutoRefresh(cargar, 20000);

const tasaExitoPct = computed(() => {
  const t = datos.value?.jobs_24h?.success_rate;
  return t === null || t === undefined ? null : Math.round(t * 100);
});

const claseTasaExito = computed(() => {
  const t = tasaExitoPct.value;
  if (t === null) return 'text-slate-400';
  if (t >= 95) return 'text-green-600';
  if (t >= 80) return 'text-amber-600';
  return 'text-red-600';
});

// Barras: un solo hue secuencial (azul validado del sistema de diseño),
// sin eje ni grilla -- 7 puntos con etiqueta directa alcanza y sobra.
// Altura en px, no %: el contenedor de cada barra es un item de un flex-row
// con items-end (se ajusta a su contenido), no tiene una altura propia
// definida -- un % ahi resuelve contra "auto" y la barra no se ve.
const ALTURA_MAX_PX = 120;
const barras = computed(() => {
  const serie = datos.value?.volume_by_day || [];
  const max = Math.max(1, ...serie.map((d) => d.count));
  return serie.map((d) => ({
    ...d,
    alturaPx: Math.max(8, Math.round((d.count / max) * ALTURA_MAX_PX)),
    etiquetaDia: new Date(d.date + 'T00:00:00').toLocaleDateString('es', { weekday: 'short' }),
  }));
});
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold">Panel de administración</h1>
        <p class="text-sm text-slate-500">Cómo va la plataforma en general — todas las empresas.</p>
      </div>
      <router-link :to="{ name: 'admin-empresas' }" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">
        Ver todas las empresas →
      </router-link>
    </div>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>

    <div v-else-if="datos" class="space-y-6">
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Empresas</p>
          <p class="mt-1 text-2xl font-semibold">{{ datos.companies.total }}</p>
          <p class="mt-1 text-xs text-slate-500">
            <span class="text-green-600">{{ datos.companies.active }} activas</span>
            <span v-if="datos.companies.pending"> · <span class="text-amber-600">{{ datos.companies.pending }} pendientes</span></span>
          </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Agentes</p>
          <p class="mt-1 text-2xl font-semibold">{{ datos.agents.total }}</p>
          <p class="mt-1 text-xs text-slate-500"><span class="text-green-600">{{ datos.agents.online }} online</span> ahora</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Impresoras</p>
          <p class="mt-1 text-2xl font-semibold">{{ datos.printers.total }}</p>
          <p class="mt-1 text-xs text-slate-500"><span class="text-green-600">{{ datos.printers.online }} online</span> ahora</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Trabajos (24h)</p>
          <p class="mt-1 text-2xl font-semibold">{{ datos.jobs_24h.printed + datos.jobs_24h.failed }}</p>
          <p class="mt-1 text-xs text-slate-500">
            <span class="text-green-600">{{ datos.jobs_24h.printed }} ok</span>
            <span v-if="datos.jobs_24h.failed"> · <span class="text-red-600">{{ datos.jobs_24h.failed }} fallidos</span></span>
          </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Tasa de éxito (24h)</p>
          <p class="mt-1 text-2xl font-semibold" :class="claseTasaExito">{{ tasaExitoPct === null ? '—' : `${tasaExitoPct}%` }}</p>
          <p class="mt-1 text-xs text-slate-500">de toda la plataforma</p>
        </div>
      </div>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-700">Volumen de trabajos — últimos 7 días</h2>
        <p v-if="!barras.length" class="text-sm text-slate-400">Sin trabajos todavía.</p>
        <div v-else class="flex h-40 items-end gap-3">
          <div v-for="b in barras" :key="b.date" class="flex flex-1 flex-col items-center gap-1" :title="`${b.date}: ${b.count} trabajos`">
            <span class="text-xs font-medium text-slate-600">{{ b.count }}</span>
            <div class="w-full rounded-t" :style="{ height: `${b.alturaPx}px`, backgroundColor: '#256abf' }"></div>
            <span class="text-xs capitalize text-slate-400">{{ b.etiquetaDia }}</span>
          </div>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Empresas con más agentes</h2>
        <p v-if="!datos.top_companies.length" class="text-sm text-slate-400">Todavía no hay empresas con agentes.</p>
        <table v-else class="w-full text-sm">
          <tbody class="divide-y divide-slate-100">
            <tr v-for="e in datos.top_companies" :key="e.id">
              <td class="py-2">
                <router-link :to="{ name: 'admin-empresa', params: { id: e.id } }" class="font-medium text-slate-900 hover:underline">{{ e.name }}</router-link>
                <span v-if="!e.active" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">pendiente</span>
              </td>
              <td class="py-2 text-right text-slate-500">{{ e.agents_count }} {{ e.agents_count === 1 ? 'agente' : 'agentes' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>

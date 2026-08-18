<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

// Debe coincidir con WebhookController::EVENTOS_DISPONIBLES en el backend.
const EVENTOS_DISPONIBLES = [
  'agente.online', 'agente.offline',
  'trabajo.creado', 'trabajo.imprimiendo', 'trabajo.impreso', 'trabajo.fallo_definitivo',
];

const webhooks = ref([]);
const cargando = ref(true);
const error = ref('');

const url = ref('');
const eventosElegidos = ref([]);
const secretoNuevo = ref(null);
const creando = ref(false);

const entregasAbiertas = ref(null);
const entregas = ref([]);

async function cargar() {
  cargando.value = true;
  try {
    const respuesta = await api.webhooks();
    webhooks.value = respuesta.data;
  } catch {
    error.value = 'No se pudieron cargar los webhooks.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

async function crear() {
  if (!url.value || !eventosElegidos.value.length) return;
  creando.value = true;
  error.value = '';
  try {
    const respuesta = await api.crearWebhook(url.value, eventosElegidos.value);
    secretoNuevo.value = respuesta.secreto;
    url.value = '';
    eventosElegidos.value = [];
    await cargar();
  } catch {
    error.value = 'No se pudo crear el webhook (revisá la URL y los eventos elegidos).';
  } finally {
    creando.value = false;
  }
}

async function borrar(id) {
  await api.borrarWebhook(id);
  await cargar();
}

async function verEntregas(id) {
  if (entregasAbiertas.value === id) {
    entregasAbiertas.value = null;
    return;
  }
  const respuesta = await api.entregasWebhook(id);
  entregas.value = respuesta.data;
  entregasAbiertas.value = id;
}
</script>

<template>
  <div>
    <h1 class="mb-6 text-lg font-semibold">Webhooks</h1>

    <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-slate-700">Nuevo webhook</h2>

      <div v-if="secretoNuevo" class="mb-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800">
        Guardalo ahora, no se vuelve a mostrar: <code class="font-mono">{{ secretoNuevo }}</code>
      </div>

      <input
        v-model="url"
        type="url"
        placeholder="https://tu-sistema.com/webhooks/printbridge"
        class="mb-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
      />

      <div class="mb-3 flex flex-wrap gap-3">
        <label v-for="ev in EVENTOS_DISPONIBLES" :key="ev" class="flex items-center gap-1.5 text-sm">
          <input type="checkbox" :value="ev" v-model="eventosElegidos" />
          {{ ev }}
        </label>
      </div>

      <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

      <button
        :disabled="creando || !url || !eventosElegidos.length"
        class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
        @click="crear"
      >
        {{ creando ? 'Creando…' : 'Crear webhook' }}
      </button>
    </section>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-else-if="!webhooks.length" class="text-sm text-slate-500">Todavía no hay webhooks configurados.</p>

    <div v-else class="space-y-3">
      <div v-for="w in webhooks" :key="w.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-mono text-sm">{{ w.url }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ w.eventos_suscritos.join(', ') }}</p>
          </div>
          <div class="flex gap-2">
            <button class="text-sm text-slate-500 hover:text-slate-900" @click="verEntregas(w.id)">
              {{ entregasAbiertas === w.id ? 'Ocultar entregas' : 'Ver entregas' }}
            </button>
            <button class="text-sm text-red-600 hover:text-red-800" @click="borrar(w.id)">Borrar</button>
          </div>
        </div>

        <table v-if="entregasAbiertas === w.id" class="mt-4 w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
            <tr><th class="py-1">Intento</th><th class="py-1">HTTP</th><th class="py-1">Entregado</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="e in entregas" :key="e.id">
              <td class="py-1.5">{{ e.intento }}</td>
              <td class="py-1.5">{{ e.status_http ?? 'sin respuesta' }}</td>
              <td class="py-1.5">{{ e.exitosa ? new Date(e.entregado_en).toLocaleString() : 'fallida' }}</td>
            </tr>
            <tr v-if="!entregas.length"><td colspan="3" class="py-2 text-slate-400">Sin entregas todavía.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

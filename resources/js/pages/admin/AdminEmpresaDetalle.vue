<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../../api';
import SecretoOculto from '../../components/SecretoOculto.vue';

const route = useRoute();
const empresaId = route.params.id;

const datos = ref(null);
const cargando = ref(true);
const error = ref('');

const nombreNuevaKey = ref('');
const tokenNuevo = ref(null);
const creando = ref(false);

async function cargar() {
  cargando.value = true;
  try {
    datos.value = (await api.adminEmpresa(empresaId)).data;
  } catch (e) {
    error.value = e.message;
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

async function toggleActivo() {
  await api.adminActualizarEmpresa(empresaId, { active: !datos.value.company.active });
  await cargar();
}

async function crearApiKey() {
  if (!nombreNuevaKey.value) return;
  creando.value = true;
  error.value = '';
  try {
    const respuesta = await api.adminCrearApiKey(empresaId, nombreNuevaKey.value);
    tokenNuevo.value = respuesta.token;
    nombreNuevaKey.value = '';
    await cargar();
  } catch (e) {
    error.value = e.message;
  } finally {
    creando.value = false;
  }
}

async function borrarApiKey(id) {
  await api.adminBorrarApiKey(empresaId, id);
  await cargar();
}
</script>

<template>
  <div>
    <router-link :to="{ name: 'admin-empresas' }" class="mb-4 inline-block text-sm text-slate-500 hover:text-slate-900">← Todas las empresas</router-link>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>
    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

    <div v-if="datos">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">{{ datos.company.name }}</h1>
          <p class="font-mono text-xs text-slate-400">{{ datos.company.code }}</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="datos.company.active ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
            {{ datos.company.active ? 'activa' : 'pendiente' }}
          </span>
          <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50" @click="toggleActivo">
            {{ datos.company.active ? 'Desactivar' : 'Activar' }}
          </button>
        </div>
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">Agentes e impresoras</h2>
          <p v-if="!datos.agents.length" class="text-sm text-slate-400">Todavía no tiene agentes.</p>
          <div v-else class="space-y-3">
            <div v-for="a in datos.agents" :key="a.id" class="rounded-lg border border-slate-100 p-3">
              <div class="mb-1 flex items-center justify-between">
                <span class="font-medium text-sm">{{ a.display_name || a.installation_id }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs" :class="a.status === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ a.status }}</span>
              </div>
              <p v-if="!a.printers.length" class="text-xs text-slate-400">Sin impresoras.</p>
              <ul v-else class="text-xs text-slate-500">
                <li v-for="imp in a.printers" :key="imp.id">
                  {{ imp.alias }} ({{ imp.type }}) — {{ imp.status }}
                </li>
              </ul>
            </div>
          </div>
        </section>

        <section v-if="datos.stats" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">Tasa de éxito por impresora</h2>
          <p v-if="!datos.stats.success_rate_by_printer.length" class="text-sm text-slate-400">Sin datos todavía.</p>
          <ul v-else class="space-y-1 text-sm">
            <li v-for="i in datos.stats.success_rate_by_printer" :key="i.printer_id" class="flex justify-between">
              <span>{{ i.alias }}</span><span>{{ (i.success_rate * 100).toFixed(1) }}%</span>
            </li>
          </ul>
        </section>

        <section v-if="datos.stats" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">Distribución de errores</h2>
          <p v-if="!datos.stats.error_distribution.length" class="text-sm text-slate-400">Sin fallos registrados.</p>
          <ul v-else class="space-y-1 text-sm">
            <li v-for="e in datos.stats.error_distribution" :key="e.error_message" class="flex justify-between">
              <span class="truncate pr-2">{{ e.error_message }}</span><span>{{ e.count }}</span>
            </li>
          </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">API keys (generadas en nombre de esta empresa)</h2>

          <div v-if="tokenNuevo" class="mb-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800">
            <p class="mb-2">Guardala ahora, no se vuelve a mostrar:</p>
            <SecretoOculto :valor="tokenNuevo" />
          </div>

          <div class="mb-4 flex gap-2">
            <input
              v-model="nombreNuevaKey"
              type="text"
              placeholder="nombre (ej. integracion-cliente)"
              class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
              @keyup.enter="crearApiKey"
            />
            <button
              :disabled="creando || !nombreNuevaKey"
              class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
              @click="crearApiKey"
            >
              Generar
            </button>
          </div>

          <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
              <tr><th class="py-1">Nombre</th><th class="py-1">Último uso</th><th class="py-1"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="k in datos.api_keys" :key="k.id">
                <td class="py-2 font-mono text-xs">{{ k.name }}</td>
                <td class="py-2 text-slate-500">{{ k.last_used_at ? new Date(k.last_used_at).toLocaleString() : 'nunca' }}</td>
                <td class="py-2 text-right"><button class="text-xs text-red-600 hover:text-red-800" @click="borrarApiKey(k.id)">Revocar</button></td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </div>
  </div>
</template>

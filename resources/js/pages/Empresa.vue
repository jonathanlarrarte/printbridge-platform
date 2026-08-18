<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const empresa = ref(null);
const apiKeys = ref([]);
const cargando = ref(true);
const error = ref('');

const nombreNuevaKey = ref('');
const tokenNuevo = ref(null);
const creando = ref(false);
const copiado = ref('');

async function cargar() {
  cargando.value = true;
  try {
    const [respEmpresa, respKeys] = await Promise.all([api.empresa(), api.apiKeys()]);
    empresa.value = respEmpresa.data;
    apiKeys.value = respKeys.data;
  } catch {
    error.value = 'No se pudo cargar la información de la empresa.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

async function crearApiKey() {
  if (!nombreNuevaKey.value) return;
  creando.value = true;
  error.value = '';
  try {
    const respuesta = await api.crearApiKey(nombreNuevaKey.value);
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
  await api.borrarApiKey(id);
  await cargar();
}

async function copiar(texto, etiqueta) {
  await navigator.clipboard.writeText(texto);
  copiado.value = etiqueta;
  setTimeout(() => (copiado.value = ''), 1500);
}
</script>

<template>
  <div>
    <h1 class="mb-6 text-lg font-semibold">Empresa</h1>

    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>

    <div v-else-if="empresa" class="space-y-6">
      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Datos generales</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-500">Nombre</dt><dd>{{ empresa.nombre }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-500">Plan</dt><dd>{{ empresa.plan }}</dd></div>
        </dl>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-700">Código de instalación de agentes</h2>
        <p class="mb-3 text-sm text-slate-500">
          Usalo como <code class="rounded bg-slate-100 px-1 py-0.5">cliente_codigo</code> cuando instales
          un agente PrintBridge nuevo — así queda vinculado a esta empresa automáticamente.
        </p>
        <div class="flex items-center gap-2">
          <code class="flex-1 rounded-md bg-slate-100 px-3 py-2 text-sm">{{ empresa.codigo }}</code>
          <button
            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50"
            @click="copiar(empresa.codigo, 'codigo')"
          >
            {{ copiado === 'codigo' ? 'Copiado' : 'Copiar' }}
          </button>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">API keys</h2>

        <div v-if="tokenNuevo" class="mb-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800">
          <p class="mb-2">Guardala ahora, no se vuelve a mostrar:</p>
          <div class="flex items-center gap-2">
            <code class="flex-1 break-all rounded bg-white px-2 py-1">{{ tokenNuevo }}</code>
            <button class="shrink-0 rounded-md border border-amber-300 px-2 py-1 text-xs font-medium hover:bg-amber-100" @click="copiar(tokenNuevo, 'token')">
              {{ copiado === 'token' ? 'Copiado' : 'Copiar' }}
            </button>
          </div>
        </div>

        <div class="mb-4 flex gap-2">
          <input
            v-model="nombreNuevaKey"
            type="text"
            placeholder="nombre (ej. integracion-produccion)"
            class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
            @keyup.enter="crearApiKey"
          />
          <button
            :disabled="creando || !nombreNuevaKey"
            class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
            @click="crearApiKey"
          >
            Crear
          </button>
        </div>

        <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
            <tr><th class="py-1">Nombre</th><th class="py-1">Último uso</th><th class="py-1"></th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="k in apiKeys" :key="k.id">
              <td class="py-2 font-mono text-xs">{{ k.nombre }}</td>
              <td class="py-2 text-slate-500">{{ k.ultimo_uso ? new Date(k.ultimo_uso).toLocaleString() : 'nunca' }}</td>
              <td class="py-2 text-right">
                <button class="text-xs text-red-600 hover:text-red-800" @click="borrarApiKey(k.id)">Revocar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>

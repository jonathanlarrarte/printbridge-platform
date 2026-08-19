<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../api';

const empresas = ref([]);
const cargando = ref(true);
const error = ref('');

const nombreNueva = ref('');
const creando = ref(false);

async function cargar() {
  cargando.value = true;
  try {
    empresas.value = (await api.adminEmpresas()).data;
  } catch (e) {
    error.value = e.message;
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

async function crear() {
  if (!nombreNueva.value) return;
  creando.value = true;
  error.value = '';
  try {
    await api.adminCrearEmpresa(nombreNueva.value);
    nombreNueva.value = '';
    await cargar();
  } catch (e) {
    error.value = e.message;
  } finally {
    creando.value = false;
  }
}

async function toggleActivo(empresa) {
  await api.adminActualizarEmpresa(empresa.id, { active: !empresa.active });
  await cargar();
}
</script>

<template>
  <div>
    <h1 class="mb-1 text-lg font-semibold">Panel de administración</h1>
    <p class="mb-6 text-sm text-slate-500">Todas las empresas de la plataforma — vos ves y administrás todo, no solo lo tuyo.</p>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-slate-700">Crear empresa manualmente</h2>
      <div class="flex gap-2">
        <input
          v-model="nombreNueva"
          type="text"
          placeholder="Nombre de la empresa"
          class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
          @keyup.enter="crear"
        />
        <button
          :disabled="creando || !nombreNueva"
          class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
          @click="crear"
        >
          Crear (activa de una)
        </button>
      </div>
    </section>

    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>
    <p v-if="cargando" class="text-sm text-slate-500">Cargando…</p>

    <table v-else class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white text-sm shadow-sm">
      <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
        <tr>
          <th class="px-4 py-3">Empresa</th>
          <th class="px-4 py-3">Código</th>
          <th class="px-4 py-3">Plan</th>
          <th class="px-4 py-3">Agentes</th>
          <th class="px-4 py-3">Estado</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="e in empresas" :key="e.id">
          <td class="px-4 py-3">
            <router-link :to="{ name: 'admin-empresa', params: { id: e.id } }" class="font-medium text-slate-900 hover:underline">
              {{ e.name }}
            </router-link>
          </td>
          <td class="px-4 py-3 font-mono text-xs">{{ e.code }}</td>
          <td class="px-4 py-3">{{ e.plan }}</td>
          <td class="px-4 py-3">{{ e.agents_count }}</td>
          <td class="px-4 py-3">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="e.active ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
              {{ e.active ? 'activa' : 'pendiente' }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <button class="text-xs font-medium text-slate-600 hover:text-slate-900" @click="toggleActivo(e)">
              {{ e.active ? 'Desactivar' : 'Activar' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

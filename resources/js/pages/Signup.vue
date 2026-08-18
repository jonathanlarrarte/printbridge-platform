<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, guardarSesion } from '../api';

const router = useRouter();
const nombreEmpresa = ref('');
const nombreUsuario = ref('');
const email = ref('');
const password = ref('');
const error = ref('');
const cargando = ref(false);

async function enviar() {
  error.value = '';
  cargando.value = true;
  try {
    const { token, usuario, empresa } = await api.signup(
      nombreEmpresa.value,
      nombreUsuario.value,
      email.value,
      password.value
    );
    guardarSesion(token, usuario, empresa);
    router.push({ name: 'agentes' });
  } catch (e) {
    error.value = e.message;
  } finally {
    cargando.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-[80vh] items-center justify-center">
    <form class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm" @submit.prevent="enviar">
      <h1 class="mb-1 text-xl font-semibold">Crear tu empresa</h1>
      <p class="mb-6 text-sm text-slate-500">Vas a poder registrar agentes e integrar la API al toque.</p>

      <label class="mb-1 block text-sm font-medium text-slate-700">Nombre de la empresa</label>
      <input
        v-model="nombreEmpresa"
        type="text"
        required
        placeholder="Café Central"
        class="mb-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      />

      <label class="mb-1 block text-sm font-medium text-slate-700">Tu nombre</label>
      <input
        v-model="nombreUsuario"
        type="text"
        required
        class="mb-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      />

      <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
      <input
        v-model="email"
        type="email"
        required
        class="mb-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      />

      <label class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
      <input
        v-model="password"
        type="password"
        required
        minlength="8"
        class="mb-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      />

      <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

      <button
        type="submit"
        :disabled="cargando"
        class="mb-4 w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
      >
        {{ cargando ? 'Creando…' : 'Crear empresa' }}
      </button>

      <p class="text-center text-sm text-slate-500">
        ¿Ya tenés cuenta?
        <router-link :to="{ name: 'login' }" class="font-medium text-slate-900 hover:underline">Ingresá</router-link>
      </p>
    </form>
  </div>
</template>

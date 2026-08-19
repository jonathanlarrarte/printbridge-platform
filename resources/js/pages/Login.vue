<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, guardarSesion } from '../api';

const router = useRouter();
const email = ref('admin@demo.test');
const password = ref('');
const error = ref('');
const cargando = ref(false);

async function enviar() {
  error.value = '';
  cargando.value = true;
  try {
    const { token, user, company } = await api.login(email.value, password.value);
    guardarSesion(token, user, company);
    router.push({ name: 'agentes' });
  } catch (e) {
    if (e.status === 401) {
      error.value = 'Email o contraseña incorrectos.';
    } else if (e.status === 403) {
      error.value = e.message;
    } else {
      error.value = 'No se pudo conectar con la plataforma.';
    }
  } finally {
    cargando.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-[80vh] items-center justify-center">
    <form class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm" @submit.prevent="enviar">
      <h1 class="mb-1 text-xl font-semibold">PrintBridge Platform</h1>
      <p class="mb-6 text-sm text-slate-500">Ingresá con tu usuario de empresa.</p>

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
        class="mb-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      />

      <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

      <button
        type="submit"
        :disabled="cargando"
        class="mb-4 w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
      >
        {{ cargando ? 'Ingresando…' : 'Ingresar' }}
      </button>

      <p class="text-center text-sm text-slate-500">
        ¿Primera vez por acá?
        <router-link :to="{ name: 'signup' }" class="font-medium text-slate-900 hover:underline">Creá tu empresa</router-link>
      </p>
    </form>
  </div>
</template>

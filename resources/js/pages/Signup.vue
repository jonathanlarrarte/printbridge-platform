<script setup>
import { ref } from 'vue';
import { api } from '../api';

const nombreEmpresa = ref('');
const nombreUsuario = ref('');
const email = ref('');
const password = ref('');
const error = ref('');
const cargando = ref(false);
const resultado = ref(null);

async function enviar() {
  error.value = '';
  cargando.value = true;
  try {
    const { mensaje, empresa } = await api.signup(
      nombreEmpresa.value,
      nombreUsuario.value,
      email.value,
      password.value
    );
    resultado.value = { mensaje, empresa };
  } catch (e) {
    error.value = e.message;
  } finally {
    cargando.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-[80vh] items-center justify-center">
    <div v-if="resultado" class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
      <h1 class="mb-2 text-xl font-semibold">¡Listo!</h1>
      <p class="mb-4 text-sm text-slate-600">{{ resultado.mensaje }}</p>
      <p class="mb-6 text-xs text-slate-400">
        Código de tu empresa: <code class="rounded bg-slate-100 px-1 py-0.5">{{ resultado.empresa.codigo }}</code>
      </p>
      <router-link :to="{ name: 'login' }" class="inline-block rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Ir a la pantalla de ingreso
      </router-link>
    </div>

    <form v-else class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm" @submit.prevent="enviar">
      <h1 class="mb-1 text-xl font-semibold">Crear tu empresa</h1>
      <p class="mb-6 text-sm text-slate-500">Un administrador de la plataforma activa tu cuenta antes de que puedas ingresar.</p>

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

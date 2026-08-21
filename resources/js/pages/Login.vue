<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, guardarSesion } from '../api';

const router = useRouter();
const email = ref('');
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
  <div class="fixed inset-0 z-0 flex items-center justify-center overflow-hidden bg-slate-950 px-6">
    <!-- Fondo: grilla sutil + glows de color, todo detras del contenido -->
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
      <div
        class="absolute inset-0 opacity-[0.07]"
        style="background-image: linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px); background-size: 44px 44px;"
      ></div>
      <div class="absolute -top-32 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-blue-600/30 blur-3xl"></div>
      <div class="absolute bottom-0 right-0 h-72 w-72 translate-x-1/3 translate-y-1/3 rounded-full bg-sky-500/20 blur-3xl"></div>
    </div>

    <form
      class="relative w-full max-w-sm animate-[login-in_0.4s_ease-out] rounded-2xl border border-white/10 bg-white/[0.04] p-8 shadow-2xl shadow-black/40 backdrop-blur-xl"
      @submit.prevent="enviar"
    >
      <div class="mb-6 flex flex-col items-center text-center">
        <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 shadow-lg shadow-blue-500/40">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5 4a2 2 0 012-2h6a2 2 0 012 2v2h1a2 2 0 012 2v5a2 2 0 01-2 2h-1v1a2 2 0 01-2 2H7a2 2 0 01-2-2v-1H4a2 2 0 01-2-2V8a2 2 0 012-2h1V4zm2 0v2h6V4H7zm0 8v4h6v-4H7zm-2-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
          </svg>
        </span>
        <h1 class="text-xl font-semibold text-white">PrintBridge Platform</h1>
        <p class="mt-1 text-sm text-slate-400">Ingresá con tu usuario de empresa</p>
      </div>

      <label class="mb-1 block text-sm font-medium text-slate-300">Email</label>
      <input
        v-model="email"
        type="email"
        required
        autofocus
        class="mb-4 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-sky-400/50 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-sky-400/20"
      />

      <label class="mb-1 block text-sm font-medium text-slate-300">Contraseña</label>
      <input
        v-model="password"
        type="password"
        required
        class="mb-4 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-sky-400/50 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-sky-400/20"
      />

      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <p v-if="error" class="mb-4 rounded-lg border border-red-500/20 bg-red-500/10 px-3 py-2 text-sm text-red-300">{{ error }}</p>
      </Transition>

      <button
        type="submit"
        :disabled="cargando"
        class="mb-5 w-full rounded-lg bg-gradient-to-r from-sky-400 to-blue-600 px-3 py-2.5 text-sm font-medium text-white shadow-lg shadow-blue-500/30 transition hover:shadow-blue-500/50 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
      >
        {{ cargando ? 'Ingresando…' : 'Ingresar' }}
      </button>

      <p class="text-center text-sm text-slate-400">
        ¿Primera vez por acá?
        <router-link :to="{ name: 'signup' }" class="font-medium text-sky-400 hover:text-sky-300 hover:underline">Creá tu empresa</router-link>
      </p>
    </form>
  </div>
</template>

<style scoped>
@keyframes login-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

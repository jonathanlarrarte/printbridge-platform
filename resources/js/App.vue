<script setup>
import { computed, ref, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, limpiarSesion, obtenerSesion, obtenerToken } from './api';

const route = useRoute();
const router = useRouter();

const sesion = ref(null);
const autenticado = ref(false);
const menuAbierto = ref(false);

// route.name se lee SIEMPRE, antes que nada -- si quedara del lado derecho
// de un `&&` con obtenerToken() (falsy en cada render previo al login), el
// cortocircuito de JS nunca lo leeria mientras no haya token, Vue nunca lo
// trackearia como dependencia reactiva, y este efecto jamas se volveria a
// disparar cuando la ruta cambia despues del login.
watchEffect(() => {
  const nombreRuta = route.name;
  sesion.value = obtenerSesion();
  autenticado.value = !!obtenerToken() && nombreRuta !== 'login';
  menuAbierto.value = false;
});

const links = computed(() => [
  { name: 'agentes', label: 'Agentes' },
  { name: 'trabajos', label: 'Trabajos' },
  { name: 'estadisticas', label: 'Estadísticas' },
  { name: 'webhooks', label: 'Webhooks' },
  { name: 'empresa', label: 'Empresa' },
  ...(sesion.value?.usuario?.is_super_admin ? [{ name: 'admin-overview', label: 'Admin' }] : []),
]);

const iniciales = computed(() => {
  const nombre = sesion.value?.usuario?.name || '';
  return nombre.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase() || '?';
});

async function salir() {
  try {
    await api.logout();
  } catch {
    // si el token ya no es valido no importa, igual limpiamos localmente
  }
  limpiarSesion();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header v-if="autenticado" class="sticky top-0 z-40 border-b border-slate-800/60 bg-gradient-to-r from-slate-900 to-slate-800 text-white shadow-lg shadow-slate-900/10">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
        <div class="flex items-center gap-8">
          <router-link :to="{ name: 'agentes' }" class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-sky-400 to-blue-600 shadow-md shadow-blue-900/40">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-white" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5 4a2 2 0 012-2h6a2 2 0 012 2v2h1a2 2 0 012 2v5a2 2 0 01-2 2h-1v1a2 2 0 01-2 2H7a2 2 0 01-2-2v-1H4a2 2 0 01-2-2V8a2 2 0 012-2h1V4zm2 0v2h6V4H7zm0 8v4h6v-4H7zm-2-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
              </svg>
            </span>
            <span class="font-semibold tracking-tight">PrintBridge</span>
          </router-link>

          <nav class="hidden items-center gap-1 md:flex">
            <router-link
              v-for="link in links"
              :key="link.name"
              :to="{ name: link.name }"
              class="relative rounded-md px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"
              active-class="!text-white after:absolute after:inset-x-2 after:-bottom-[13px] after:h-0.5 after:rounded-full after:bg-sky-400 after:shadow-[0_0_8px_rgba(56,189,248,0.7)]"
            >
              {{ link.label }}
            </router-link>
            <a
              href="https://impryxa.vekronis.com/developers"
              target="_blank"
              rel="noopener"
              class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"
            >
              Docs
            </a>
          </nav>
        </div>

        <div class="hidden items-center gap-3 text-sm text-slate-300 md:flex">
          <div v-if="sesion" class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-slate-200 ring-1 ring-white/10">{{ iniciales }}</span>
            <span>{{ sesion.empresa.name }} · {{ sesion.usuario.name }}</span>
          </div>
          <button class="rounded-md bg-white/5 px-3 py-1.5 font-medium ring-1 ring-white/10 transition hover:bg-white/10" @click="salir">
            Salir
          </button>
        </div>

        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-slate-300 hover:bg-white/5 md:hidden" @click="menuAbierto = !menuAbierto">
          <svg v-if="!menuAbierto" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>

      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="menuAbierto" class="border-t border-white/10 bg-slate-900/95 px-4 pb-4 pt-2 md:hidden">
          <router-link
            v-for="link in links"
            :key="link.name"
            :to="{ name: link.name }"
            class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white"
            active-class="!bg-white/10 !text-white"
          >
            {{ link.label }}
          </router-link>
          <a
            href="https://impryxa.vekronis.com/developers"
            target="_blank"
            rel="noopener"
            class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white"
          >
            Docs
          </a>
          <div class="mt-2 flex items-center justify-between border-t border-white/10 px-3 pt-3">
            <div v-if="sesion" class="flex items-center gap-2 text-sm text-slate-300">
              <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-slate-200 ring-1 ring-white/10">{{ iniciales }}</span>
              <span>{{ sesion.empresa.name }} · {{ sesion.usuario.name }}</span>
            </div>
            <button class="rounded-md bg-white/5 px-3 py-1.5 text-sm font-medium text-slate-300 ring-1 ring-white/10 hover:bg-white/10" @click="salir">
              Salir
            </button>
          </div>
        </div>
      </Transition>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
      <router-view />
    </main>
  </div>
</template>

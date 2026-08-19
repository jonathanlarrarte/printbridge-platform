<script setup>
import { computed, ref, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, limpiarSesion, obtenerSesion, obtenerToken } from './api';

const route = useRoute();
const router = useRouter();

const sesion = ref(null);
const autenticado = ref(false);

// route.name se lee SIEMPRE, antes que nada -- si quedara del lado derecho
// de un `&&` con obtenerToken() (falsy en cada render previo al login), el
// cortocircuito de JS nunca lo leeria mientras no haya token, Vue nunca lo
// trackearia como dependencia reactiva, y este efecto jamas se volveria a
// disparar cuando la ruta cambia despues del login.
watchEffect(() => {
  const nombreRuta = route.name;
  sesion.value = obtenerSesion();
  autenticado.value = !!obtenerToken() && nombreRuta !== 'login';
});

const links = computed(() => [
  { name: 'agentes', label: 'Agentes' },
  { name: 'trabajos', label: 'Trabajos' },
  { name: 'estadisticas', label: 'Estadísticas' },
  { name: 'webhooks', label: 'Webhooks' },
  { name: 'documentacion', label: 'Docs' },
  { name: 'empresa', label: 'Empresa' },
  ...(sesion.value?.usuario?.is_super_admin ? [{ name: 'admin-overview', label: 'Admin' }] : []),
]);

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
    <header v-if="autenticado" class="bg-slate-900 text-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
        <div class="flex items-center gap-8">
          <span class="font-semibold tracking-tight">PrintBridge</span>
          <nav class="flex gap-1">
            <router-link
              v-for="link in links"
              :key="link.name"
              :to="{ name: link.name }"
              class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
              active-class="!bg-slate-700 !text-white"
            >
              {{ link.label }}
            </router-link>
          </nav>
        </div>
        <div class="flex items-center gap-4 text-sm text-slate-300">
          <span v-if="sesion">{{ sesion.empresa.name }} · {{ sesion.usuario.name }}</span>
          <button class="rounded-md bg-slate-800 px-3 py-1.5 font-medium hover:bg-slate-700" @click="salir">
            Salir
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
      <router-view />
    </main>
  </div>
</template>

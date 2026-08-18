import { createRouter, createWebHashHistory } from 'vue-router';
import { obtenerToken } from './api';
import Login from './pages/Login.vue';
import Agentes from './pages/Agentes.vue';
import Trabajos from './pages/Trabajos.vue';
import Estadisticas from './pages/Estadisticas.vue';
import Webhooks from './pages/Webhooks.vue';

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { publica: true } },
  { path: '/', redirect: '/agentes' },
  { path: '/agentes', name: 'agentes', component: Agentes },
  { path: '/trabajos', name: 'trabajos', component: Trabajos },
  { path: '/estadisticas', name: 'estadisticas', component: Estadisticas },
  { path: '/webhooks', name: 'webhooks', component: Webhooks },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach((to) => {
  if (!to.meta.publica && !obtenerToken()) {
    return { name: 'login' };
  }
  if (to.name === 'login' && obtenerToken()) {
    return { name: 'agentes' };
  }
});

export default router;

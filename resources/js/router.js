import { createRouter, createWebHashHistory } from 'vue-router';
import { obtenerToken } from './api';
import Login from './pages/Login.vue';
import Signup from './pages/Signup.vue';
import Agentes from './pages/Agentes.vue';
import Trabajos from './pages/Trabajos.vue';
import Estadisticas from './pages/Estadisticas.vue';
import Webhooks from './pages/Webhooks.vue';
import Empresa from './pages/Empresa.vue';
import Documentacion from './pages/Documentacion.vue';
import InstalarAgente from './pages/InstalarAgente.vue';

const PUBLICAS = ['login', 'signup'];

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { publica: true } },
  { path: '/signup', name: 'signup', component: Signup, meta: { publica: true } },
  { path: '/', redirect: '/agentes' },
  { path: '/agentes', name: 'agentes', component: Agentes },
  { path: '/trabajos', name: 'trabajos', component: Trabajos },
  { path: '/estadisticas', name: 'estadisticas', component: Estadisticas },
  { path: '/webhooks', name: 'webhooks', component: Webhooks },
  { path: '/empresa', name: 'empresa', component: Empresa },
  { path: '/documentacion', name: 'documentacion', component: Documentacion },
  { path: '/instalar-agente', name: 'instalar-agente', component: InstalarAgente },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach((to) => {
  if (!to.meta.publica && !obtenerToken()) {
    return { name: 'login' };
  }
  if (PUBLICAS.includes(to.name) && obtenerToken()) {
    return { name: 'agentes' };
  }
});

export default router;

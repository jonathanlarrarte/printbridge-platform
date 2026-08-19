<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const empresa = ref(null);
const baseUrl = window.location.origin;

onMounted(async () => {
  try {
    empresa.value = (await api.empresa()).data;
  } catch {
    // la pagina funciona igual sin el codigo personalizado
  }
});

const ENDPOINTS = [
  ['GET', '/v1/agentes', 'Lista los agentes de tu empresa y su estado'],
  ['GET', '/v1/agentes/{id}/impresoras', 'Impresoras registradas en un agente'],
  ['GET', '/v1/trabajos', 'Trabajos de impresión (filtros: agente_id, estado, desde, hasta)'],
  ['GET', '/v1/estadisticas/resumen', 'Tasa de éxito, uptime, volumen, errores'],
  ['GET', '/v1/webhooks', 'Tus webhooks configurados'],
  ['POST', '/v1/webhooks', 'Registrar un webhook nuevo'],
];
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="mb-6 text-lg font-semibold">Documentación de integración</h1>

    <section class="mb-6 rounded-xl border border-slate-900 bg-slate-900 p-5 text-white shadow-sm">
      <h2 class="mb-2 text-sm font-semibold">¿Cadena con varias sucursales o puntos de venta?</h2>
      <p class="mb-3 text-sm text-slate-300">
        Guía técnica completa: arquitectura del flujo, cómo configurar una sucursal con impresora
        térmica de 80mm + impresora de brazaletes, ejemplos de integración en JavaScript, Python,
        PHP, C# y Java, y cómo escalar a 10+ locales.
      </p>
      <router-link :to="{ name: 'integracion-pos' }" class="inline-block rounded-md bg-white px-3 py-2 text-sm font-medium text-slate-900 hover:bg-slate-100">
        Ver guía de integración POS multi-sucursal →
      </router-link>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">1. Autenticación</h2>
      <p class="mb-3 text-sm text-slate-600">
        Todas las llamadas a <code class="rounded bg-slate-100 px-1">/v1/*</code> van con un token Bearer.
        Generá uno en <router-link :to="{ name: 'empresa' }" class="font-medium text-slate-900 hover:underline">Empresa → API keys</router-link>.
      </p>
      <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">curl {{ baseUrl }}/v1/agentes \
  -H "Authorization: Bearer TU_API_KEY"</pre>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">2. Instalar un agente</h2>
      <p class="mb-3 text-sm text-slate-600">
        Cada agente corre en el equipo físico con la impresora conectada, y se autoregistra contra
        <code class="rounded bg-slate-100 px-1">POST /agente/registrar</code> usando el código de tu empresa
        <code v-if="empresa" class="rounded bg-slate-100 px-1">{{ empresa.codigo }}</code>.
        Una vez conectado aparece solo en <router-link :to="{ name: 'agentes' }" class="font-medium text-slate-900 hover:underline">Agentes</router-link>.
      </p>
      <router-link :to="{ name: 'instalar-agente' }"
                    class="inline-block rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Ver guía completa de instalación →
      </router-link>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-slate-700">3. Endpoints principales</h2>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <tr v-for="[metodo, ruta, desc] in ENDPOINTS" :key="ruta + metodo">
            <td class="py-2 pr-3 font-mono text-xs text-slate-500">{{ metodo }}</td>
            <td class="py-2 pr-3 font-mono text-xs">{{ ruta }}</td>
            <td class="py-2 text-slate-600">{{ desc }}</td>
          </tr>
        </tbody>
      </table>
      <a :href="`${baseUrl}/docs/api`" target="_blank" rel="noopener"
         class="mt-4 inline-block rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Ver referencia completa (OpenAPI) →
      </a>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">4. SDK cliente (JS)</h2>
      <p class="mb-3 text-sm text-slate-600">Incluido en el repo de la plataforma, en <code class="rounded bg-slate-100 px-1">sdk-js/</code>.</p>
      <pre class="overflow-x-auto rounded-md bg-slate-900 p-3 text-xs text-slate-100">import { PrintBridgeClient } from '@printbridge/sdk-js';

const client = new PrintBridgeClient({ baseUrl: '{{ baseUrl }}', token: 'TU_API_KEY' });
const { data: agentes } = await client.listarAgentes();</pre>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">5. Webhooks</h2>
      <p class="mb-3 text-sm text-slate-600">
        Cada entrega llega firmada en el header <code class="rounded bg-slate-100 px-1">X-PrintBridge-Signature</code>
        (HMAC-SHA256 sobre el cuerpo crudo). Configuralos en
        <router-link :to="{ name: 'webhooks' }" class="font-medium text-slate-900 hover:underline">Webhooks</router-link>,
        y verificá la firma con <code class="rounded bg-slate-100 px-1">verificarFirmaWebhook</code> del SDK.
      </p>
    </section>
  </div>
</template>

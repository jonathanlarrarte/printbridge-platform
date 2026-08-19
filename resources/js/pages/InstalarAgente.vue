<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const empresa = ref(null);
const baseUrl = window.location.origin;
const copiado = ref('');

// Bump esto cuando se suba un .exe nuevo a /var/www/downloads en el
// servidor -- el nombre de archivo tiene que coincidir exacto.
const VERSION_AGENTE = '1.0.0';
const urlDescarga = `${baseUrl}/downloads/PrintBridge-Setup-${VERSION_AGENTE}.exe`;

onMounted(async () => {
  try {
    empresa.value = (await api.empresa()).data;
  } catch {
    // la guia funciona igual sin el codigo personalizado
  }
});

async function copiar(texto, etiqueta) {
  await navigator.clipboard.writeText(texto);
  copiado.value = etiqueta;
  setTimeout(() => (copiado.value = ''), 1500);
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="mb-1 text-lg font-semibold">Instalar y registrar un agente</h1>
    <p class="mb-6 text-sm text-slate-500">
      Un agente corre en cada caja/equipo físico que tiene una impresora conectada. Podés instalar
      tantos como necesites — cada uno queda vinculado a esta empresa.
    </p>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Requisitos</h2>
      <ul class="list-inside list-disc space-y-1 text-sm text-slate-600">
        <li>Windows 10/11 en el equipo donde va a correr el agente.</li>
        <li>Una impresora térmica ESC/POS (recibos) o TSC/TSPL (etiquetas/brazaletes) — USB o red. También podés instalarlo sin impresora y configurarla después.</li>
        <li>Conexión a internet saliente hacia esta plataforma (el agente inicia la conexión, no necesita IP pública ni puertos abiertos entrantes).</li>
      </ul>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Paso 1 — Descargar el instalador</h2>
      <p class="mb-3 text-sm text-slate-600">
        Descargá el instalador y llevalo al equipo donde vas a instalar — es el único
        archivo que necesitás, no hace falta nada más.
      </p>
      <a
        :href="urlDescarga"
        class="inline-block rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
      >
        ⬇ Descargar PrintBridge-Setup-{{ VERSION_AGENTE }}.exe
      </a>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Paso 2 — Instalar</h2>
      <p class="text-sm text-slate-600">
        Doble clic al <code class="rounded bg-slate-100 px-1">.exe</code> → Siguiente → Siguiente → Instalar.
        Al terminar, el agente queda corriendo en la bandeja del sistema (junto al reloj de Windows) con
        arranque automático configurado — no hace falta abrirlo manualmente después de reiniciar.
      </p>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Paso 3 — Conectar con esta plataforma</h2>
      <p class="mb-3 text-sm text-slate-600">
        Abrí la ventana del agente (ícono de la bandeja → "Abrir configuración") y completá la sección
        <strong>"Conexión con PrintBridge Platform"</strong> con estos dos datos:
      </p>
      <dl class="space-y-3 text-sm">
        <div>
          <dt class="mb-1 font-medium text-slate-700">URL de la plataforma</dt>
          <dd class="flex items-center gap-2">
            <code class="flex-1 rounded-md bg-slate-100 px-3 py-2">{{ baseUrl }}</code>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50" @click="copiar(baseUrl, 'url')">
              {{ copiado === 'url' ? 'Copiado' : 'Copiar' }}
            </button>
          </dd>
        </div>
        <div v-if="empresa">
          <dt class="mb-1 font-medium text-slate-700">Código de cliente</dt>
          <dd class="flex items-center gap-2">
            <code class="flex-1 rounded-md bg-slate-100 px-3 py-2">{{ empresa.code }}</code>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50" @click="copiar(empresa.code, 'codigo')">
              {{ copiado === 'codigo' ? 'Copiado' : 'Copiar' }}
            </button>
          </dd>
        </div>
      </dl>
      <p class="mt-3 text-sm text-slate-600">
        Apretá <strong>"Conectar"</strong>. Si todo está bien, el estado cambia a <span class="font-medium text-green-700">Conectado</span> al instante.
      </p>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Paso 4 — Configurar las impresoras</h2>
      <p class="text-sm text-slate-600">
        En la misma ventana, sección "Agregar / actualizar impresora": elegí un alias (ej.
        <code class="rounded bg-slate-100 px-1">receipt</code>, <code class="rounded bg-slate-100 px-1">wristband</code>),
        el tipo de conexión (USB/local o red) y el formato de comandos (ESC/POS para recibos, TSPL para
        TSC/brazaletes). El agente reporta el estado de cada una a la plataforma automáticamente.
      </p>
    </section>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Paso 5 — Confirmar</h2>
      <p class="text-sm text-slate-600">
        Volvé a <router-link :to="{ name: 'agentes' }" class="font-medium text-slate-900 hover:underline">Agentes</router-link> acá en el
        dashboard — el agente nuevo debería aparecer <span class="font-medium text-green-700">online</span> en unos 15-30 segundos
        (es el intervalo de heartbeat), con sus impresoras listadas.
      </p>
    </section>

    <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
      <h2 class="mb-2 text-sm font-semibold text-amber-900">¿No aparece?</h2>
      <ul class="list-inside list-disc space-y-1 text-sm text-amber-800">
        <li>Revisá que la URL de la plataforma no tenga una barra final ni un typo (probala en un navegador: debería responder algo en <code class="rounded bg-amber-100 px-1">/health</code>).</li>
        <li>El código de cliente distingue mayúsculas/minúsculas — copialo en vez de tipearlo.</li>
        <li>Si cambiaste la URL o el código después de haber conectado una vez, el agente se re-registra solo la próxima vez que apretás "Conectar" (no hace falta reinstalar).</li>
        <li>El equipo necesita salida a internet hacia esta URL — revisá firewall/proxy corporativo si aplica.</li>
      </ul>
    </section>
  </div>
</template>

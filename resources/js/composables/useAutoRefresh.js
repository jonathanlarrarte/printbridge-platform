import { onMounted, onUnmounted } from 'vue';

// Re-llama a `fn` cada `intervaloMs` mientras el componente esta montado --
// para paginas de monitoreo (estado de agentes/impresoras, KPIs) donde el
// dato cambia solo del lado del servidor y nadie va a estar apretando F5.
export function useAutoRefresh(fn, intervaloMs = 20000) {
  let id = null;

  onMounted(() => {
    id = setInterval(fn, intervaloMs);
  });

  onUnmounted(() => {
    if (id) clearInterval(id);
  });
}

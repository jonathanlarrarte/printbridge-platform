<script setup>
import { ref } from 'vue';

const props = defineProps({ valor: { type: String, required: true } });

const visible = ref(false);
const copiado = ref(false);

const enmascarado = () => {
  const v = props.valor;
  if (v.length <= 8) return '•'.repeat(v.length);
  return `${'•'.repeat(v.length - 6)}${v.slice(-6)}`;
};

async function copiar() {
  await navigator.clipboard.writeText(props.valor);
  copiado.value = true;
  setTimeout(() => (copiado.value = false), 1500);
}
</script>

<template>
  <div class="flex items-center gap-2">
    <code class="flex-1 break-all rounded-md bg-slate-100 px-3 py-2 text-sm">{{ visible ? valor : enmascarado() }}</code>
    <button
      type="button"
      class="shrink-0 rounded-md border border-slate-300 px-2 py-2 text-xs font-medium hover:bg-slate-50"
      @click="visible = !visible"
    >
      {{ visible ? 'Ocultar' : 'Mostrar' }}
    </button>
    <button
      type="button"
      class="shrink-0 rounded-md border border-slate-300 px-2 py-2 text-xs font-medium hover:bg-slate-50"
      @click="copiar"
    >
      {{ copiado ? 'Copiado' : 'Copiar' }}
    </button>
  </div>
</template>

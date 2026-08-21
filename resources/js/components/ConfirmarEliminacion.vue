<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  titulo: { type: String, default: 'Eliminar' },
  mensaje: { type: String, required: true },
  palabraConfirmacion: { type: String, required: true },
  procesando: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'cancelar']);

const texto = ref('');
const coincide = computed(() => texto.value.trim() === props.palabraConfirmacion);
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4" @click.self="emit('cancelar')">
      <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl shadow-red-900/10 animate-[modal-in_0.18s_ease-out]">
        <div class="mb-4 flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
          </div>
          <h2 class="text-base font-semibold text-slate-900">{{ titulo }}</h2>
        </div>

        <p class="mb-4 text-sm leading-relaxed text-slate-600" v-html="mensaje"></p>

        <label class="mb-1.5 block text-xs font-medium text-slate-500">
          Escribí <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-800">{{ palabraConfirmacion }}</code> para confirmar
        </label>
        <input
          v-model="texto"
          type="text"
          autocomplete="off"
          class="mb-5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-100"
          :placeholder="palabraConfirmacion"
          @keyup.enter="coincide && !procesando && emit('confirmar')"
        />

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            @click="emit('cancelar')"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="!coincide || procesando"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-red-600/30 transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
            @click="emit('confirmar')"
          >
            {{ procesando ? 'Eliminando…' : 'Eliminar definitivamente' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes modal-in {
  from { opacity: 0; transform: translateY(6px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>

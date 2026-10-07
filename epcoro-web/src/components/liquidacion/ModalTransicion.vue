<script setup lang="ts">
/**
 * Modal de transicion (9.2): pide el dato que la transicion necesita.
 *
 * El contrato real es el de TransicionRequest: `motivo` OBLIGATORIO en
 * `rechazado` y `anulado` (la razon que el tecnico necesita para corregir),
 * y `comentario` SIEMPRE opcional. Ese es el campo que aqui se ofrece, igual
 * de obligatorio u opcional que alla, para que la UI y la API no se
 * desincronicen (9.9, espejo de las reglas del backend).
 */
import { computed, nextTick, ref, watch } from 'vue'

import type { TransicionPayload } from '@/types/liquidacion'
import type { AccionTransicion } from '@/utils/transiciones'

const props = withDefaults(
  defineProps<{
    abierto: boolean
    accion: AccionTransicion | null
    enviando?: boolean
  }>(),
  { enviando: false },
)

const emit = defineEmits<{
  cancelar: []
  confirmar: [payload: TransicionPayload]
}>()

const texto = ref('')
const error = ref('')
const area = ref<HTMLTextAreaElement | null>(null)

watch(
  () => [props.abierto, props.accion] as const,
  () => {
    if (!props.abierto) {
      return
    }

    texto.value = ''
    error.value = ''
    void nextTick(() => area.value?.focus())
  },
)

const titulo = computed(() => props.accion?.etiqueta ?? 'Confirmar movimiento')
const esPeligro = computed(() => props.accion?.variante === 'peligro')

function confirmar(): void {
  if (!props.accion) {
    return
  }

  const valor = texto.value.trim()

  if (props.accion.requiereMotivo && !valor) {
    error.value = 'El motivo es obligatorio en esta transición.'
    return
  }

  emit('confirmar', {
    estado_destino: props.accion.destino,
    ...(props.accion.requiereMotivo ? { motivo: valor } : { comentario: valor || undefined }),
  })
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="abierto"
      class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      :aria-label="`${titulo}: ${accion?.etiqueta ?? ''}`"
    >
      <div class="absolute inset-0 bg-slate-900/50" @click="emit('cancelar')" />

      <div
        class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
        :class="esPeligro ? 'ring-1 ring-rose-200' : ''"
      >
        <h2 class="text-base font-semibold text-slate-800">{{ titulo }}</h2>
        <p class="mt-1 text-sm text-slate-500">
          {{
            accion?.requiereMotivo
              ? 'El motivo es obligatorio: es la razón que verá el técnico para corregir.'
              : 'El comentario es opcional. Dejá las notas que quieras en el historial.'
          }}
        </p>

        <textarea
          ref="area"
          v-model="texto"
          rows="3"
          :maxlength="2000"
          :placeholder="accion?.requiereMotivo ? 'Motivo…' : 'Comentario (opcional)…'"
          class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-100"
          @keydown.esc="emit('cancelar')"
        />

        <p v-if="error" class="mt-1.5 text-sm text-rose-600">{{ error }}</p>

        <div class="mt-4 flex justify-end gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            @click="emit('cancelar')"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="enviando"
            class="rounded-lg px-3 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-60"
            :class="esPeligro ? 'bg-rose-600 hover:bg-rose-700' : 'bg-marca-600 hover:bg-marca-700'"
            @click="confirmar"
          >
            {{ enviando ? 'Procesando…' : 'Confirmar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
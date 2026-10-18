<script setup lang="ts">
/**
 * Insignia de estado de una liquidacion: color por estado, el mismo en el
 * listado, la tarjeta movil y el detalle.
 */
import { computed } from 'vue'

import { ETIQUETAS_ESTADO, type EstadoLiquidacion } from '@/types/liquidacion'

const props = defineProps<{ estado: EstadoLiquidacion }>()

const clases = computed(() => {
  const paleta: Record<EstadoLiquidacion, string> = {
    creado: 'bg-sky-100 text-sky-700',
    en_revision: 'bg-amber-100 text-amber-700',
    revisado: 'bg-teal-100 text-teal-700',
    en_correccion: 'bg-violet-100 text-violet-700',
    aprobado: 'bg-emerald-100 text-emerald-700',
    rechazado: 'bg-rose-100 text-rose-700',
    anulado: 'bg-slate-200 text-slate-600',
    eliminado: 'bg-slate-200 text-slate-500 line-through',
  }

  return paleta[props.estado]
})
</script>

<template>
  <span
    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
    :class="clases"
  >
    {{ ETIQUETAS_ESTADO[estado] }}
  </span>
</template>
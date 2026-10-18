<script setup lang="ts">
/**
 * Timeline del historial de estados (9.3): una linea vertical, un punto por
 * movimiento. El punto y la insignia usan el color del estado NUEVO, y el
 * estado anterior se lee de izquierda a derecha (muted → badged).
 */
import BadgeEstado from '@/components/BadgeEstado.vue'
import { ETIQUETAS_ESTADO } from '@/types/liquidacion'
import type { EstadoLiquidacion, HistorialEstado } from '@/types/liquidacion'
import { fechaHora } from '@/utils/formato'

defineProps<{ filas: HistorialEstado[] }>()

/** El mismo color que BadgeEstado: el punto del timeline acompaña al badge. */
const colorPunto: Record<EstadoLiquidacion, string> = {
  creado: 'bg-sky-500',
  en_revision: 'bg-amber-500',
  revisado: 'bg-teal-500',
  en_correccion: 'bg-violet-500',
  aprobado: 'bg-emerald-500',
  rechazado: 'bg-rose-500',
  anulado: 'bg-slate-400',
  eliminado: 'bg-slate-400',
}

function etiqueta(estado: string | null): string {
  if (!estado) {
    return '—'
  }

  return ETIQUETAS_ESTADO[estado as EstadoLiquidacion] ?? estado
}
</script>

<template>
  <ol v-if="filas.length" class="relative space-y-4 border-l-2 border-slate-200 pl-5">
    <li v-for="fila in filas" :key="fila.id" class="relative">
      <span
        class="absolute -left-[1.32rem] top-1 h-3 w-3 rounded-full ring-4 ring-white"
        :class="colorPunto[fila.estado_nuevo as EstadoLiquidacion] ?? 'bg-slate-400'"
      />
      <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="text-sm text-slate-500">{{ etiqueta(fila.estado_anterior) }}</span>
        <span class="text-slate-400">→</span>
        <BadgeEstado :estado="fila.estado_nuevo as EstadoLiquidacion" />
      </div>
      <p class="mt-0.5 text-xs text-slate-500">
        {{ fila.usuario.username ?? fila.usuario.nombre_completo }} · {{ fechaHora(fila.fecha) }}
      </p>
      <p v-if="fila.comentario" class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
        {{ fila.comentario }}
      </p>
    </li>
  </ol>
  <p v-else class="text-sm text-slate-500">Sin movimientos registrados.</p>
</template>
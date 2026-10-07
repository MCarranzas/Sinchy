<script setup lang="ts">
/**
 * Barra de estado y acciones de una liquidacion (9.1).
 *
 * La seguridad real vive en el backend: este componente solo pinta lo que el
 * recurso ya entrego en `transiciones_disponibles`, que se calcula con el rol
 * del usuario autenticado y con la propiedad de la fila. Ocultar un boton no
 * es seguridad, pero equivalerse con el backend si lo es. Cuando no hay
 * ninguna transicion posible la barra muestra el estado como solo lectura,
 * que es tambien lo que el detalle debe decir.
 */
import { computed } from 'vue'

import BadgeEstado from '@/components/BadgeEstado.vue'
import type { Liquidacion } from '@/types/liquidacion'
import type { EstadoLiquidacion } from '@/types/liquidacion'
import { accionTransicion } from '@/utils/transiciones'

const props = defineProps<{ liquidacion: Liquidacion }>()

const emit = defineEmits<{ accion: [destino: EstadoLiquidacion] }>()

const acciones = computed(() =>
  props.liquidacion.transiciones_disponibles.map((destino: string) =>
    accionTransicion(props.liquidacion.estado, destino as EstadoLiquidacion),
  ),
)

const soloLectura = computed(() => acciones.value.length === 0)

const variantes: Record<string, string> = {
  primario: 'bg-marca-600 text-white hover:bg-marca-700',
  secundario: 'border border-slate-300 text-slate-600 hover:bg-slate-50',
  peligro: 'bg-rose-600 text-white hover:bg-rose-700',
}
</script>

<template>
  <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
    <div class="flex min-w-0 items-center gap-2">
      <BadgeEstado :estado="liquidacion.estado" />
      <span v-if="soloLectura" class="text-sm text-slate-500">Solo lectura</span>
    </div>

    <div v-if="!soloLectura" class="ml-auto flex flex-wrap gap-2">
      <button
        v-for="accion in acciones"
        :key="accion.destino"
        type="button"
        class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors"
        :class="variantes[accion.variante]"
        @click="emit('accion', accion.destino)"
      >
        {{ accion.etiqueta }}
      </button>
    </div>
  </div>
</template>
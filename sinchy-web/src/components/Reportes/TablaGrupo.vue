<script setup lang="ts">
/**
 * Tabla de un agrupado del reporte (10.3): grupo | n | bruto | neto. Reusable
 * para estado, municipio, area minera, presentacion y mes: la vista le pasa
 * el `etiquetarValor` que traduce su valor crudo al nombre humano.
 */
import { bs, numero } from '@/utils/formato'
import type { FilaAgrupada } from '@/types/reportes'

defineProps<{
  titulo: string
  filas: FilaAgrupada[]
  etiquetarValor: (valor: string | null) => string
}>()
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <h3
      class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800"
    >
      {{ titulo }}
    </h3>

    <div class="max-h-80 overflow-auto">
      <table class="w-full text-sm">
        <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-2 text-left">Grupo</th>
            <th class="px-2 py-2 text-right">N</th>
            <th class="px-2 py-2 text-right">Bruto</th>
            <th class="px-2 py-2 text-right">Neto</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="fila in filas" :key="String(fila.valor ?? 'sin-valor')">
            <td class="px-4 py-2 text-slate-700">
              {{ etiquetarValor(fila.valor) }}
            </td>
            <td class="px-2 py-2 text-right tabular-nums text-slate-600">
              {{ numero(fila.liquidaciones, 0) }}
            </td>
            <td class="px-2 py-2 text-right tabular-nums text-slate-700">
              {{ bs(fila.bruto_total) }}
            </td>
            <td class="px-2 py-2 text-right tabular-nums text-slate-700">
              {{ bs(fila.neto_total) }}
            </td>
          </tr>
          <tr v-if="filas.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-slate-400">
              Sin datos en el período.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
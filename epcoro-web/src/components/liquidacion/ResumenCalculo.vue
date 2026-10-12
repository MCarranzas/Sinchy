<script setup lang="ts">
/**
 * ResumenCalculo (8.7): previsualizacion EN VIVO del calculo.
 *
 * Los numeros salen de utils/calculo.ts, espejo de
 * CalculoLiquidacionService: UN redondeo half-up a 2 decimales y al final.
 * Es solo feedback visual; lo que se guarda lo calcula el backend y por eso
 * el detalle siempre muestra lo persistido, nunca esta previsualizacion.
 */
import { bs, gramos, numero } from '@/utils/formato'
import type { ResultadoCalculo } from '@/utils/calculo'

defineProps<{
  resultado: ResultadoCalculo
  ley?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-3 flex items-center justify-between text-sm font-semibold uppercase tracking-wide text-slate-500">
      Resumen del cálculo
      <span
        class="rounded-full px-2 py-0.5 text-[10px] font-medium"
        :class="resultado.valido ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
      >
        {{ resultado.valido ? 'en vivo' : 'incompleto' }}
      </span>
    </h2>

    <dl class="space-y-2 text-sm">
      <div class="flex justify-between gap-3">
        <dt class="text-slate-500">Peso fino</dt>
        <dd class="font-medium tabular-nums text-slate-800">
          {{ resultado.valido ? gramos(resultado.peso_fino_gramos) : '—' }}
        </dd>
      </div>

      <div class="flex justify-between gap-3">
        <dt class="text-slate-500">Valor USD/gramo</dt>
        <dd class="font-medium tabular-nums text-slate-800">
          {{ resultado.valido ? `$ ${numero(resultado.valor_usd_gramo, 6)}` : '—' }}
        </dd>
      </div>

      <div class="border-t border-dashed border-slate-200 pt-2">
        <div class="flex justify-between gap-3">
          <dt class="text-slate-500">Valor bruto</dt>
          <dd class="font-semibold tabular-nums text-slate-800">
            {{ resultado.valido ? bs(resultado.valor_bruto_bs) : '—' }}
          </dd>
        </div>
      </div>

      <div class="flex justify-between gap-3">
        <dt class="text-slate-500">Aporte caja de salud</dt>
        <dd class="tabular-nums text-rose-600">
          {{ resultado.valido ? `- ${bs(resultado.aporte_caja_salud_bs)}` : '—' }}
        </dd>
      </div>

      <div class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-marca-50 px-3 py-2">
        <dt class="text-sm font-medium text-marca-700">Valor neto</dt>
        <dd class="text-base font-bold tabular-nums text-marca-700">
          {{ resultado.valido ? bs(resultado.valor_neto_bs) : '—' }}
        </dd>
      </div>
    </dl>

    <p v-if="ley" class="mt-3 text-xs text-slate-500">Ley pagable: {{ ley }} %</p>
  </div>
</template>

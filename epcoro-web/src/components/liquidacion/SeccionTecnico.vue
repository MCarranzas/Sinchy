<script setup lang="ts">
/**
 * Seccion Tecnico (8.6): presentacion del mineral, peso y ley pagable.
 *
 * La ley se captura en PORCENTAJE con su signo % (93,02); la fraccion que
 * exige el backend (lte:1, trampa T1) la arma el composable al enviar. Van
 * aqui tambien las observaciones, porque son el ultimo dato antes del resumen.
 */
import CampoEntrada from '@/components/CampoEntrada.vue'
import CampoSelect from '@/components/CampoSelect.vue'
import { useCatalogosStore } from '@/stores/catalogos'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'

defineProps<{ formulario: FormularioCaptura }>()

const catalogos = useCatalogosStore()
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
      <span class="flex h-6 w-6 items-center justify-center rounded-full bg-marca-100 text-xs text-marca-700">
        5
      </span>
      Técnico
    </h2>

    <div class="grid gap-4 sm:grid-cols-3">
      <CampoSelect
        v-bind="formulario.enlazar('presentacion_mineral_id')"
        etiqueta="Presentación del mineral *"
        :opciones="catalogos.presentaciones"
        :error="formulario.error('presentacion_mineral_id')"
        @blur="formulario.tocar('presentacion_mineral_id')"
      />

      <CampoEntrada
        v-bind="formulario.enlazar('peso_gramos')"
        etiqueta="Peso (g) *"
        tipo="number"
        step="0.01"
        placeholder="1944.53"
        :error="formulario.error('peso_gramos')"
        @blur="formulario.tocar('peso_gramos')"
      />

      <CampoEntrada
        v-bind="formulario.enlazar('ley_pagable')"
        etiqueta="Ley pagable %"
        tipo="number"
        step="0.0001"
        sufijo="%"
        placeholder="93.02"
        ayuda="En porcentaje; el backend recibe la fracción."
        :error="formulario.error('ley_pagable')"
        @blur="formulario.tocar('ley_pagable')"
      />
    </div>

    <!-- ------------------------------------------------ observaciones -->
    <div class="mt-5">
      <label for="observaciones" class="mb-1 block text-sm font-medium text-slate-700">
        Observaciones
      </label>
      <textarea
        id="observaciones"
        v-model="formulario.valores.observaciones"
        rows="3"
        maxlength="5000"
        placeholder="Notas de la captura (máx. 5000 caracteres)"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-100"
        :class="formulario.error('observaciones') ? 'border-rose-400' : ''"
        @blur="formulario.tocar('observaciones')"
      />
      <p v-if="formulario.error('observaciones')" class="mt-1 text-xs text-rose-600">
        {{ formulario.error('observaciones') }}
      </p>
      <p v-else class="mt-1 text-xs text-slate-500">
        {{ formulario.valores.observaciones.length }}/5000
      </p>
    </div>
  </section>
</template>

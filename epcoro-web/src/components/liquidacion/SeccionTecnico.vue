<script setup lang="ts">
/**
 * Seccion Tecnico (8.6): presentacion del mineral, peso y ley pagable.
 *
 * La ley se captura en PORCENTAJE con su signo % (93,02); la fraccion que
 * exige el backend (lte:1, trampa T1) la arma el composable al enviar. Van
 * aqui tambien las deducciones variables (columnas H/I del Excel) y las
 * observaciones, porque son los ultimos datos antes del resumen.
 */
import CampoEntrada from '@/components/CampoEntrada.vue'
import CampoSelect from '@/components/CampoSelect.vue'
import { useCatalogosStore } from '@/stores/catalogos'
import { bs } from '@/utils/formato'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'

const props = defineProps<{ formulario: FormularioCaptura }>()

const catalogos = useCatalogosStore()

function errorDe(indice: number, campo: 'concepto' | 'monto'): string | undefined {
  return props.formulario.errores.value[`deducciones.${indice}.${campo}`]
}
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
        etiqueta="Ley pagable *"
        tipo="number"
        step="0.0001"
        sufijo="%"
        placeholder="93.02"
        ayuda="En porcentaje; el backend recibe la fracción."
        :error="formulario.error('ley_pagable')"
        @blur="formulario.tocar('ley_pagable')"
      />
    </div>

    <!-- ------------------------------------------------- deducciones -->
    <div class="mt-5">
      <div class="mb-2 flex items-center justify-between">
        <h3 class="text-sm font-medium text-slate-700">Deducciones variables</h3>
        <button
          type="button"
          class="text-sm font-medium text-marca-600 transition-colors hover:text-marca-700"
          @click="formulario.agregarDeduccion()"
        >
          + Agregar deducción
        </button>
      </div>

      <div
        v-for="(fila, indice) in formulario.deducciones"
        :key="indice"
        class="mb-2 grid grid-cols-1 items-start gap-2 sm:grid-cols-[1fr_10rem_2.5rem]"
      >
        <CampoEntrada
          v-model="fila.concepto"
          etiqueta="Concepto"
          placeholder="Ej.: Comisión de refinería"
          maxlength="100"
          :error="errorDe(indice, 'concepto')"
        />

        <CampoEntrada
          v-model="fila.monto"
          etiqueta="Monto (Bs)"
          tipo="number"
          step="0.01"
          placeholder="0.00"
          :error="errorDe(indice, 'monto')"
        />

        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 text-slate-400 transition-colors hover:border-rose-300 hover:text-rose-500 sm:mt-7"
          aria-label="Quitar deducción"
          @click="formulario.quitarDeduccion(indice)"
        >
          ✕
        </button>
      </div>

      <p v-if="!formulario.resultado.value.valido && formulario.deducciones.length > 1" class="text-xs text-slate-400">
        Filas vacías se ignoran al guardar.
      </p>
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

    <p class="mt-4 text-xs text-slate-400">
      El monto total de deducciones se ve en el resumen: {{ bs(formulario.resultado.value.total_deducciones_bs) }}
    </p>
  </section>
</template>

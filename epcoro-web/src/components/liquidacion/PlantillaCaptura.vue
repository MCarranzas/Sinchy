<script setup lang="ts">
/**
 * Plantilla de captura/edicion (8.1, 8.8): el esqueleto comun de las vistas
 * de alta y edicion de una liquidacion.
 *
 * Dos anchos con UN solo conjunto de secciones:
 *   - escritorio (>= 1024px): las cinco secciones seguidas y el resumen fijo
 *     en la columna derecha;
 *   - celular: wizard de cinco pasos; cada paso se valida al avanzar y el
 *     resumen vive debajo del paso actual.
 */
import { useMediaQuery } from '@vueuse/core'

import ResumenCalculo from '@/components/liquidacion/ResumenCalculo.vue'
import SeccionComprador from '@/components/liquidacion/SeccionComprador.vue'
import SeccionProcedencia from '@/components/liquidacion/SeccionProcedencia.vue'
import SeccionTecnico from '@/components/liquidacion/SeccionTecnico.vue'
import SeccionTransaccion from '@/components/liquidacion/SeccionTransaccion.vue'
import SeccionVendedor from '@/components/liquidacion/SeccionVendedor.vue'
import { PASOS, type FormularioCaptura } from '@/composables/useLiquidacionForm'

const props = withDefaults(
  defineProps<{
    formulario: FormularioCaptura
    titulo: string
    descripcion: string
    etiquetaBoton?: string
  }>(),
  { etiquetaBoton: 'Guardar liquidación' },
)

const esMovil = useMediaQuery('(max-width: 1023px)')

const SECCIONES = [
  SeccionTransaccion,
  SeccionComprador,
  SeccionVendedor,
  SeccionProcedencia,
  SeccionTecnico,
] as const

async function avanzar(): Promise<void> {
  if (await props.formulario.validarPaso()) {
    props.formulario.pasoActual.value = Math.min(
      props.formulario.pasoActual.value + 1,
      PASOS.length - 1,
    )
  }
}

async function irA(indice: number): Promise<void> {
  await props.formulario.irAPaso(indice)
}

async function guardar(): Promise<void> {
  props.formulario.intentado.value = true
  await props.formulario.enviar()
}
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div class="mb-4">
      <h1 class="text-lg font-semibold text-slate-800 lg:text-xl">{{ titulo }}</h1>
      <p class="text-sm text-slate-500">{{ descripcion }}</p>
    </div>

    <!-- Indicador de pasos: solo en celular. -->
    <nav v-if="esMovil" class="mb-4 flex items-center gap-1" aria-label="Pasos del formulario">
      <button
        v-for="(paso, indice) in PASOS"
        :key="paso.id"
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold transition-colors"
        :class="
          indice === formulario.pasoActual.value
            ? 'bg-marca-600 text-white'
            : indice < formulario.pasoActual.value
              ? 'bg-emerald-100 text-emerald-700'
              : 'bg-slate-200 text-slate-500'
        "
        :aria-current="indice === formulario.pasoActual.value ? 'step' : undefined"
        @click="irA(indice)"
      >
        {{ indice < formulario.pasoActual.value ? '✓' : indice + 1 }}
      </button>
      <span class="ml-2 text-xs font-medium text-slate-500">
        {{ PASOS[formulario.pasoActual.value].titulo }}
      </span>
    </nav>

    <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
      <!-- ---------------------------------------------------- formulario -->
      <div class="space-y-4">
        <template v-if="!esMovil">
          <component
            :is="SECCIONES[indice]"
            v-for="(_, indice) in PASOS"
            :key="indice"
            :formulario="formulario"
          />
        </template>

        <component :is="SECCIONES[formulario.pasoActual.value]" v-else :formulario="formulario" />

        <!-- Resumen compacto en celular, debajo del paso actual. -->
        <div v-if="esMovil" class="lg:hidden">
          <ResumenCalculo :resultado="formulario.resultado.value" :ley="formulario.valores.ley_pagable" />
        </div>

        <!-- Navegacion -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button
            v-if="esMovil && formulario.pasoActual.value > 0"
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            @click="formulario.pasoActual.value = formulario.pasoActual.value - 1"
          >
            Anterior
          </button>

          <button
            v-if="esMovil && formulario.pasoActual.value < PASOS.length - 1"
            type="button"
            class="ml-auto rounded-lg bg-marca-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700"
            @click="avanzar"
          >
            Siguiente
          </button>

          <button
            v-if="!esMovil || formulario.pasoActual.value === PASOS.length - 1"
            type="button"
            :disabled="formulario.enviando.value"
            class="ml-auto flex items-center gap-2 rounded-lg bg-marca-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700 disabled:opacity-60"
            @click="guardar"
          >
            {{ formulario.enviando.value ? 'Guardando…' : etiquetaBoton }}
          </button>
        </div>

        <p
          v-if="formulario.mensajeError.value && Object.keys(formulario.errores.value).length === 0"
          class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
        >
          {{ formulario.mensajeError.value }}
        </p>
      </div>

      <!-- --------------------------------------------------------- resumen -->
      <aside class="hidden lg:block">
        <div class="sticky top-24 space-y-4">
          <ResumenCalculo :resultado="formulario.resultado.value" :ley="formulario.valores.ley_pagable" />
        </div>
      </aside>
    </div>
  </div>
</template>
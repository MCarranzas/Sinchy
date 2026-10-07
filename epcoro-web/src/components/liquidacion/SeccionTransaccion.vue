<script setup lang="ts">
/**
 * Seccion Transaccion (8.2): numero de documento, fecha, tipo de cambio y
 * cotizacion quincenal.
 *
 * La cotizacion se SUGIERE desde /cotizaciones (la mas reciente primero) y
 * el usuario puede cambiarla en el select o escribirla a mano: en los dos
 * casos el que viaja al backend es `cotizacion_usd_oz_t`, con su
 * `cotizacion_id` cuando vino del catalogo. El tipo de cambio arranca con
 * TIPO_CAMBIO_DEFECTO (parametros, celda F6 de hoja.xlsx).
 */
import { computed, ref, watch } from 'vue'

import CampoEntrada from '@/components/CampoEntrada.vue'
import CampoSelect from '@/components/CampoSelect.vue'
import { useCatalogosStore } from '@/stores/catalogos'
import { numero } from '@/utils/formato'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'
import type { CotizacionQuincenal } from '@/types/catalogos'

const props = defineProps<{ formulario: FormularioCaptura }>()

const catalogos = useCatalogosStore()

const opcionesCotizacion = computed(() =>
  catalogos.cotizaciones.map((cotizacion: CotizacionQuincenal) => ({
    id: cotizacion.id,
    nombre: `${cotizacion.quincena}ª quincena ${cotizacion.ejercicio} — ${numero(cotizacion.valor_usd_oz_t, 4)} USD/oz`,
  })),
)

/** Id seleccionado en el select: el de la ultima cotizacion elegida. */
const seleccion = ref<number | null>(null)

/**
 * Elegir una quincena rellena su valor en el input y deja el id para que el
 * backend sepa de donde vino. Se observa el ref y no se escucha `change`
 * para no depender del orden entre el v-model interno del select y el
 * listener heredado.
 */
watch(seleccion, (id) => {
  const cotizacion = catalogos.cotizaciones.find((c) => c.id === id)

  if (cotizacion) {
    props.formulario.asignar('cotizacion_usd_oz_t', cotizacion.valor_usd_oz_t)
    props.formulario.asignar('cotizacion_id', cotizacion.id)
  }
})
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
      <span class="flex h-6 w-6 items-center justify-center rounded-full bg-marca-100 text-xs text-marca-700">
        1
      </span>
      Transacción
    </h2>

    <div class="grid gap-4 sm:grid-cols-2">
      <CampoEntrada
        v-bind="formulario.enlazar('numero_documento')"
        etiqueta="Número de documento"
        placeholder="Vacío = correlativo del ejercicio"
        ayuda="Formato EP-BCB-nnn. Si se deja vacío lo asigna el numerador."
        maxlength="50"
        :error="formulario.error('numero_documento')"
        @blur="formulario.tocar('numero_documento')"
      />

      <CampoEntrada
        v-bind="formulario.enlazar('fecha_transaccion')"
        etiqueta="Fecha de transacción *"
        tipo="date"
        :error="formulario.error('fecha_transaccion')"
        @blur="formulario.tocar('fecha_transaccion')"
      />

      <CampoEntrada
        v-bind="formulario.enlazar('tipo_cambio')"
        etiqueta="Tipo de cambio (Bs/$) *"
        tipo="number"
        step="0.0001"
        placeholder="6.96"
        ayuda="Sugerido por parámetros; editable."
        :error="formulario.error('tipo_cambio')"
        @blur="formulario.tocar('tipo_cambio')"
      />

      <CampoSelect
        v-model="seleccion"
        etiqueta="Cotización quincenal (sugerida)"
        :opciones="opcionesCotizacion"
        placeholder="Sin cotización del catálogo"
      />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <CampoEntrada
        v-bind="formulario.enlazar('cotizacion_usd_oz_t')"
        etiqueta="Cotización (USD/oz t) *"
        tipo="number"
        step="0.0001"
        placeholder="2648.5700"
        ayuda="Rellena sola al elegir la quincena; también se puede escribir."
        :error="formulario.error('cotizacion_usd_oz_t')"
        @blur="formulario.tocar('cotizacion_usd_oz_t')"
      />

      <label class="flex items-center gap-3 self-end rounded-lg border border-slate-200 px-3 py-2.5">
        <input
          v-model="formulario.valores.aplica_caja_salud"
          type="checkbox"
          class="h-4 w-4 rounded border-slate-300 text-marca-600 focus:ring-marca-500"
          @change="formulario.tocar('aplica_caja_salud')"
        />
        <span class="text-sm text-slate-700">
          Aplica aporte de caja de salud
          <span class="block text-xs text-slate-500">1,8 % sobre el bruto (Ley 649)</span>
        </span>
      </label>
    </div>
  </section>
</template>

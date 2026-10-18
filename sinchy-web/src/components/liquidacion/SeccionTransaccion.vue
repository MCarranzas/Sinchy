<script setup lang="ts">
/**
 * Seccion Transaccion (8.2): numero de documento, fecha, tipo de cambio y
 * cotizacion quincenal.
 *
 * La cotizacion se SUGIERE desde /cotizaciones (la mas reciente primero) y
 * el usuario puede cambiarla en el select o escribirla a mano: en los dos
 * casos el que viaja al backend es `cotizacion_usd_oz_t`, con su
 * `cotizacion_id` cuando vino del catalogo.
 *
 * El tipo de cambio es de SOLO LECTURA: lo resuelve el backend por la fecha
 * de la transaccion y aqui solo se muestra el valor oficial del dia. Si esa
 * fecha no tiene valor cargado, se avisa y el alta queda bloqueada.
 */
import { computed, ref, watch } from 'vue'

import CampoEntrada from '@/components/CampoEntrada.vue'
import CampoSelect from '@/components/CampoSelect.vue'
import { useCatalogosStore } from '@/stores/catalogos'
import { aDosDecimales } from '@/utils/calculo'
import { numero } from '@/utils/formato'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'
import type { CotizacionQuincenal } from '@/types/catalogos'

const props = defineProps<{ formulario: FormularioCaptura }>()

const catalogos = useCatalogosStore()

const opcionesCotizacion = computed(() =>
  catalogos.cotizaciones.map((cotizacion: CotizacionQuincenal) => ({
    id: cotizacion.id,
    nombre: `${cotizacion.quincena}ª quincena ${cotizacion.ejercicio} — ${numero(cotizacion.valor_usd_oz_t)} USD/oz`,
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
    props.formulario.asignar('cotizacion_usd_oz_t', aDosDecimales(cotizacion.valor_usd_oz_t))
    props.formulario.asignar('cotizacion_id', cotizacion.id)
  }
})

/**
 * El checkbox es un control NATIVO, y `formulario.valores` es de solo lectura
 * (vee-validate expone `readonly(formValues)`): un `v-model` directo ahi se
 * descarta en silencio y el aporte quedaba siempre aplicado. El computado
 * escribible encamina el cambio por `asignar()` -> `setFieldValue`.
 */
const aplicaCajaSalud = computed({
  get: () => props.formulario.valores.aplica_caja_salud,
  set: (valor: boolean) => props.formulario.asignar('aplica_caja_salud', valor),
})

const tipoCambioAyuda = computed<string | undefined>(() => {
  if (props.formulario.tipoCambioCargando.value) {
    return 'Consultando el tipo de cambio del día…'
  }

  return props.formulario.tipoCambioDisponible.value
    ? 'Valor oficial del día (lo resuelve el sistema).'
    : undefined
})

// El error solo se muestra cuando ya se intento avanzar o guardar
// (tocado/intentado): al abrir no se pinta nada en rojo.
const tipoCambioError = computed<string | undefined>(() =>
  props.formulario.tipoCambioCargando.value
    ? undefined
    : props.formulario.error('tipo_cambio'),
)
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
        etiqueta="Tipo de cambio (Bs/$)"
        tipo="number"
        step="0.01"
        placeholder="—"
        disabled
        :ayuda="tipoCambioAyuda"
        :error="tipoCambioError"
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
        step="0.01"
        placeholder="2648.57"
        ayuda="Rellena sola al elegir la quincena; también se puede escribir."
        :error="formulario.error('cotizacion_usd_oz_t')"
        @blur="formulario.tocar('cotizacion_usd_oz_t')"
      />

      <label class="flex items-center gap-3 self-end rounded-lg border border-slate-200 px-3 py-2.5">
        <input
          v-model="aplicaCajaSalud"
          type="checkbox"
          class="h-4 w-4 rounded border-slate-300 text-marca-600 focus:ring-marca-500"
        />
        <span class="text-sm text-slate-700">
          Aplica aporte de caja de salud
          <span class="block text-xs text-slate-500">1,8 % sobre el bruto (Ley 649)</span>
        </span>
      </label>
    </div>
  </section>
</template>

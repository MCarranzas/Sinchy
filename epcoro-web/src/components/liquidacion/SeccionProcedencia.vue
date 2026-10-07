<script setup lang="ts">
/**
 * Seccion Procedencia (8.5): buscador de area minera con autocompletado de
 * los CUATRO campos, mas el SelectorUbicacion para ajuste manual.
 *
 * El backend exige que los cuatro niveles coincidan con el area elegida
 * (trait ValidaUbicacionDelArea), asi que elegir el area rellena la cascada
 * entera; si el usuario cambia un nivel a mano, la validacion del servidor
 * dira si no cuadra. Los codigos municipial y AJAM tambien se copian del
 * catalogo (con su alternativa manual si el catalogo no los trae).
 */
import { computed, ref } from 'vue'

import Autocompletar from '@/components/Autocompletar.vue'
import CampoEntrada from '@/components/CampoEntrada.vue'
import SelectorUbicacion from '@/components/liquidacion/SelectorUbicacion.vue'
import { buscarAreasMineras } from '@/api/catalogos.api'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'
import type { AreaMinera } from '@/types/catalogos'
import type { OpcionBusqueda } from '@/components/Autocompletar.vue'

const props = defineProps<{ formulario: FormularioCaptura }>()

const opciones = ref<OpcionBusqueda[]>([])
const areas = ref<AreaMinera[]>([])
const cargando = ref(false)

const area = computed(
  () => areas.value.find((a) => a.id === props.formulario.valores.area_minera_id) ?? null,
)

const textoArea = computed(() => area.value?.nombre_area_minera ?? '')

async function buscar(texto: string): Promise<void> {
  cargando.value = true

  try {
    areas.value = await buscarAreasMineras(texto || undefined)
    opciones.value = areas.value.map((a) => ({
      id: a.id,
      titulo: a.nombre_area_minera,
      detalle: `${a.titular_derecho_minero} · ${a.ubicacion.municipio_nombre}, ${a.ubicacion.departamento_nombre}`,
    }))
  } finally {
    cargando.value = false
  }
}

/** Autocompleta los cuatro niveles + codigos desde el area elegida. */
function alElegir(opcion: OpcionBusqueda): void {
  const elegida = areas.value.find((a) => a.id === opcion.id)

  if (!elegida) {
    return
  }

  const { formulario } = props
  const u = elegida.ubicacion

  formulario.asignar('area_minera_id', elegida.id)
  formulario.asignar('departamento_id', u.departamento_id)
  formulario.asignar('provincia_id', u.provincia_id)
  formulario.asignar('municipio_id', u.municipio_id)
  formulario.asignar('localidad_id', u.localidad_id)
  formulario.asignar('codigo_municipio', u.codigo_municipio ?? '')
  formulario.asignar('codigo_ajam_patente', elegida.codigo_ajam_patente ?? '')
}
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
      <span class="flex h-6 w-6 items-center justify-center rounded-full bg-marca-100 text-xs text-marca-700">
        4
      </span>
      Procedencia
    </h2>

    <Autocompletar
      v-bind="formulario.enlazar('area_minera_id')"
      etiqueta="Área minera *"
      :opciones="opciones"
      :texto="textoArea"
      :cargando="cargando"
      :error="formulario.error('area_minera_id')"
      placeholder="Nombre del área o titular del derecho minero…"
      ayuda="Al elegirla se completan los cuatro niveles y los códigos."
      @buscar="buscar"
      @seleccion="alElegir"
      @blur="formulario.tocar('area_minera_id')"
    />

    <!-- Ficha del area elegida: para verificar de un vistazo que es la buena. -->
    <div
      v-if="area"
      class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"
    >
      <span class="font-medium text-slate-700">{{ area.titular_derecho_minero }}</span>
      · {{ area.ubicacion.localidad_nombre }}, {{ area.ubicacion.municipio_nombre }},
      {{ area.ubicacion.provincia_nombre }}, {{ area.ubicacion.departamento_nombre }}
      <span v-if="area.codigo_ajam_patente"> · AJAM {{ area.codigo_ajam_patente }}</span>
    </div>

    <div class="mt-4">
      <SelectorUbicacion :formulario="formulario" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <CampoEntrada
        v-bind="formulario.enlazar('codigo_municipio')"
        etiqueta="Código de municipio"
        placeholder="Solo dígitos"
        maxlength="10"
        ayuda="Se completa desde el área; editable si el Excel lo trae distinto."
        :error="formulario.error('codigo_municipio')"
        @blur="formulario.tocar('codigo_municipio')"
      />

      <CampoEntrada
        v-bind="formulario.enlazar('codigo_ajam_patente')"
        etiqueta="Patente AJAM"
        placeholder="Solo dígitos"
        maxlength="20"
        ayuda="Se completa desde el área."
        :error="formulario.error('codigo_ajam_patente')"
        @blur="formulario.tocar('codigo_ajam_patente')"
      />
    </div>
  </section>
</template>

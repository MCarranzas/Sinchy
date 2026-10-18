<script setup lang="ts">
/**
 * Cascada de 4 selects: departamento -> provincia -> municipio ->
 * localidad (8.5).
 *
 * La carga del hijo la dispara el padre de su nivel. Al cambiar un nivel se
 * limpia lo que cuelga de el que YA NO PERTENEZCA (un municipio de otra
 * provincia no puede sobrevivir), pero se conserva lo valido: al elegir un
 * area minera se rellenan los cuatro niveles de una sola vez y el watch del
 * departamento NO debe borrar la provincia/municipio/localidad que acaban de
 * llegar con ella. Antes si lo hacia y el autocompletado quedaba a medias.
 */
import { onMounted, ref, watch } from 'vue'

import CampoSelect from '@/components/CampoSelect.vue'
import { listarLocalidades, listarMunicipios, listarProvincias } from '@/api/catalogos.api'
import { useCatalogosStore } from '@/stores/catalogos'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'
import type { Localidad, Municipio, Provincia } from '@/types/catalogos'

const props = defineProps<{ formulario: FormularioCaptura }>()

const catalogos = useCatalogosStore()

const provincias = ref<Provincia[]>([])
const municipios = ref<Municipio[]>([])
const localidades = ref<Localidad[]>([])
const cargandoProvincias = ref(false)
const cargandoMunicipios = ref(false)
const cargandoLocalidades = ref(false)

const valores = props.formulario.valores

/** Baja la cascada desde provincia: sin departamento valido no hay nada que cuelgue. */
function limpiarDesdeProvincia(): void {
  props.formulario.asignar('provincia_id', null)
  props.formulario.asignar('municipio_id', null)
  props.formulario.asignar('localidad_id', null)
}

/** Baja la cascada desde municipio. */
function limpiarDesdeMunicipio(): void {
  props.formulario.asignar('municipio_id', null)
  props.formulario.asignar('localidad_id', null)
}

watch(
  () => valores.departamento_id,
  async (nuevo) => {
    provincias.value = []
    municipios.value = []
    localidades.value = []

    if (!nuevo) {
      limpiarDesdeProvincia()
      return
    }

    cargandoProvincias.value = true
    try {
      provincias.value = await listarProvincias(nuevo)
    } finally {
      cargandoProvincias.value = false
    }

    // Solo se limpia lo que ya no cuelga del departamento nuevo: si la
    // provincia actual sigue perteneciendole (es lo que pasa al
    // autocompletar desde el area minera) se conserva, y con ella sus hijos.
    if (!provincias.value.some((provincia) => provincia.id === valores.provincia_id)) {
      limpiarDesdeProvincia()
    }
  },
  { immediate: true },
)

watch(
  () => valores.provincia_id,
  async (nuevo) => {
    municipios.value = []
    localidades.value = []

    if (!nuevo) {
      limpiarDesdeMunicipio()
      return
    }

    cargandoMunicipios.value = true
    try {
      municipios.value = await listarMunicipios(nuevo)
    } finally {
      cargandoMunicipios.value = false
    }

    if (!municipios.value.some((municipio) => municipio.id === valores.municipio_id)) {
      limpiarDesdeMunicipio()
    }
  },
  { immediate: true },
)

watch(
  () => valores.municipio_id,
  async (nuevo) => {
    localidades.value = []

    if (!nuevo) {
      props.formulario.asignar('localidad_id', null)
      return
    }

    cargandoLocalidades.value = true
    try {
      localidades.value = await listarLocalidades(nuevo)
    } finally {
      cargandoLocalidades.value = false
    }

    if (!localidades.value.some((localidad) => localidad.id === valores.localidad_id)) {
      props.formulario.asignar('localidad_id', null)
    }

    // El codigo de municipio no se captura: se copia del municipio elegido
    // para que viaje en el payload. Solo se toca si el municipio esta en la
    // lista cargada (al editar, la lista llega despues y el codigo ya viene
    // del expediente; no hay que borrarlo).
    const municipioElegido = municipios.value.find((municipio) => municipio.id === nuevo)

    if (municipioElegido) {
      props.formulario.asignar('codigo_municipio', municipioElegido.codigo ?? '')
    }
  },
  { immediate: true },
)

onMounted(() => {
  void catalogos.cargar()
})
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <CampoSelect
      v-bind="formulario.enlazar('departamento_id')"
      etiqueta="Departamento *"
      :opciones="catalogos.departamentos"
      :error="formulario.error('departamento_id')"
      @blur="formulario.tocar('departamento_id')"
    />

    <CampoSelect
      v-bind="formulario.enlazar('provincia_id')"
      etiqueta="Provincia *"
      :opciones="provincias"
      :cargando="cargandoProvincias"
      :disabled="!valores.departamento_id"
      placeholder="Elija un departamento primero"
      :error="formulario.error('provincia_id')"
      @blur="formulario.tocar('provincia_id')"
    />

    <CampoSelect
      v-bind="formulario.enlazar('municipio_id')"
      etiqueta="Municipio *"
      :opciones="municipios"
      :cargando="cargandoMunicipios"
      :disabled="!valores.provincia_id"
      placeholder="Elija una provincia primero"
      :error="formulario.error('municipio_id')"
      @blur="formulario.tocar('municipio_id')"
    />

    <CampoSelect
      v-bind="formulario.enlazar('localidad_id')"
      etiqueta="Localidad *"
      :opciones="localidades"
      :cargando="cargandoLocalidades"
      :disabled="!valores.municipio_id"
      placeholder="Elija un municipio primero"
      :error="formulario.error('localidad_id')"
      @blur="formulario.tocar('localidad_id')"
    />
  </div>
</template>

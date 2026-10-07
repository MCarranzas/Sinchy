<script setup lang="ts">
/**
 * Cascada de 4 selects: departamento -> provincia -> municipio ->
 * localidad (8.5).
 *
 * La carga del hijo la dispara el padre de su nivel, y al cambiar un nivel
 * se limpia todo lo que cuelga de él: un municipio elegido en otra provincia
 * no puede sobrevivir. En modo edicion los cuatro ids llegan rellenados de
 * una vez (viene del area minera) y cada watch solo CARGA la lista de su
 * nivel sin tocar la seleccion.
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

watch(
  () => valores.departamento_id,
  async (nuevo, viejo) => {
    if (viejo !== undefined && nuevo !== viejo) {
      props.formulario.asignar('provincia_id', null)
      props.formulario.asignar('municipio_id', null)
      props.formulario.asignar('localidad_id', null)
    }

    provincias.value = []
    municipios.value = []
    localidades.value = []

    if (!nuevo) {
      return
    }

    cargandoProvincias.value = true
    try {
      provincias.value = await listarProvincias(nuevo)
    } finally {
      cargandoProvincias.value = false
    }
  },
  { immediate: true },
)

watch(
  () => valores.provincia_id,
  async (nuevo, viejo) => {
    if (viejo !== undefined && viejo !== null && nuevo !== viejo) {
      props.formulario.asignar('municipio_id', null)
      props.formulario.asignar('localidad_id', null)
    }

    municipios.value = []
    localidades.value = []

    if (!nuevo) {
      return
    }

    cargandoMunicipios.value = true
    try {
      municipios.value = await listarMunicipios(nuevo)
    } finally {
      cargandoMunicipios.value = false
    }
  },
  { immediate: true },
)

watch(
  () => valores.municipio_id,
  async (nuevo, viejo) => {
    if (viejo !== undefined && viejo !== null && nuevo !== viejo) {
      props.formulario.asignar('localidad_id', null)
    }

    localidades.value = []

    if (!nuevo) {
      return
    }

    cargandoLocalidades.value = true
    try {
      localidades.value = await listarLocalidades(nuevo)
    } finally {
      cargandoLocalidades.value = false
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

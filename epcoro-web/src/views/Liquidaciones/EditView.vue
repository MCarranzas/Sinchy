<script setup lang="ts">
/**
 * Edicion de una liquidacion creada o en correccion (reutiliza la FASE 8).
 *
 * Mismo formulario que el alta: al cargar el registro se vuelcan sus valores
 * al formulario (la ley, fraccion en la base, se muestra de nuevo en
 * porcentaje) y el envio hace PUT. La cascada de ubicacion precarga al
 * llegar, porque los cuatro niveles vienen del area minera guardada.
 */
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import PlantillaCaptura from '@/components/liquidacion/PlantillaCaptura.vue'
import { actualizarLiquidacion, obtenerLiquidacion } from '@/api/liquidaciones.api'
import { mensajeDeError } from '@/api/axios'
import {
  useLiquidacionForm,
  valoresDesdeLiquidacion,
} from '@/composables/useLiquidacionForm'
import { useCatalogosStore } from '@/stores/catalogos'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const catalogos = useCatalogosStore()
const cargando = ref(true)
const errorDeCarga = ref('')

const formulario = useLiquidacionForm({
  guardar: async (payload) => {
    const liquidacion = await actualizarLiquidacion(id, payload)
    await router.push({ name: 'liquidaciones-detalle', params: { id: liquidacion.id } })
    return liquidacion
  },
})

onMounted(async () => {
  try {
    const [liquidacion] = await Promise.all([obtenerLiquidacion(id), catalogos.cargar()])

    const valores = valoresDesdeLiquidacion(liquidacion)

    // Vuelca el registro al formulario. La cascada de SelectorUbicacion se
    // entera por los watch y precarga cada nivel sin tocar la seleccion.
    for (const [campo, valor] of Object.entries(valores)) {
      formulario.asignar(campo as keyof typeof valores, valor)
    }

    formulario.aplicarDefaults()
  } catch (fallo) {
    errorDeCarga.value = mensajeDeError(fallo)
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <div v-if="cargando" class="mx-auto max-w-6xl p-8 text-center text-sm text-slate-500">
    Cargando…
  </div>

  <p
    v-else-if="errorDeCarga"
    class="mx-auto max-w-6xl rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
  >
    {{ errorDeCarga }}
  </p>

  <PlantillaCaptura
    v-else
    :formulario="formulario"
    titulo="Editar liquidación"
    descripcion="Los cambios se guardan con PUT; el cálculo lo vuelve a calcular el backend."
    etiqueta-boton="Guardar cambios"
  />
</template>
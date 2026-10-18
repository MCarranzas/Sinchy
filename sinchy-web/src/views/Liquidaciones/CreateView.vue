<script setup lang="ts">
/**
 * Captura de una liquidacion (8.1, 8.8 y criterio de la FASE 8).
 *
 * La logica de formulario vive en useLiquidacionForm (registro de campos,
 * reglas espejo del backend, wizard y previsualizacion del calculo) y el
 * esqueleto responsive en PlantillaCaptura: aqui solo se construye el "
 * guardar" de este caso y se aplican los defaults.
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

import PlantillaCaptura from '@/components/liquidacion/PlantillaCaptura.vue'
import { crearLiquidacion } from '@/api/liquidaciones.api'
import { useLiquidacionForm } from '@/composables/useLiquidacionForm'
import { useCatalogosStore } from '@/stores/catalogos'

const router = useRouter()
const catalogos = useCatalogosStore()

const formulario = useLiquidacionForm({
  guardar: async (payload) => {
    const liquidacion = await crearLiquidacion(payload)
    await router.push({ name: 'liquidaciones-detalle', params: { id: liquidacion.id } })
    return liquidacion
  },
})

onMounted(async () => {
  await catalogos.cargar()
  formulario.aplicarDefaults()
  // El tipo de cambio no se captura: se trae el oficial de la fecha por defecto.
  await formulario.cargarTipoCambioDelDia()
})
</script>

<template>
  <PlantillaCaptura
    :formulario="formulario"
    titulo="Nueva liquidación"
    descripcion="Los campos con * son obligatorios. El cálculo se previsualiza en vivo."
    etiqueta-boton="Guardar liquidación"
  />
</template>
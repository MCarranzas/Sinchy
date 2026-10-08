<script setup lang="ts">
/**
 * Grafico de dona sobre Chart.js (10.4), para la distribucion por estado.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

interface DatosDona {
  etiquetas: string[]
  valores: number[]
}

const props = defineProps<{
  datos: DatosDona
  etiqueta?: string
}>()

const PALETA = ['#1d4ed8', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#f43f5e', '#84cc16']

const lienzo = ref<HTMLCanvasElement | null>(null)
let grafico: Chart | null = null

function pintar(): void {
  if (!lienzo.value) {
    return
  }

  grafico?.destroy()

  grafico = new Chart(lienzo.value, {
    type: 'doughnut',
    data: {
      labels: props.datos.etiquetas,
      datasets: [
        {
          label: props.etiqueta ?? 'Liquidaciones',
          data: props.datos.valores,
          backgroundColor: PALETA,
          borderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '62%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 12 },
        },
      },
    },
  })
}

watch(() => props.datos, pintar, { deep: true })
onMounted(pintar)
onBeforeUnmount(() => grafico?.destroy())
</script>

<template>
  <div class="relative h-72">
    <canvas ref="lienzo" />
  </div>
</template>
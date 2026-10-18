<script setup lang="ts">
/**
 * Grafico de barras sobre Chart.js (10.4). Componente tonto: recibe los
 * datos ya calculados por la vista y se limita a pintarlos. La vista le manda
 * numeros, porque Chart.js no entiende las cadenas decimales del backend.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

interface DatosBarras {
  etiquetas: string[]
  valores: number[]
}

const props = defineProps<{
  datos: DatosBarras
  etiqueta?: string
  color?: string
}>()

const lienzo = ref<HTMLCanvasElement | null>(null)
let grafico: Chart | null = null

function pintar(): void {
  if (!lienzo.value) {
    return
  }

  grafico?.destroy()

  grafico = new Chart(lienzo.value, {
    type: 'bar',
    data: {
      labels: props.datos.etiquetas,
      datasets: [
        {
          label: props.etiqueta ?? 'Valor neto (Bs)',
          data: props.datos.valores,
          backgroundColor: props.color ?? '#1d4ed8',
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
      },
      scales: {
        y: { beginAtZero: true },
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
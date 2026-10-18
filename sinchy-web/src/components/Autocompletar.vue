<script lang="ts">
export interface OpcionBusqueda {
  id: number
  titulo: string
  detalle?: string
}
</script>

<script setup lang="ts">
/**
 * Buscador con autocompletado (8.4 vendedor, 8.5 area minera).
 *
 * El filtrado lo manda el padre: aqui solo vive el input, el desplegable y
 * el rebote de `buscar(query)` con 300 ms de espera, para no martillar la
 * API en cada tecla. Al elegir una opcion se emite `seleccion` con el objeto
 * completo: el padre es quien sabe que con el vendedor hay que copiar su CI
 * y con el area hay que autocompletar los cuatro niveles.
 */
import { ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    etiqueta: string
    opciones: OpcionBusqueda[]
    texto: string
    cargando?: boolean
    error?: string
    placeholder?: string
    ayuda?: string
    disabled?: boolean
    etiquetaVacio?: string
    /** Solo para v-model desde el padre: el desplegable no lo necesita. */
    modelValue?: number | null
  }>(),
  {
    cargando: false,
    error: undefined,
    placeholder: 'Escriba para buscar…',
    ayuda: undefined,
    disabled: false,
    etiquetaVacio: 'Sin resultados',
  },
)

const emit = defineEmits<{
  'update:modelValue': [id: number | null]
  buscar: [consulta: string]
  seleccion: [opcion: OpcionBusqueda]
  blur: []
}>()

const consulta = ref('')
const abierto = ref(false)
const resaltado = ref(-1)

let temporizador: ReturnType<typeof setTimeout> | null = null

watch(consulta, (texto) => {
  if (temporizador !== null) {
    clearTimeout(temporizador)
  }

  temporizador = setTimeout(() => {
    emit('buscar', texto.trim())
  }, 300)
})

function elegir(opcion: OpcionBusqueda): void {
  emit('update:modelValue', opcion.id)
  emit('seleccion', opcion)
  consulta.value = ''
  abierto.value = false
  resaltado.value = -1
}

function tecla(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    abierto.value = true
    resaltado.value = Math.min(resaltado.value + 1, props.opciones.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    resaltado.value = Math.max(resaltado.value - 1, 0)
  } else if (event.key === 'Enter' && abierto.value && props.opciones[resaltado.value]) {
    event.preventDefault()
    elegir(props.opciones[resaltado.value])
  } else if (event.key === 'Escape') {
    abierto.value = false
  }
}

function perderFoco(): void {
  // Deja tiempo a que el click del desplegable llegue antes de cerrarlo.
  setTimeout(() => {
    abierto.value = false
    consulta.value = ''
    emit('blur')
  }, 180)
}

const uid = `busqueda-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <div class="relative">
    <label :for="uid" class="mb-1 block text-sm font-medium text-slate-700">
      {{ etiqueta }}
    </label>

    <input
      :id="uid"
      v-model="consulta"
      type="text"
      role="combobox"
      :placeholder="texto ? '' : placeholder"
      :disabled="disabled"
      :aria-expanded="abierto"
      autocomplete="off"
      class="w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500"
      :class="
        error
          ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200'
          : 'border-slate-300 focus:border-marca-500 focus:ring-marca-100'
      "
      :aria-invalid="error ? 'true' : undefined"
      @focus="abierto = true"
      @input="resaltado = -1"
      @keydown="tecla"
      @blur="perderFoco"
    />

    <!-- Lo elegido se ve aunque el input este vacio (placeholder). -->
    <span
      v-if="!consulta && texto"
      class="pointer-events-none absolute left-3 top-[2.15rem] max-w-[calc(100%-2.5rem)] truncate text-sm text-slate-700"
    >
      {{ texto }}
    </span>

    <div
      v-if="abierto && (cargando || opciones.length > 0 || consulta.length > 0)"
      class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
    >
      <p v-if="cargando" class="px-3 py-2 text-sm text-slate-500">Buscando…</p>

      <template v-else>
        <button
          v-for="(opcion, indice) in opciones"
          :key="opcion.id"
          type="button"
          class="flex w-full flex-col px-3 py-2 text-left text-sm transition-colors"
          :class="
            indice === resaltado ? 'bg-marca-50 text-marca-700' : 'text-slate-700 hover:bg-slate-50'
          "
          @mousedown.prevent="elegir(opcion)"
          @mouseenter="resaltado = indice"
        >
          <span class="font-medium">{{ opcion.titulo }}</span>
          <span v-if="opcion.detalle" class="text-xs text-slate-500">{{ opcion.detalle }}</span>
        </button>

        <p v-if="opciones.length === 0" class="px-3 py-2 text-sm text-slate-500">
          {{ etiquetaVacio }}
        </p>
      </template>
    </div>

    <p v-if="error" class="mt-1 text-xs text-rose-600">{{ error }}</p>
    <p v-else-if="ayuda" class="mt-1 text-xs text-slate-500">{{ ayuda }}</p>
  </div>
</template>

<script lang="ts">
export interface OpcionSelect {
  id: number
  nombre: string
}
</script>

<script setup lang="ts">
/**
 * Select estandar del formulario. La lista llega como `{id, nombre}` para
 * que las cinco cascadas de catalogo y las presentaciones se pinten con el
 * mismo componente.
 */
const modelo = defineModel<number | null>({ required: true })

defineEmits<{ blur: [] }>()

withDefaults(
  defineProps<{
    etiqueta: string
    opciones: OpcionSelect[]
    placeholder?: string
    error?: string
    ayuda?: string
    cargando?: boolean
    disabled?: boolean
  }>(),
  {
    placeholder: 'Seleccione…',
    error: undefined,
    ayuda: undefined,
    cargando: false,
    disabled: false,
  },
)

const uid = `select-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <div>
    <label :for="uid" class="mb-1 block text-sm font-medium text-slate-700">
      {{ etiqueta }}
    </label>

    <select
      :id="uid"
      v-model="modelo"
      :disabled="disabled || cargando"
      class="w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500"
      :class="
        error
          ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200'
          : 'border-slate-300 focus:border-marca-500 focus:ring-marca-100'
      "
      :aria-invalid="error ? 'true' : undefined"
      @change="$emit('blur')"
    >
      <option :value="null" disabled>{{ cargando ? 'Cargando…' : placeholder }}</option>
      <option v-for="opcion in opciones" :key="opcion.id" :value="opcion.id">
        {{ opcion.nombre }}
      </option>
    </select>

    <p v-if="error" class="mt-1 text-xs text-rose-600">{{ error }}</p>
    <p v-else-if="ayuda" class="mt-1 text-xs text-slate-500">{{ ayuda }}</p>
  </div>
</template>

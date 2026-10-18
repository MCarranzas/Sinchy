<script setup lang="ts">
/**
 * Campo de texto estandar del formulario: etiqueta, ayuda y error en un
 * solo lugar, para que las cinco secciones se pinten igual y el error
 * siempre se muestre debajo del input (8.9).
 */
const modelo = defineModel<string | number | null>({ required: true })

defineEmits<{ blur: [] }>()

withDefaults(
  defineProps<{
    etiqueta: string
    tipo?: string
    placeholder?: string
    error?: string
    ayuda?: string
    sufijo?: string
    autocomplete?: string
    disabled?: boolean
    step?: string
    maxlength?: number | string
  }>(),
  {
    tipo: 'text',
    placeholder: '',
    error: undefined,
    ayuda: undefined,
    sufijo: undefined,
    autocomplete: undefined,
    disabled: false,
    step: undefined,
    maxlength: undefined,
  },
)

const uid = `campo-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <div>
    <label :for="uid" class="mb-1 block text-sm font-medium text-slate-700">
      {{ etiqueta }}
    </label>

    <div class="relative">
      <input
        :id="uid"
        v-model="modelo"
        :type="tipo"
        :placeholder="placeholder"
        :disabled="disabled"
        :step="step"
        :maxlength="maxlength"
        :autocomplete="autocomplete"
        class="w-full rounded-lg border px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500"
        :class="
          error
            ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200'
            : 'border-slate-300 focus:border-marca-500 focus:ring-marca-100'
        "
        :aria-invalid="error ? 'true' : undefined"
        @blur="$emit('blur')"
      />
      <span
        v-if="sufijo"
        class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400"
      >
        {{ sufijo }}
      </span>
    </div>

    <p v-if="error" class="mt-1 text-xs text-rose-600">{{ error }}</p>
    <p v-else-if="ayuda" class="mt-1 text-xs text-slate-500">{{ ayuda }}</p>
  </div>
</template>

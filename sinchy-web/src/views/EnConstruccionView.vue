<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const titulo = computed(() => (route.meta.titulo as string | undefined) ?? 'Módulo')
const descripcion = computed(
  () => (route.meta.descripcion as string | undefined) ?? '',
)
</script>

<template>
  <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
    <p class="text-sm font-medium uppercase tracking-wide text-marca-600">En construcción</p>
    <h2 class="mt-2 text-xl font-semibold text-slate-800">{{ titulo }}</h2>
    <p v-if="descripcion" class="mx-auto mt-2 max-w-md text-sm text-slate-500">
      {{ descripcion }}
    </p>
    <p class="mt-4 text-sm text-slate-400">
      Este módulo se habilitará en una fase posterior del proyecto.
    </p>
  </div>
</template>
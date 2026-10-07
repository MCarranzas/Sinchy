<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 text-center">
    <p class="text-6xl font-bold text-slate-400">404</p>
    <h1 class="mt-4 text-xl font-semibold text-slate-800">Página no encontrada</h1>
    <p class="mt-2 max-w-md text-sm text-slate-500">
      La dirección a la que intentó acceder no existe o fue movida.
    </p>
    <RouterLink
      to="/"
      class="mt-6 rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700"
    >
      Volver al inicio
    </RouterLink>
  </div>
</template>
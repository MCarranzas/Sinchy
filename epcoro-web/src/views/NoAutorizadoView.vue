<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 text-center">
    <p class="text-6xl font-bold text-marca-600">403</p>
    <h1 class="mt-4 text-xl font-semibold text-slate-800">Acceso denegado</h1>
    <p class="mt-2 max-w-md text-sm text-slate-500">
      Su usuario no tiene permiso para ver esta sección. Si cree que es un error,
      contacte al administrador del sistema.
    </p>
    <RouterLink
      to="/"
      class="mt-6 rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700"
    >
      Volver al inicio
    </RouterLink>
  </div>
</template>
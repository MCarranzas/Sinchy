<script setup lang="ts">
import { computed } from 'vue'
import { RouterView } from 'vue-router'

const version = computed(() => __APP_VERSION__)
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 py-10">
    <div class="w-full max-w-sm">
      <div class="mb-6 text-center">
        <p class="text-2xl font-semibold tracking-tight text-slate-900">SINCHI WAYRA</p>
        <p class="mt-1 text-sm text-slate-500">Sistema de liquidación de minerales</p>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <RouterView />
      </div>

      <p class="mt-6 text-center text-xs text-slate-400">Versión {{ version }}</p>
    </div>
  </div>
</template>
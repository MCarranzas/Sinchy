<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import { menuPrincipal } from '@/navigation/menu'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const modulos = computed(() =>
  menuPrincipal.filter(
    (item) => item.ruta !== '/' && (!item.permiso || auth.puede(item.permiso)),
  ),
)
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-lg font-semibold text-slate-800">
        Bienvenido, {{ auth.usuario?.nombre_completo }}
      </h2>
      <p class="mt-1 text-sm text-slate-500">
        Ha iniciado sesión como
        <span class="font-medium capitalize text-slate-700">{{ auth.rol }}</span>.
        Estos son los módulos disponibles para su rol.
      </p>
    </section>

    <section>
      <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
        Accesos
      </h3>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <RouterLink
          v-for="item in modulos"
          :key="item.ruta"
          :to="item.ruta"
          class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-marca-500 hover:bg-marca-50"
        >
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-marca-100 text-marca-700"
          >
            <AppIcon :nombre="item.icono" :tamanio="20" />
          </span>
          <span class="min-w-0">
            <span class="block font-medium text-slate-800 group-hover:text-marca-700">
              {{ item.titulo }}
            </span>
            <span class="mt-0.5 block text-sm text-slate-500">{{ item.descripcion }}</span>
          </span>
        </RouterLink>
      </div>

      <p v-if="modulos.length === 0" class="text-sm text-slate-500">
        Su rol no tiene módulos operativos asignados.
      </p>
    </section>
  </div>
</template>
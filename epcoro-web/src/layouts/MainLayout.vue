<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core'
import { computed, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import { menuPrincipal } from '@/navigation/menu'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const version = __APP_VERSION__

// En escritorio el panel lateral es fijo; el drawer solo aplica en móvil.
const esEscritorio = useMediaQuery('(min-width: 1024px)')
const menuAbierto = ref(false)

// Si se agranda la ventana con el drawer abierto, se cierra para no quedar
// debajo del panel fijo.
watch(esEscritorio, (escritorio) => {
  if (escritorio) {
    menuAbierto.value = false
  }
})

// Al navegar, el drawer se pliega para dejar ver la nueva vista.
watch(
  () => route.fullPath,
  () => {
    menuAbierto.value = false
  },
)

/**
 * Menu filtrado por permisos: es lo que hace que cada rol vea solo sus
 * modulos. Las entradas sin permiso (Inicio) son visibles para todos.
 */
const items = computed(() => menuPrincipal.filter((item) => auth.puedeAcceder(item.permiso)),)

function estaActivo(ruta: string): boolean {
  return ruta === '/' ? route.path === '/' : route.path.startsWith(ruta)
}

function alternarMenu(): void {
  menuAbierto.value = !menuAbierto.value
}

async function cerrarSesion(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}

const iniciales = computed(() => {
  return (auth.usuario?.nombre_completo ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0]?.toUpperCase() ?? '')
    .join('')
})
</script>

<template>
  <div class="flex min-h-screen bg-slate-100">
    <!-- Panel lateral: fijo en escritorio, drawer superpuesto en móvil. -->
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 transform flex-col bg-marca-900 text-slate-100 transition-transform duration-200 lg:static lg:translate-x-0"
      :class="menuAbierto ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-16 items-center gap-2 border-b border-white/10 px-5">
        <span class="text-lg font-semibold tracking-tight">EPCORO</span>
        <span class="rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-medium text-slate-300">
          v{{ version }}
        </span>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <RouterLink
          v-for="item in items"
          :key="item.ruta"
          :to="item.ruta"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
          :class="
            estaActivo(item.ruta)
              ? 'bg-white/15 text-white'
              : 'text-slate-300 hover:bg-white/10 hover:text-white'
          "
        >
          <AppIcon :nombre="item.icono" :tamanio="18" />
          <span>{{ item.titulo }}</span>
        </RouterLink>
      </nav>
    </aside>

    <!-- Fondo oscurecido del drawer en móvil. -->
    <div
      v-if="menuAbierto"
      class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
      @click="menuAbierto = false"
    />

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-6"
      >
        <button
          type="button"
          class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
          aria-label="Abrir menú"
          @click="alternarMenu"
        >
          <AppIcon nombre="menu" :tamanio="22" />
        </button>

        <h1 class="truncate text-base font-semibold text-slate-800 lg:text-lg">
          {{ route.meta.titulo ?? 'Inicio' }}
        </h1>

        <div class="ml-auto flex items-center gap-3">
          <div class="hidden text-right sm:block">
            <p class="text-sm font-medium leading-tight text-slate-800">
              {{ auth.usuario?.nombre_completo }}
            </p>
            <p class="text-xs capitalize leading-tight text-slate-500">{{ auth.rol }}</p>
          </div>

          <div
            class="flex h-9 w-9 items-center justify-center rounded-full bg-marca-100 text-sm font-semibold text-marca-700"
            aria-hidden="true"
          >
            {{ iniciales }}
          </div>

          <button
            type="button"
            class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100"
            @click="cerrarSesion"
          >
            <AppIcon nombre="salir" :tamanio="18" />
            <span class="hidden sm:inline">Salir</span>
          </button>
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
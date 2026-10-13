<script setup lang="ts">
/**
 * Mi bandeja (9.5).
 *
 * Tres colas que el rol manda a decidir: mis liquidaciones, las que estan en
 * revision y las que esperan aprobacion. Los conteos llegan recortados por rol desde `/bandeja`; las
 * listas usan el mismo recurso `liquidaciones` con un par de filtros, y todo
 * (pestana + pagina) vive en la URL para que un enlace abra la misma vista.
 */
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import BadgeEstado from '@/components/BadgeEstado.vue'
import { mensajeDeError } from '@/api/axios'
import { listarLiquidaciones, obtenerBandeja, type PaginaLiquidaciones } from '@/api/liquidaciones.api'
import { bs, fecha } from '@/utils/formato'
import { useAuthStore } from '@/stores/auth'
import type { BandejaConteos, EstadoLiquidacion, FiltrosLiquidacion } from '@/types/liquidacion'

type IdPestana = 'mis_liquidaciones' | 'por_revisar' | 'por_aprobar'

const VALIDAS: IdPestana[] = ['mis_liquidaciones', 'por_revisar', 'por_aprobar']

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const cargando = ref(false)
const error = ref('')
const pagina = ref<PaginaLiquidaciones | null>(null)
const conteos = ref<BandejaConteos | null>(null)

const pestana = computed<IdPestana>(() => {
  const pedida = String(route.query.tab ?? 'mis_liquidaciones') as IdPestana
  return (VALIDAS as string[]).includes(pedida) ? pedida : 'mis_liquidaciones'
})

/** La misma regla del backend (BandejaController): quien no toca esa cola no la ve. */
const puedeRevisar = computed(() => auth.puede('liquidaciones.revisar'))
const puedeAprobar = computed(() => auth.puede('liquidaciones.aprobar'))

const pestanas = computed(() => {
  const lista: { id: IdPestana; etiqueta: string; conteo: number }[] = [
    { id: 'mis_liquidaciones', etiqueta: 'Mis liquidaciones', conteo: conteos.value?.mis_liquidaciones ?? 0 },
  ]

  if (puedeRevisar.value) {
    lista.push({ id: 'por_revisar', etiqueta: 'Por revisar', conteo: conteos.value?.por_revisar ?? 0 })
  }

  if (puedeAprobar.value) {
    lista.push({ id: 'por_aprobar', etiqueta: 'Por aprobar', conteo: conteos.value?.por_aprobar ?? 0 })
  }

  return lista
})

function filtros(): FiltrosLiquidacion {
  const numeroPagina = Number(route.query.page ?? 1)

  const comunes: FiltrosLiquidacion = {
    page: Number.isFinite(numeroPagina) && numeroPagina > 0 ? numeroPagina : 1,
    por_pagina: 12,
  }

  if (pestana.value === 'mis_liquidaciones') {
    return { ...comunes, registrado_por: auth.usuario?.id }
  }

  const estado: EstadoLiquidacion = pestana.value === 'por_revisar' ? 'en_revision' : 'revisado'

  return { ...comunes, estado }
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = ''

  try {
    const [c, p] = await Promise.all([obtenerBandeja(), listarLiquidaciones(filtros())])
    conteos.value = c
    pagina.value = p
  } catch (fallo) {
    error.value = mensajeDeError(fallo)
  } finally {
    cargando.value = false
  }
}

function cambiarPestana(otra: IdPestana): void {
  router.push({ query: { ...route.query, tab: otra, page: undefined } })
}

function irPagina(nueva: number): void {
  router.push({ query: { ...route.query, page: nueva } })
}

watch(
  () => route.query,
  () => {
    void cargar()
  },
  { immediate: true },
)

const esUltima = computed(() => Number(route.query.page ?? 1) >= (pagina.value?.meta.last_page ?? 1))
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div class="mb-4">
      <h1 class="text-lg font-semibold text-slate-800 lg:text-xl">Mi bandeja</h1>
      <p class="text-sm text-slate-500">Lo que te toca en el flujo de liquidaciones.</p>
    </div>

    <!-- Pestanas recortadas por rol -->
    <div class="mb-4 flex flex-wrap gap-2">
      <button
        v-for="p in pestanas"
        :key="p.id"
        type="button"
        class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors"
        :class="
          pestana === p.id
            ? 'bg-marca-600 text-white'
            : 'border border-slate-300 text-slate-600 hover:bg-slate-50'
        "
        @click="cambiarPestana(p.id)"
      >
        {{ p.etiqueta }}
        <span class="tabular-nums">({{ p.conteo }})</span>
      </button>
    </div>

    <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ error }}
    </p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div v-if="cargando" class="p-8 text-center text-sm text-slate-500">Cargando…</div>

      <div v-else-if="!pagina?.data.length" class="p-8 text-center text-sm text-slate-500">
        No hay liquidaciones en esta bandeja.
      </div>

      <template v-else>
        <table class="hidden w-full text-left text-sm lg:table">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-3 font-medium">Documento</th>
              <th class="px-4 py-3 font-medium">Vendedor</th>
              <th class="px-4 py-3 font-medium">Área minera</th>
              <th class="px-4 py-3 text-right font-medium">Neto</th>
              <th class="px-4 py-3 text-center font-medium">Estado</th>
              <th class="px-4 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="liquidacion in pagina.data" :key="liquidacion.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3">
                <p class="font-medium text-slate-800">{{ liquidacion.numero_documento }}</p>
                <p class="text-xs text-slate-500">{{ fecha(liquidacion.fecha_transaccion) }}</p>
              </td>
              <td class="px-4 py-3 text-slate-700">{{ liquidacion.vendedor.nombre }}</td>
              <td class="px-4 py-3 text-slate-600">
                <p>{{ liquidacion.procedencia.nombre_area_minera }}</p>
                <p class="text-xs text-slate-400">{{ liquidacion.presentacion.nombre }}</p>
              </td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-800">
                {{ bs(liquidacion.calculo.valor_neto_bs) }}
              </td>
              <td class="px-4 py-3 text-center">
                <BadgeEstado :estado="liquidacion.estado" />
              </td>
              <td class="px-4 py-3 text-right">
                <RouterLink
                  :to="{ name: 'liquidaciones-detalle', params: { id: liquidacion.id } }"
                  class="font-medium text-marca-600 hover:text-marca-700"
                >
                  Ver
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>

        <ul class="divide-y divide-slate-100 lg:hidden">
          <li v-for="liquidacion in pagina.data" :key="liquidacion.id">
            <RouterLink
              :to="{ name: 'liquidaciones-detalle', params: { id: liquidacion.id } }"
              class="flex flex-col gap-1 px-4 py-3"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold text-slate-800">{{ liquidacion.numero_documento }}</p>
                <BadgeEstado :estado="liquidacion.estado" />
              </div>
              <p class="text-xs text-slate-500">
                {{ fecha(liquidacion.fecha_transaccion) }} · {{ liquidacion.vendedor.nombre }}
              </p>
              <p class="text-xs text-slate-500">{{ liquidacion.procedencia.nombre_area_minera }}</p>
              <p class="text-sm font-semibold tabular-nums text-marca-700">
                {{ bs(liquidacion.calculo.valor_neto_bs) }}
              </p>
            </RouterLink>
          </li>
        </ul>
      </template>
    </div>

    <nav v-if="(pagina?.meta.last_page ?? 0) > 1" class="mt-4 flex items-center justify-center gap-2">
      <button
        type="button"
        :disabled="Number(route.query.page ?? 1) <= 1"
        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
        @click="irPagina(Number(route.query.page ?? 1) - 1)"
      >
        ← Anterior
      </button>

      <span class="text-sm text-slate-600">
        Página {{ Number(route.query.page ?? 1) }} de {{ pagina?.meta.last_page }}
      </span>

      <button
        type="button"
        :disabled="esUltima"
        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
        @click="irPagina(Number(route.query.page ?? 1) + 1)"
      >
        Siguiente →
      </button>
    </nav>
  </div>
</template>
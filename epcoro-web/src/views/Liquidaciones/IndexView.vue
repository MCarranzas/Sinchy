<script setup lang="ts">
/**
 * Listado de liquidaciones (8.10).
 *
 * Escritorio: tabla; celular: cards. Los filtros viven en la URL: cambiar
 * uno empuja el query string y recarga, de modo que un enlace copiado
 * ?page=2&estado=creado abre exactamente la misma vista (paginacion y
 * filtros por URL).
 */
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import Autocompletar from '@/components/Autocompletar.vue'
import BadgeEstado from '@/components/BadgeEstado.vue'
import { buscarAreasMineras, buscarMunicipios, buscarPersonas } from '@/api/catalogos.api'
import { listarLiquidaciones, type PaginaLiquidaciones } from '@/api/liquidaciones.api'
import { bs, fecha } from '@/utils/formato'
import { mensajeDeError } from '@/api/axios'
import { ETIQUETAS_ESTADO } from '@/types/liquidacion'
import type { AreaMinera, Municipio, Persona } from '@/types/catalogos'
import type { OpcionBusqueda } from '@/components/Autocompletar.vue'

const route = useRoute()
const router = useRouter()

const cargando = ref(false)
const error = ref('')
const pagina = ref<PaginaLiquidaciones | null>(null)

const busqueda = ref(String(route.query.q ?? ''))
const estado = ref(String(route.query.estado ?? ''))
const fechaDesde = ref(String(route.query.fecha_desde ?? ''))
const fechaHasta = ref(String(route.query.fecha_hasta ?? ''))
const areaMineraId = ref<number | null>(route.query.area_minera_id ? Number(route.query.area_minera_id) : null)
const municipioId = ref<number | null>(route.query.municipio_id ? Number(route.query.municipio_id) : null)
const vendedorId = ref<number | null>(route.query.vendedor_id ? Number(route.query.vendedor_id) : null)

// ------------ area minera (filtro con autocompletado)
const areas = ref<AreaMinera[]>([])
const opcionesArea = ref<OpcionBusqueda[]>([])
const cargandoAreas = ref(false)

async function buscarArea(texto: string): Promise<void> {
  cargandoAreas.value = true

  try {
    areas.value = await buscarAreasMineras(texto || undefined)
    opcionesArea.value = areas.value.map((a) => ({
      id: a.id,
      titulo: a.nombre_area_minera,
      detalle: `${a.ubicacion.municipio_nombre}, ${a.ubicacion.departamento_nombre}`,
    }))
  } finally {
    cargandoAreas.value = false
  }
}

function elegirArea(opcion: OpcionBusqueda): void {
  areaMineraId.value = opcion.id
}

function quitarFiltroArea(): void {
  areaMineraId.value = null
  aplicar()
}

// ------------ municipio (filtro con autocompletado, 8.10)
const municipios = ref<Municipio[]>([])
const opcionesMunicipio = ref<OpcionBusqueda[]>([])
const cargandoMunicipios = ref(false)

async function buscarMunicipio(texto: string): Promise<void> {
  cargandoMunicipios.value = true

  try {
    municipios.value = await buscarMunicipios(texto || undefined)
    opcionesMunicipio.value = municipios.value.map((m) => ({
      id: m.id,
      titulo: m.nombre,
      detalle:
        [m.departamento_nombre, m.provincia_nombre].filter(Boolean).join(', ') || undefined,
    }))
  } finally {
    cargandoMunicipios.value = false
  }
}

function elegirMunicipio(opcion: OpcionBusqueda): void {
  municipioId.value = opcion.id
}

function quitarFiltroMunicipio(): void {
  municipioId.value = null
  aplicar()
}

// ------------ vendedor (filtro con autocompletado, 8.10)
const vendedores = ref<Persona[]>([])
const opcionesVendedor = ref<OpcionBusqueda[]>([])
const cargandoVendedores = ref(false)

async function buscarVendedor(texto: string): Promise<void> {
  cargandoVendedores.value = true

  try {
    vendedores.value = await buscarPersonas(texto || undefined)
    opcionesVendedor.value = vendedores.value.map((p) => ({
      id: p.id,
      titulo: p.nombre_completo,
      detalle: `CI ${p.ci}`,
    }))
  } finally {
    cargandoVendedores.value = false
  }
}

function elegirVendedor(opcion: OpcionBusqueda): void {
  vendedorId.value = opcion.id
}

function quitarFiltroVendedor(): void {
  vendedorId.value = null
  aplicar()
}

// ---------------- carga de datos con los filtros de la URL
async function cargar(): Promise<void> {
  cargando.value = true
  error.value = ''

  try {
    const numeroPagina = Number(route.query.page ?? 1)

    pagina.value = await listarLiquidaciones({
      q: busqueda.value || undefined,
      estado: estado.value || undefined,
      fecha_desde: fechaDesde.value || undefined,
      fecha_hasta: fechaHasta.value || undefined,
      area_minera_id: areaMineraId.value ?? undefined,
      municipio_id: municipioId.value ?? undefined,
      vendedor_id: vendedorId.value ?? undefined,
      page: Number.isFinite(numeroPagina) && numeroPagina > 0 ? numeroPagina : 1,
      por_pagina: 12,
    })
  } catch (fallo) {
    error.value = mensajeDeError(fallo)
  } finally {
    cargando.value = false
  }
}

/** Cambiar un filtro reinicia a la pagina 1 y actualiza la URL. */
function aplicar(): void {
  router.push({
    query: {
      ...route.query,
      q: busqueda.value || undefined,
      estado: estado.value || undefined,
      fecha_desde: fechaDesde.value || undefined,
      fecha_hasta: fechaHasta.value || undefined,
      area_minera_id: areaMineraId.value ?? undefined,
      municipio_id: municipioId.value ?? undefined,
      vendedor_id: vendedorId.value ?? undefined,
      page: undefined,
    },
  })
}

function irPagina(nuevaPagina: number): void {
  router.push({ query: { ...route.query, page: nuevaPagina } })
}

watch(
  () => route.query,
  () => {
    void cargar()
  },
  { immediate: true },
)

const total = computed(() => pagina.value?.meta.total ?? 0)
const desde = computed(() => pagina.value?.meta.from ?? 0)
const hasta = computed(() => pagina.value?.meta.to ?? 0)
const esUltima = computed(() => Number(route.query.page ?? 1) >= (pagina.value?.meta.last_page ?? 1))

const opcionesEstado: { valor: string; etiqueta: string }[] = [
  { valor: 'creado', etiqueta: ETIQUETAS_ESTADO.creado },
  { valor: 'en_revision', etiqueta: ETIQUETAS_ESTADO.en_revision },
  { valor: 'revisado', etiqueta: ETIQUETAS_ESTADO.revisado },
  { valor: 'en_correccion', etiqueta: ETIQUETAS_ESTADO.en_correccion },
  { valor: 'aprobado', etiqueta: ETIQUETAS_ESTADO.aprobado },
  { valor: 'rechazado', etiqueta: ETIQUETAS_ESTADO.rechazado },
  { valor: 'anulado', etiqueta: ETIQUETAS_ESTADO.anulado },
  { valor: 'eliminado', etiqueta: ETIQUETAS_ESTADO.eliminado },
]
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h1 class="text-lg font-semibold text-slate-800 lg:text-xl">Liquidaciones</h1>
        <p class="text-sm text-slate-500">{{ total }} registro(s) · {{ desde }}-{{ hasta }}</p>
      </div>

      <RouterLink
        :to="{ name: 'liquidaciones-nueva' }"
        class="flex items-center gap-2 rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700"
      >
        + Nueva liquidación
      </RouterLink>
    </div>

    <!-- Filtros -->
    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
      <div class="lg:col-span-2">
        <label class="mb-1 block text-xs font-medium text-slate-600">Buscar</label>
        <input
          v-model="busqueda"
          type="text"
          placeholder="N° doc, vendedor, comprador…"
          class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-100"
          @keydown.enter="aplicar"
        />
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Estado</label>
        <select
          v-model="estado"
          class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-marca-500 focus:outline-none"
          @change="aplicar"
        >
          <option value="">Todos</option>
          <option v-for="opcion in opcionesEstado" :key="opcion.valor" :value="opcion.valor">
            {{ opcion.etiqueta }}
          </option>
        </select>
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Desde</label>
        <input v-model="fechaDesde" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-marca-500 focus:outline-none" @change="aplicar" />
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Hasta</label>
        <input v-model="fechaHasta" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-marca-500 focus:outline-none" @change="aplicar" />
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Área minera</label>
        <Autocompletar
          v-model="areaMineraId"
          etiqueta=""
          texto=""
          :opciones="opcionesArea"
          :cargando="cargandoAreas"
          placeholder="Buscar área…"
          @buscar="buscarArea"
          @seleccion="elegirArea"
        />
        <button
          v-if="areaMineraId"
          type="button"
          class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
          @click="quitarFiltroArea"
        >
          Quitar filtro de área
        </button>
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Municipio</label>
        <Autocompletar
          v-model="municipioId"
          etiqueta=""
          texto=""
          :opciones="opcionesMunicipio"
          :cargando="cargandoMunicipios"
          placeholder="Buscar municipio…"
          @buscar="buscarMunicipio"
          @seleccion="elegirMunicipio"
        />
        <button
          v-if="municipioId"
          type="button"
          class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
          @click="quitarFiltroMunicipio"
        >
          Quitar filtro de municipio
        </button>
      </div>

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Vendedor</label>
        <Autocompletar
          v-model="vendedorId"
          etiqueta=""
          texto=""
          :opciones="opcionesVendedor"
          :cargando="cargandoVendedores"
          placeholder="Buscar vendedor…"
          @buscar="buscarVendedor"
          @seleccion="elegirVendedor"
        />
        <button
          v-if="vendedorId"
          type="button"
          class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
          @click="quitarFiltroVendedor"
        >
          Quitar filtro de vendedor
        </button>
      </div>
    </div>

    <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ error }}
    </p>

    <!-- -------------------------------------------------------- tabla -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div v-if="cargando" class="p-8 text-center text-sm text-slate-500">Cargando…</div>

      <div v-else-if="!pagina?.data.length" class="p-8 text-center text-sm text-slate-500">
        No hay liquidaciones que coincidan con los filtros.
      </div>

      <template v-else>
        <!-- escritorio -->
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

        <!-- celular -->
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

    <!-- Paginacion por URL: ?page=N -->
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
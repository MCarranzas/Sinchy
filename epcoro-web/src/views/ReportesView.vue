<script setup lang="ts">
/**
 * Reportes (FASE 10): rango de fechas, tarjetas de resumen, agrupados,
 * graficos y exportacion a Excel/PDF. Todo bajo `reportes.ver`.
 *
 * Contrato con el backend: GET /reportes/resumen?fecha_desde=&fecha_hasta=
 * (los totales y los agrupados vienen en la misma respuesta), y el enlace
 * de descarga /reportes/exportar?formato=xlsx|pdf con el mismo rango.
 *
 * 10.6: el rango vive en la URL (?fecha_desde=...&fecha_hasta=...), como el
 * listado; un enlace copiado abre exactamente el mismo periodo.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import GraficoBarras from '@/components/Reportes/GraficoBarras.vue'
import GraficoDona from '@/components/Reportes/GraficoDona.vue'
import TablaGrupo from '@/components/Reportes/TablaGrupo.vue'
import { obtenerResumen, urlExportar } from '@/api/reportes.api'
import { mensajeDeError } from '@/api/axios'
import { bs, gramos, numero } from '@/utils/formato'
import { ETIQUETAS_ESTADO, type EstadoLiquidacion } from '@/types/liquidacion'
import type { ResumenReporte } from '@/types/reportes'

const route = useRoute()
const router = useRouter()

// ------- controles del periodo (10.1: quincenal, mensual, anual) ----------
type TipoPeriodo = 'mensual' | 'quincenal' | 'anual'

const anio = ref(new Date().getFullYear())
const mes = ref(new Date().getMonth() + 1)
const quincena = ref<1 | 2>(1)
const periodoTipo = ref<TipoPeriodo>('mensual')

const fechaDesde = ref('')
const fechaHasta = ref('')

const cargando = ref(false)
const error = ref('')
const resumen = ref<ResumenReporte | null>(null)

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function ultimoDiaDelMes(anioN: number, mesN: number): number {
  return new Date(Date.UTC(anioN, mesN, 0)).getUTCDate()
}

function rangoActual(): { desde: string; hasta: string } {
  if (periodoTipo.value === 'anual') {
    return { desde: `${anio.value}-01-01`, hasta: `${anio.value}-12-31` }
  }

  const base = `${anio.value}-${pad(mes.value)}`
  const momento = periodoTipo.value === 'quincenal' && quincena.value === 2

  return {
    desde: `${base}-${momento ? '16' : '01'}`,
    hasta: `${base}-${pad(ultimoDiaDelMes(anio.value, mes.value))}`,
  }
}

function aplicar(): void {
  router.push({
    query: {
      fecha_desde: fechaDesde.value || undefined,
      fecha_hasta: fechaHasta.value || undefined,
    },
  })
}

function aplicarPeriodo(): void {
  const rango = rangoActual()
  fechaDesde.value = rango.desde
  fechaHasta.value = rango.hasta
  aplicar()
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = ''

  try {
    resumen.value = await obtenerResumen(fechaDesde.value || undefined, fechaHasta.value || undefined)
  } catch (fallo) {
    resumen.value = null
    error.value = mensajeDeError(fallo)
  } finally {
    cargando.value = false
  }
}

watch(
  () => [route.query.fecha_desde, route.query.fecha_hasta],
  () => {
    fechaDesde.value = String(route.query.fecha_desde ?? '')
    fechaHasta.value = String(route.query.fecha_hasta ?? '')
    void cargar()
  },
)

onMounted(() => {
  const desde = String(route.query.fecha_desde ?? '')
  const hasta = String(route.query.fecha_hasta ?? '')

  if (desde && hasta) {
    fechaDesde.value = desde
    fechaHasta.value = hasta
  } else {
    const rango = rangoActual()
    fechaDesde.value = rango.desde
    fechaHasta.value = rango.hasta
  }

  void cargar()
})

// ------- deriva lo que pintan las tarjetas, tablas y graficos -------------
function etiquetaEstado(valor: string | null): string {
  if (valor === null) {
    return '—'
  }

  return ETIQUETAS_ESTADO[valor as EstadoLiquidacion] ?? valor
}

function etiquetaMes(valor: string | null): string {
  if (valor === null) {
    return '—'
  }

  const [anioV, mesV] = valor.split('-')
  return anioV && mesV ? `${mesV}/${anioV}` : valor
}

const tarjetas = computed(() => {
  const t = resumen.value?.totales

  return [
    { titulo: 'Liquidaciones', valor: numero(t?.liquidaciones ?? 0, 0), detalle: 'en el período' },
    { titulo: 'Peso', valor: gramos(t?.peso_total ?? null), detalle: 'gramos brutos' },
    { titulo: 'Peso fino', valor: gramos(t?.peso_fino_total ?? null), detalle: 'gramos finos' },
    { titulo: 'Valor bruto', valor: bs(t?.bruto_total ?? null), detalle: 'antes de deducciones' },
    { titulo: 'Aportes', valor: bs(t?.aportes_total ?? null), detalle: 'caja de salud' },
    { titulo: 'Deducciones', valor: bs(t?.deducciones_total ?? null), detalle: 'total descontado' },
    { titulo: 'Valor neto', valor: bs(t?.neto_total ?? null), detalle: 'a pagar al vendedor' },
  ]
})

const evolucionMensual = computed(() => ({
  etiquetas: resumen.value?.por_mes.filas.map((fila) => etiquetaMes(fila.valor)) ?? [],
  valores: resumen.value?.por_mes.filas.map((fila) => Number(fila.neto_total)) ?? [],
}))

const distribucionEstado = computed(() => ({
  etiquetas: resumen.value?.por_estado.filas.map((fila) => etiquetaEstado(fila.valor)) ?? [],
  valores: resumen.value?.por_estado.filas.map((fila) => fila.liquidaciones) ?? [],
}))

/** Top municipios por valor neto, para la barra (10.4). */
const topMunicipios = computed(() => {
  const filas = [...(resumen.value?.por_municipio.filas ?? [])]
    .sort((a, b) => Number(b.neto_total) - Number(a.neto_total))
    .slice(0, 8)

  return {
    etiquetas: filas.map((fila) => fila.valor ?? 'Sin municipio'),
    valores: filas.map((fila) => Number(fila.neto_total)),
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- -------- controles de periodo y exportacion (10.1, 10.5) -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-8">
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">Período</label>
          <select
            v-model="periodoTipo"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          >
            <option value="mensual">Mensual</option>
            <option value="quincenal">Quincenal</option>
            <option value="anual">Anual</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Año</label>
          <input
            v-model.number="anio"
            type="number"
            min="2000"
            max="2100"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Mes</label>
          <select
            v-model.number="mes"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          >
            <option v-for="m in 12" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Quincena</label>
          <select
            v-model="quincena"
            :disabled="periodoTipo !== 'quincenal'"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none disabled:cursor-not-allowed disabled:opacity-50 focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          >
            <option :value="1">1ª (01–15)</option>
            <option :value="2">2ª (16–fin)</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Desde</label>
          <input
            v-model="fechaDesde"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Hasta</label>
          <input
            v-model="fechaHasta"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          />
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700"
            @click="aplicarPeriodo"
          >
            Aplicar período
          </button>
          <button
            type="button"
            class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-200"
            @click="aplicar"
          >
            Aplicar rango
          </button>
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-500">Exportar:</span>
        <a
          :href="urlExportar('xlsx', fechaDesde, fechaHasta)"
          class="text-sm font-medium text-marca-700 hover:text-marca-800 hover:underline"
        >
          Excel (.xlsx)
        </a>
        <a
          :href="urlExportar('pdf', fechaDesde, fechaHasta)"
          class="text-sm font-medium text-marca-700 hover:text-marca-800 hover:underline"
        >
          PDF
        </a>
      </div>
    </div>

    <p
      v-if="error"
      class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ error }}
    </p>

    <div
      v-if="cargando && !resumen"
      class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm"
    >
      <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-marca-600" />
      Calculando el resumen del período…
    </div>

    <template v-if="!cargando && resumen">
      <!-- -------- tarjetas de resumen (10.2) -->
      <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="tarjeta in tarjetas"
          :key="tarjeta.titulo"
          class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            {{ tarjeta.titulo }}
          </p>
          <p class="mt-1 truncate text-xl font-semibold text-slate-800">
            {{ tarjeta.valor }}
          </p>
          <p class="mt-0.5 text-xs text-slate-400">{{ tarjeta.detalle }}</p>
        </div>
      </section>

      <!-- -------- graficos (10.4) -->
      <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 class="mb-2 text-sm font-semibold text-slate-800">
            Distribución por estado
          </h3>
          <GraficoDona :datos="distribucionEstado" etiqueta="Liquidaciones" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 class="mb-2 text-sm font-semibold text-slate-800">
            Evolución mensual del valor neto
          </h3>
          <GraficoBarras :datos="evolucionMensual" etiqueta="Valor neto (Bs)" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 class="mb-2 text-sm font-semibold text-slate-800">
            Top municipios por valor neto
          </h3>
          <GraficoBarras :datos="topMunicipios" etiqueta="Valor neto (Bs)" color="#0f766e" />
        </div>
      </section>

      <!-- -------- tablas agrupadas (10.3) -->
      <section class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <TablaGrupo
          titulo="Por estado"
          :filas="resumen.por_estado.filas"
          :etiquetar-valor="etiquetaEstado"
        />
        <TablaGrupo
          titulo="Por municipio"
          :filas="resumen.por_municipio.filas"
          :etiquetar-valor="(v) => v ?? 'Sin municipio'"
        />
        <TablaGrupo
          titulo="Por área minera"
          :filas="resumen.por_area_minera.filas"
          :etiquetar-valor="(v) => v ?? 'Sin área'"
        />
        <TablaGrupo
          titulo="Por presentación (mineral)"
          :filas="resumen.por_mineral.filas"
          :etiquetar-valor="(v) => v ?? 'Sin presentación'"
        />
        <TablaGrupo
          titulo="Por mes"
          :filas="resumen.por_mes.filas"
          :etiquetar-valor="etiquetaMes"
        />

        <RouterLink
          :to="{ name: 'liquidaciones', query: { fecha_desde: fechaDesde, fecha_hasta: fechaHasta } }"
          class="flex flex-col justify-center rounded-xl border border-dashed border-slate-300 bg-white p-5 shadow-sm transition-colors hover:border-marca-500 hover:bg-marca-50"
        >
          <p class="text-sm font-semibold text-slate-800">
            Ver el detalle en el listado →
          </p>
          <p class="mt-1 text-sm text-slate-500">
            Abre el mismo período en Liquidaciones para comprobar de qué se
            compone el total ({{
              numero(resumen.totales.liquidaciones, 0)
            }}).
          </p>
        </RouterLink>
      </section>
    </template>
  </div>
</template>
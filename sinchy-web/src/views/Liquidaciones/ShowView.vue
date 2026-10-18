<script setup lang="ts">
/**
 * Detalle de una liquidacion (8.11 y 8.12 + FASE 9).
 *
 * Todo lo que puede necesitar el tecnico que capturo O el que revisa: datos
 * completos, desglose del calculo (lo que persistio el backend, T3), el
 * historial de estados y las descargas PDF/Excel. La barra de acciones
 * (9.1) se pinta con lo que el recurso promete en `transiciones_disponibles`
 * y, al ejecutar una, el detalle se refresca con lo que el backend devuelve.
 *
 * La edicion solo aparece para el AUTOR y mientras el estado lo admita
 * (creado / en_correccion) y el usuario tenga el permiso (9.4).
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import BadgeEstado from '@/components/BadgeEstado.vue'
import BarraEstado from '@/components/liquidacion/BarraEstado.vue'
import HistorialEstados from '@/components/liquidacion/HistorialEstados.vue'
import ModalTransicion from '@/components/liquidacion/ModalTransicion.vue'
import { mensajeDeError } from '@/api/axios'
import { historialLiquidacion, obtenerLiquidacion, transicionarLiquidacion, urlExcel, urlPdf } from '@/api/liquidaciones.api'
import { bs, fecha, fechaHora, gramos, numero, porcentaje } from '@/utils/formato'
import { ESTADOS_EDITABLES } from '@/types/liquidacion'
import type { EstadoLiquidacion, HistorialEstado, Liquidacion, TransicionPayload } from '@/types/liquidacion'
import { accionTransicion } from '@/utils/transiciones'
import type { AccionTransicion } from '@/utils/transiciones'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()

const route = useRoute()
const id = Number(route.params.id)

const liquidacion = ref<Liquidacion | null>(null)
const historial = ref<HistorialEstado[]>([])
const cargando = ref(true)
const error = ref('')

// ----------------------------------------------------- barra de estado (9.1)
const accionPendiente = ref<AccionTransicion | null>(null)
const modalAbierto = ref(false)
const enviandoTransicion = ref(false)
const aviso = ref('')

/** El autor de la fila, para decidir que puede tocar la barra de accion. */
const esPropia = computed(
  () => liquidacion.value?.auditoria.registrado_por?.id === auth.usuario?.id,
)

const editable = computed(() => {
  const actual = liquidacion.value
  if (!actual) {
    return false
  }

  return ESTADOS_EDITABLES.includes(actual.estado)
    && esPropia.value
    && auth.puede('liquidaciones.editar')
})

async function cargar(): Promise<void> {
  const [detalle, filas] = await Promise.all([
    obtenerLiquidacion(id),
    historialLiquidacion(id),
  ])

  liquidacion.value = detalle
  historial.value = filas
}

onMounted(async () => {
  try {
    await cargar()
  } catch (fallo) {
    error.value = mensajeDeError(fallo)
  } finally {
    cargando.value = false
  }
})

function abrirAccion(destino: EstadoLiquidacion): void {
  if (!liquidacion.value) {
    return
  }

  accionPendiente.value = accionTransicion(liquidacion.value.estado, destino)
  modalAbierto.value = true
}

async function ejecutarTransicion(payload: TransicionPayload): Promise<void> {
  enviandoTransicion.value = true
  error.value = ''

  try {
    const actualizado = await transicionarLiquidacion(id, payload)
    liquidacion.value = actualizado
    historial.value = await historialLiquidacion(id)
    modalAbierto.value = false
    aviso.value = 'Movimiento registrado. La liquidación quedó en “'
      + (actualizado.estado_label ?? payload.estado_destino)
      + '”.'
  } catch (fallo) {
    error.value = mensajeDeError(fallo)
  } finally {
    enviandoTransicion.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl">
    <div v-if="cargando" class="p-8 text-center text-sm text-slate-500">Cargando…</div>

    <p v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ error }}
    </p>

    <template v-else-if="liquidacion">
      <!-- Cabecera -->
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-lg font-semibold text-slate-800 lg:text-xl">
              {{ liquidacion.numero_documento }}
            </h1>
            <BadgeEstado :estado="liquidacion.estado" />
          </div>
          <p class="mt-1 text-sm text-slate-500">
            {{ fecha(liquidacion.fecha_transaccion) }} · Código
            {{ liquidacion.codigo_formulario }} · Ejercicio {{ liquidacion.ejercicio }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            :to="{ name: 'liquidaciones' }"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
          >
            <AppIcon nombre="cerrar" :tamanio="15" />
            Volver
          </RouterLink>

          <RouterLink
            v-if="editable"
            :to="{ name: 'liquidaciones-editar', params: { id: liquidacion.id } }"
            class="flex items-center gap-1.5 rounded-lg border border-marca-300 px-3 py-2 text-sm font-medium text-marca-700 transition-colors hover:bg-marca-50"
          >
            Editar
          </RouterLink>

          <a
            :href="urlPdf(liquidacion.id)"
            target="_blank"
            class="rounded-lg bg-marca-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-marca-700"
          >
            PDF
          </a>
          <a
            :href="urlExcel(liquidacion.id)"
            class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
          >
            Excel
          </a>
        </div>
      </div>

      <p v-if="aviso" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ aviso }}
      </p>

      <!-- Estado y acciones del flujo (9.1) -->
      <BarraEstado :liquidacion="liquidacion" @accion="abrirAccion" />

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Personas -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Partes</h2>
          <dl class="space-y-2 text-sm">
            <div>
              <dt class="text-slate-500">Comprador</dt>
              <dd class="font-medium text-slate-800">{{ liquidacion.comprador.razon_social }}</dd>
              <dd v-if="liquidacion.comprador.nim" class="text-xs text-slate-500">
                NIM {{ liquidacion.comprador.nim }}
              </dd>
              <dd v-if="liquidacion.comprador.nit" class="text-xs text-slate-500">
                NIT {{ liquidacion.comprador.nit }}
              </dd>
            </div>
            <div class="border-t border-dashed border-slate-200 pt-2">
              <dt class="text-slate-500">Vendedor</dt>
              <dd class="font-medium text-slate-800">{{ liquidacion.vendedor.nombre }}</dd>
              <dd class="text-xs text-slate-500">CI {{ liquidacion.vendedor.ci }}</dd>
            </div>
            <div class="border-t border-dashed border-slate-200 pt-2">
              <dt class="text-slate-500">Presentación</dt>
              <dd class="font-medium text-slate-800">{{ liquidacion.presentacion.nombre }}</dd>
            </div>
          </dl>
        </section>

        <!-- Procedencia -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Procedencia</h2>
          <dl class="space-y-2 text-sm">
            <div>
              <dt class="text-slate-500">Área minera</dt>
              <dd class="font-medium text-slate-800">{{ liquidacion.procedencia.nombre_area_minera }}</dd>
              <dd v-if="liquidacion.procedencia.titular_derecho_minero" class="text-xs text-slate-500">
                {{ liquidacion.procedencia.titular_derecho_minero }}
              </dd>
              <dd v-if="liquidacion.procedencia.codigo_ajam_patente" class="text-xs text-slate-500">
                AJAM {{ liquidacion.procedencia.codigo_ajam_patente }}
              </dd>
            </div>
            <div class="border-t border-dashed border-slate-200 pt-2">
              <dt class="text-slate-500">Ubicación</dt>
              <dd class="font-medium text-slate-800">
                {{ liquidacion.procedencia.localidad.nombre }}, {{ liquidacion.procedencia.municipio.nombre }},
                {{ liquidacion.procedencia.provincia.nombre }},
                {{ liquidacion.procedencia.departamento.nombre }}
              </dd>
              <dd v-if="liquidacion.procedencia.municipio.codigo" class="text-xs text-slate-500">
                Código de municipio {{ liquidacion.procedencia.municipio.codigo }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Insumos -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Insumos</h2>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt class="text-slate-500">Peso</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              {{ gramos(liquidacion.insumos.peso_gramos) }}
            </dd>
            <dt class="text-slate-500">Ley pagable</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              {{ porcentaje(liquidacion.insumos.ley_pagable) }}
            </dd>
            <dt class="text-slate-500">Tipo de cambio</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              {{ numero(liquidacion.insumos.tipo_cambio) }} Bs/$
            </dd>
            <dt class="text-slate-500">Cotización</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              {{ numero(liquidacion.insumos.cotizacion_usd_oz_t) }} USD/oz t
            </dd>
            <dt class="text-slate-500">Caja de salud</dt>
            <dd class="text-right font-medium text-slate-800">
              {{ liquidacion.insumos.aplica_caja_salud ? 'Aplica' : 'No aplica' }}
            </dd>
          </dl>
        </section>

        <!-- Calculo -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Cálculo</h2>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt class="text-slate-500">Peso fino</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              {{ gramos(liquidacion.calculo.peso_fino_gramos) }}
            </dd>
            <dt class="text-slate-500">Valor USD/gramo</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              $ {{ numero(liquidacion.calculo.valor_usd_gramo) }}
            </dd>
            <dt class="text-slate-500">Valor bruto</dt>
            <dd class="text-right font-medium tabular-nums text-slate-800">
              {{ bs(liquidacion.calculo.valor_bruto_bs) }}
            </dd>
            <dt class="text-slate-500">Aporte caja de salud</dt>
            <dd class="text-right tabular-nums text-rose-600">
              - {{ bs(liquidacion.calculo.aporte_caja_salud_bs) }}
            </dd>
            <div class="col-span-2 mt-1 flex items-center justify-between rounded-lg bg-marca-50 px-3 py-2">
              <dt class="text-sm font-medium text-marca-700">Valor neto</dt>
              <dd class="text-base font-bold tabular-nums text-marca-700">
                {{ bs(liquidacion.calculo.valor_neto_bs) }}
              </dd>
            </div>
          </dl>
        </section>
      </div>

      <!-- Observaciones -->
      <section v-if="liquidacion.observaciones" class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Observaciones</h2>
        <p class="whitespace-pre-wrap text-sm text-slate-700">{{ liquidacion.observaciones }}</p>
      </section>

      <!-- Auditoria y historial -->
      <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Auditoría</h2>
          <dl class="space-y-1.5 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-slate-500">Registrado por</dt>
              <dd class="font-medium text-slate-800">
                {{ liquidacion.auditoria.registrado_por?.nombre_completo ?? '—' }}
              </dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-slate-500">Fecha de registro</dt>
              <dd class="tabular-nums text-slate-700">{{ fechaHora(liquidacion.auditoria.fecha_registro) }}</dd>
            </div>
            <template v-if="liquidacion.auditoria.fecha_revision">
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">En revisión</dt>
                <dd class="tabular-nums text-slate-700">{{ fechaHora(liquidacion.auditoria.fecha_revision) }}</dd>
              </div>
            </template>
            <template v-if="liquidacion.auditoria.fecha_aprobacion">
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">Aprobada</dt>
                <dd class="tabular-nums text-slate-700">{{ fechaHora(liquidacion.auditoria.fecha_aprobacion) }}</dd>
              </div>
            </template>
            <template v-if="liquidacion.auditoria.motivo_rechazo">
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">Motivo de rechazo</dt>
                <dd class="max-w-[60%] text-right text-rose-600">{{ liquidacion.auditoria.motivo_rechazo }}</dd>
              </div>
            </template>
          </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Historial</h2>
          <HistorialEstados :filas="historial" />
        </section>
      </div>
    </template>

    <ModalTransicion
      :abierto="modalAbierto"
      :accion="accionPendiente"
      :enviando="enviandoTransicion"
      @cancelar="modalAbierto = false"
      @confirmar="ejecutarTransicion"
    />
  </div>
</template>
<script setup lang="ts">
/**
 * Seccion Comprador (8.3): elegir del catalogo `compradores` y, si el Excel
 * trae otro comprador en un caso puntual, corregir los tres campos a mano.
 *
 * Igual que el vendedor (8.4): la razon social, el NIM y el NIT llegan del
 * catalogo. Al abrir se prellenan con el comprador por defecto (SINCHI WAYRA, celdas
 * C11-C13) y aqui se puede cambiar por cualquiera de la lista. Los tres campos
 * quedan editables porque el Excel admite otro comprador.
 *
 * La lista sale del store (que ya tiene el catalogo cargado) y se filtra en el
 * cliente: son pocas filas, no hace falta un buscador del backend.
 */
import { computed, onMounted, ref } from 'vue'

import Autocompletar from '@/components/Autocompletar.vue'
import CampoEntrada from '@/components/CampoEntrada.vue'
import { useCatalogosStore } from '@/stores/catalogos'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'
import type { OpcionBusqueda } from '@/components/Autocompletar.vue'

const props = defineProps<{ formulario: FormularioCaptura }>()

const catalogos = useCatalogosStore()

const consulta = ref('')

/** Detalle de cada opcion: NIM y NIT, solo los que existan. */
function detalleDe(nim: string | null, nit: string | null): string | undefined {
  const partes: string[] = []

  if (nim) {
    partes.push(`NIM ${nim}`)
  }

  if (nit) {
    partes.push(`NIT ${nit}`)
  }

  return partes.length > 0 ? partes.join(' · ') : undefined
}

const opciones = computed<OpcionBusqueda[]>(() => {
  const texto = consulta.value.trim().toLowerCase()

  return catalogos.compradores
    .filter(
      (comprador) =>
        texto === '' ||
        comprador.razon_social.toLowerCase().includes(texto) ||
        (comprador.nim ?? '').toLowerCase().includes(texto) ||
        (comprador.nit ?? '').toLowerCase().includes(texto),
    )
    .map((comprador) => ({
      id: comprador.id,
      titulo: comprador.razon_social,
      detalle: detalleDe(comprador.nim, comprador.nit),
    }))
})

function buscar(texto: string): void {
  consulta.value = texto
}

function alElegir(opcion: OpcionBusqueda): void {
  const comprador = catalogos.compradores.find((c) => c.id === opcion.id)

  if (comprador) {
    props.formulario.asignar('comprador_razon_social', comprador.razon_social)
    props.formulario.asignar('comprador_nim', comprador.nim ?? '')
    props.formulario.asignar('comprador_nit', comprador.nit ?? '')
  }
}

onMounted(() => {
  void catalogos.cargar()
})
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
      <span class="flex h-6 w-6 items-center justify-center rounded-full bg-marca-100 text-xs text-marca-700">
        2
      </span>
      Comprador
    </h2>

    <div class="grid gap-4">
      <Autocompletar
        etiqueta="Comprador del catálogo *"
        :opciones="opciones"
        :texto="formulario.valores.comprador_razon_social"
        placeholder="Razón social, NIM o NIT del comprador…"
        ayuda="Elija del catálogo para completar razón social, NIM y NIT."
        etiqueta-vacio="Sin compradores en el catálogo"
        @buscar="buscar"
        @seleccion="alElegir"
      />

      <CampoEntrada
        v-bind="formulario.enlazar('comprador_razon_social')"
        etiqueta="Razón social *"
        maxlength="200"
        autocomplete="organization"
        ayuda="Se completa al elegir del catálogo; editable."
        :error="formulario.error('comprador_razon_social')"
        @blur="formulario.tocar('comprador_razon_social')"
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <CampoEntrada
          v-bind="formulario.enlazar('comprador_nim')"
          etiqueta="NIM"
          placeholder="02-2388-05"
          maxlength="30"
          ayuda="Identificador: dígitos y guiones."
          :error="formulario.error('comprador_nim')"
          @blur="formulario.tocar('comprador_nim')"
        />

        <CampoEntrada
          v-bind="formulario.enlazar('comprador_nit')"
          etiqueta="NIT"
          placeholder="1006965023"
          maxlength="20"
          ayuda="Identificador: solo dígitos, sin notación científica."
          :error="formulario.error('comprador_nit')"
          @blur="formulario.tocar('comprador_nit')"
        />
      </div>
    </div>
  </section>
</template>

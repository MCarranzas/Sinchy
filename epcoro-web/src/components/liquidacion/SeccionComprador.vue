<script setup lang="ts">
/**
 * Seccion Comprador (8.3): autocompletado desde el catalogo `compradores`.
 *
 * La razon social, el NIM y el NIT del comprador son FIJOS del sistema
 * (C11-C13 de hoja.xlsx, hoy una sola fila: EPCORO) y llegan rellenados al
 * abrir; el formulario los deja editar porque el Excel admite otro comprador
 * en casos puntuales.
 */
import CampoEntrada from '@/components/CampoEntrada.vue'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'

defineProps<{ formulario: FormularioCaptura }>()
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
      <CampoEntrada
        v-bind="formulario.enlazar('comprador_razon_social')"
        etiqueta="Razón social *"
        maxlength="200"
        autocomplete="organization"
        ayuda="Prellenada con el comprador del catálogo (EPCORO)."
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

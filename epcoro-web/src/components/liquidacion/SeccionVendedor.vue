<script setup lang="ts">
/**
 * Seccion Vendedor (8.4): buscador de personas con ALTA RAPIDA.
 *
 * El vendedor es obligatorio y ademas se guarda con su CI en la propia
 * liquidacion (snapshot D3): al elegir la persona se copia su CI en el
 * campo, y ahi se puede corregir si el Excel trae otro. Si la persona no
 * existe, el alta se hace en el mismo formulario sin salirse de la captura
 * (el backend ademas devuelve la existente si el CI ya estaba, N5).
 */
import { computed, ref } from 'vue'

import Autocompletar from '@/components/Autocompletar.vue'
import CampoEntrada from '@/components/CampoEntrada.vue'
import { buscarPersonas, guardarPersona } from '@/api/catalogos.api'
import { mensajeDeError } from '@/api/axios'
import type { FormularioCaptura } from '@/composables/useLiquidacionForm'
import type { Persona } from '@/types/catalogos'
import type { OpcionBusqueda } from '@/components/Autocompletar.vue'

const props = defineProps<{ formulario: FormularioCaptura }>()

const opciones = ref<OpcionBusqueda[]>([])
const personas = ref<Persona[]>([])
const cargando = ref(false)
const consulta = ref('')

const vendedor = computed(() => personas.value.find((p) => p.id === props.formulario.valores.vendedor_persona_id) ?? null)

const textoVendedor = computed(() => vendedor.value?.nombre_completo ?? '')

async function buscar(texto: string): Promise<void> {
  consulta.value = texto
  cargando.value = true

  try {
    personas.value = await buscarPersonas(texto || undefined)
    opciones.value = personas.value.map((persona) => ({
      id: persona.id,
      titulo: persona.nombre_completo,
      detalle: `CI ${persona.ci}${persona.nit ? ` · NIT ${persona.nit}` : ''}`,
    }))
  } finally {
    cargando.value = false
  }
}

function alElegir(opcion: OpcionBusqueda): void {
  const persona = personas.value.find((p) => p.id === opcion.id)

  if (persona) {
    props.formulario.asignar('vendedor_persona_id', persona.id)
    props.formulario.asignar('vendedor_ci', persona.ci)
  }
}

// ------------------------------------------------------------- alta rapida

const altaAbierta = ref(false)
const guardando = ref(false)
const errorAlta = ref('')
const nueva = ref({ nombres: '', apellidos: '', ci: '', nit: '' })

function abrirAlta(): void {
  errorAlta.value = ''
  nueva.value = { nombres: '', apellidos: '', ci: '', nit: '' }
  altaAbierta.value = true
}

async function crearPersona(): Promise<void> {
  errorAlta.value = ''

  if (nueva.value.nombres.trim() === '' || nueva.value.apellidos.trim() === '') {
    errorAlta.value = 'Nombres y apellidos son obligatorios.'
    return
  }

  if (!/^\d{1,10}$/.test(nueva.value.ci.trim())) {
    errorAlta.value = 'El CI debe tener solo dígitos (hasta 10).'
    return
  }

  guardando.value = true

  try {
    const persona = await guardarPersona({
      nombres: nueva.value.nombres.trim(),
      apellidos: nueva.value.apellidos.trim(),
      ci: nueva.value.ci.trim(),
      nit: nueva.value.nit.trim() || undefined,
    })

    personas.value = [persona, ...personas.value.filter((p) => p.id !== persona.id)]
    opciones.value = [
      {
        id: persona.id,
        titulo: persona.nombre_completo,
        detalle: `CI ${persona.ci}`,
      },
      ...opciones.value,
    ]

    props.formulario.asignar('vendedor_persona_id', persona.id)
    props.formulario.asignar('vendedor_ci', persona.ci)
    altaAbierta.value = false
  } catch (errorDeRed) {
    errorAlta.value = mensajeDeError(errorDeRed)
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
      <span class="flex h-6 w-6 items-center justify-center rounded-full bg-marca-100 text-xs text-marca-700">
        3
      </span>
      Vendedor
    </h2>

    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
      <div>
        <Autocompletar
          v-bind="formulario.enlazar('vendedor_persona_id')"
          etiqueta="Vendedor *"
          :opciones="opciones"
          :texto="textoVendedor"
          :cargando="cargando"
          :error="formulario.error('vendedor_persona_id')"
          placeholder="Nombre, CI o NIT del vendedor…"
          @buscar="buscar"
          @seleccion="alElegir"
          @blur="formulario.tocar('vendedor_persona_id')"
        />

        <button
          type="button"
          class="mt-2 text-sm font-medium text-marca-600 transition-colors hover:text-marca-700"
          @click="altaAbierta ? (altaAbierta = false) : abrirAlta()"
        >
          {{ altaAbierta ? 'Cancelar alta' : '¿No existe? Registrar vendedor' }}
        </button>
      </div>

      <CampoEntrada
        v-bind="formulario.enlazar('vendedor_ci')"
        etiqueta="Cédula de identidad *"
        placeholder="1234567"
        maxlength="10"
        ayuda="Se copia al elegir la persona; editable."
        :error="formulario.error('vendedor_ci')"
        @blur="formulario.tocar('vendedor_ci')"
      />
    </div>

    <!-- Alta rapida: no debe sacar al usuario del formulario. -->
    <div
      v-if="altaAbierta"
      class="mt-4 rounded-lg border border-dashed border-marca-300 bg-marca-50/50 p-4"
    >
      <p class="mb-3 text-sm font-medium text-slate-700">Nuevo vendedor</p>

      <div class="grid gap-3 sm:grid-cols-2">
        <CampoEntrada v-model="nueva.nombres" etiqueta="Nombres *" maxlength="100" />
        <CampoEntrada v-model="nueva.apellidos" etiqueta="Apellidos *" maxlength="100" />
        <CampoEntrada v-model="nueva.ci" etiqueta="CI *" maxlength="10" placeholder="Solo dígitos" />
        <CampoEntrada v-model="nueva.nit" etiqueta="NIT (opcional)" maxlength="20" placeholder="Solo dígitos" />
      </div>

      <p v-if="errorAlta" class="mt-2 text-xs text-rose-600">{{ errorAlta }}</p>

      <div class="mt-3 flex gap-2">
        <button
          type="button"
          :disabled="guardando"
          class="rounded-lg bg-marca-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-marca-700 disabled:opacity-60"
          @click="crearPersona"
        >
          {{ guardando ? 'Guardando…' : 'Guardar y usar' }}
        </button>
        <button
          type="button"
          class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
          @click="altaAbierta = false"
        >
          Cancelar
        </button>
      </div>
    </div>
  </section>
</template>

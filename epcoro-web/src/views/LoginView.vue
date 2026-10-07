<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { mensajeDeError } from '@/api/axios'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const usuario = ref('')
const contrasena = ref('')
const recordar = ref(false)

const error = ref('')
const erroresCampo = ref<{ usuario?: string; contrasena?: string }>({})

/**
 * Validacion minima en el cliente para no gastar una peticion (y un intento
 * del limitador) en un formulario vacio. El backend vuelve a validar igual.
 */
function validar(): boolean {
  const campos: typeof erroresCampo.value = {}

  if (!usuario.value.trim()) {
    campos.usuario = 'El usuario es obligatorio.'
  }
  if (!contrasena.value) {
    campos.contrasena = 'La contraseña es obligatoria.'
  }

  erroresCampo.value = campos
  return Object.keys(campos).length === 0
}

async function enviar(): Promise<void> {
  error.value = ''

  if (!validar()) {
    return
  }

  try {
    await auth.login(usuario.value.trim(), contrasena.value, recordar.value)

    const destino = route.query.redirect
    await router.push(typeof destino === 'string' ? destino : { name: 'inicio' })
  } catch (fallo) {
    error.value = mensajeDeError(fallo)
    contrasena.value = ''
  }
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="enviar">
    <div>
      <label for="usuario" class="mb-1 block text-sm font-medium text-slate-700">
        Usuario
      </label>
      <input
        id="usuario"
        v-model="usuario"
        type="text"
        autocomplete="username"
        autofocus
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition-colors focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
        :class="erroresCampo.usuario ? 'border-red-400' : ''"
        placeholder="su.usuario"
        @input="erroresCampo.usuario = undefined"
      />
      <p v-if="erroresCampo.usuario" class="mt-1 text-xs text-red-600">
        {{ erroresCampo.usuario }}
      </p>
    </div>

    <div>
      <label for="contrasena" class="mb-1 block text-sm font-medium text-slate-700">
        Contraseña
      </label>
      <input
        id="contrasena"
        v-model="contrasena"
        type="password"
        autocomplete="current-password"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition-colors focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
        :class="erroresCampo.contrasena ? 'border-red-400' : ''"
        placeholder="••••••••"
        @input="erroresCampo.contrasena = undefined"
      />
      <p v-if="erroresCampo.contrasena" class="mt-1 text-xs text-red-600">
        {{ erroresCampo.contrasena }}
      </p>
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-600">
      <input
        v-model="recordar"
        type="checkbox"
        class="h-4 w-4 rounded border-slate-300 text-marca-600 focus:ring-marca-500"
      />
      Mantener la sesión iniciada
    </label>

    <p
      v-if="error"
      class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      role="alert"
    >
      {{ error }}
    </p>

    <button
      type="submit"
      class="flex w-full items-center justify-center gap-2 rounded-lg bg-marca-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-marca-700 disabled:cursor-not-allowed disabled:opacity-60"
      :disabled="auth.cargando"
    >
      <span
        v-if="auth.cargando"
        class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
        aria-hidden="true"
      />
      {{ auth.cargando ? 'Ingresando…' : 'Ingresar' }}
    </button>
  </form>
</template>
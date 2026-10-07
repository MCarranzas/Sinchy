<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { erroresDeValidacion, mensajeDeError } from '@/api/axios'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const actual = ref('')
const nueva = ref('')
const confirmacion = ref('')

const error = ref('')
const erroresCampo = ref<{
  actual?: string
  nueva?: string
  confirmacion?: string
}>({})

/**
 * Las mismas reglas que ChangePasswordRequest en el backend (obligatoria,
 * confirmed, Password::min(8)->letters()->numbers(), distinta de la actual)
 * para no gastar una peticion en algo que ya se puede decidir aqui.
 */
function validar(): boolean {
  const campos: typeof erroresCampo.value = {}

  if (!actual.value) {
    campos.actual = 'Debe indicar la contraseña actual.'
  }

  if (!nueva.value) {
    campos.nueva = 'La nueva contraseña es obligatoria.'
  } else if (nueva.value.length < 8) {
    campos.nueva = 'La nueva contraseña debe tener al menos 8 caracteres.'
  } else if (!/[a-zA-Z]/.test(nueva.value) || !/[0-9]/.test(nueva.value)) {
    campos.nueva = 'La nueva contraseña debe incluir letras y números.'
  } else if (nueva.value === actual.value) {
    campos.nueva = 'La nueva contraseña debe ser distinta de la actual.'
  }

  if (!confirmacion.value) {
    campos.confirmacion = 'La confirmación no coincide con la nueva contraseña.'
  } else if (confirmacion.value !== nueva.value) {
    campos.confirmacion = 'La confirmación no coincide con la nueva contraseña.'
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
    await auth.cambiarPassword(actual.value, nueva.value)

    const destino = route.query.redirect
    await router.push(typeof destino === 'string' ? destino : { name: 'inicio' })
  } catch (fallo) {
    const delBackend = erroresDeValidacion(fallo)
    erroresCampo.value = {
      actual: delBackend.password_actual,
      nueva: delBackend.password,
      confirmacion: delBackend.password_confirmation,
    }
    // Si el backend no devuelve error por campo (419, 429, 403) queda el generico.
    if (Object.keys(delBackend).length === 0) {
      error.value = mensajeDeError(fallo)
    }
  }
}

async function salir(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-lg font-semibold text-slate-900">Cambie su contraseña</h1>
      <p class="mt-1 text-sm text-slate-600">
        La contraseña con la que ingresó es temporal y no puede seguir en uso.
        Defina una nueva para continuar al sistema.
      </p>
    </div>

    <form class="space-y-4" novalidate @submit.prevent="enviar">
      <div>
        <label for="actual" class="mb-1 block text-sm font-medium text-slate-700">
          Contraseña actual
        </label>
        <input
          id="actual"
          v-model="actual"
          type="password"
          autocomplete="current-password"
          autofocus
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition-colors focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          :class="erroresCampo.actual ? 'border-red-400' : ''"
          placeholder="••••••••"
          @input="erroresCampo.actual = undefined"
        />
        <p v-if="erroresCampo.actual" class="mt-1 text-xs text-red-600">
          {{ erroresCampo.actual }}
        </p>
      </div>

      <div>
        <label for="nueva" class="mb-1 block text-sm font-medium text-slate-700">
          Nueva contraseña
        </label>
        <input
          id="nueva"
          v-model="nueva"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition-colors focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          :class="erroresCampo.nueva ? 'border-red-400' : ''"
          placeholder="••••••••"
          @input="erroresCampo.nueva = undefined"
        />
        <p v-if="erroresCampo.nueva" class="mt-1 text-xs text-red-600">
          {{ erroresCampo.nueva }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
          Mínimo 8 caracteres, con letras y números, y distinta de la actual.
        </p>
      </div>

      <div>
        <label for="confirmacion" class="mb-1 block text-sm font-medium text-slate-700">
          Confirmar nueva contraseña
        </label>
        <input
          id="confirmacion"
          v-model="confirmacion"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition-colors focus:border-marca-500 focus:ring-2 focus:ring-marca-100"
          :class="erroresCampo.confirmacion ? 'border-red-400' : ''"
          placeholder="••••••••"
          @input="erroresCampo.confirmacion = undefined"
        />
        <p v-if="erroresCampo.confirmacion" class="mt-1 text-xs text-red-600">
          {{ erroresCampo.confirmacion }}
        </p>
      </div>

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
        {{ auth.cargando ? 'Guardando…' : 'Guardar contraseña' }}
      </button>
    </form>

    <button
      type="button"
      class="text-sm font-medium text-slate-500 transition-colors hover:text-slate-700"
      @click="salir"
    >
      Cerrar sesión
    </button>
  </div>
</template>

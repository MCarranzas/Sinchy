import axios from 'axios'

/**
 * Cliente HTTP unico de la aplicacion.
 *
 * La autenticacion es por COOKIE DE SESION (Sanctum SPA), no por token, asi
 * que TODA peticion viaja con `withCredentials`. Sin esto el navegador no
 * manda la cookie de sesion y el backend responde 401 aunque el login haya
 * funcionado.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

/**
 * Pide la cookie XSRF-TOKEN a Sanctum.
 *
 * Laravel exige el token CSRF en toda peticion que muta estado (POST/PUT/
 * DELETE). El token NO viaja en el cuerpo: se entrega como cookie legible por
 * JavaScript y axios la reintroduce como cabecera X-XSRF-TOKEN por su cuenta.
 * Hay que pedirla antes del login o el backend responde 419.
 *
 * Se usa una instancia limpia, sin `baseURL` ni `Content-Type` JSON, porque
 * `/sanctum/csrf-cookie` vive en la raiz del backend y no en `/api/v1`.
 */
export async function obtenerTokenCsrf(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', {
    withCredentials: true,
    headers: { Accept: 'application/json' },
  })
}

let manejadorNoAutorizado: (() => void) | null = null

/**
 * Registra la accion a ejecutar cuando el backend responde 401.
 *
 * Se resuelve por callback y no importando el store o el router aqui para no
 * crear un ciclo de importaciones (store -> api -> store).
 */
export function alPerderSesion(manejador: () => void): void {
  manejadorNoAutorizado = manejador
}

api.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    const estado = error?.response?.status

    if (estado === 401) {
      manejadorNoAutorizado?.()
    }

    return Promise.reject(error)
  },
)

interface ErrorValidacion {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Mensaje legible de un error de axios.
 *
 * Laravel devuelve `{message, errors}` en 422; para el resto de casos se usa
 * `message` y, como ultimo recurso, un texto generico. Importante: el 419 de
 * CSRF es un error de sesion caducada y merece un mensaje propio.
 */
export function mensajeDeError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const estado = error.response?.status
    const datos = error.response?.data as ErrorValidacion | undefined

    if (estado === 419) {
      return 'La sesión expiró. Vuelva a iniciar sesión.'
    }

    if (datos?.errors) {
      const primero = Object.values(datos.errors)[0]
      if (Array.isArray(primero) && primero.length > 0) {
        return primero[0]
      }
    }

    if (datos?.message) {
      return datos.message
    }

    if (estado === 429) {
      return 'Demasiados intentos. Espere unos minutos e intente de nuevo.'
    }

    if (estado === 403) {
      return 'No tiene permisos para realizar esta acción.'
    }
  }

  return 'Ocurrió un error inesperado. Intente nuevamente.'
}

/**
 * Errores de validacion por campo de un 422, para marcar el input exacto en
 * lugar de dejar solo un mensaje generico. El 422 de Laravel trae
 * `errors: {campo: [mensaje, ...]}`; cualquier otro fallo devuelve un objeto
 * vacio y el llamador cae en `mensajeDeError`.
 */
export function erroresDeValidacion(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error)) {
    return {}
  }

  const datos = error.response?.data as ErrorValidacion | undefined
  const salida: Record<string, string> = {}

  for (const [campo, mensajes] of Object.entries(datos?.errors ?? {})) {
    if (Array.isArray(mensajes) && mensajes.length > 0) {
      salida[campo] = mensajes[0]
    }
  }

  return salida
}
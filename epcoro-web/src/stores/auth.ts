import { defineStore } from 'pinia'

import { alPerderSesion, api, obtenerTokenCsrf } from '@/api/axios'
import type { RespuestaLogin, RespuestaPerfil, Usuario } from '@/types/auth'

interface EstadoAuth {
  usuario: Usuario | null
  cargada: boolean
  cargando: boolean
}

/**
 * Estado de sesion del usuario actual.
 *
 * `cargada` distingue "todavia no preguntamos al backend" de "preguntamos y no
 * hay sesion". Sin esa bandera el guard del router no sabria si redirigir a
 * login por falta real de sesion o si solo esta esperando la respuesta.
 */
export const useAuthStore = defineStore('auth', {
  state: (): EstadoAuth => ({
    usuario: null,
    cargada: false,
    cargando: false,
  }),

  getters: {
    autenticado: (estado): boolean => estado.usuario !== null,

    rol: (estado): string | null => estado.usuario?.rol ?? null,

    permisos: (estado): string[] => estado.usuario?.permisos ?? [],

    /**
     * La bandera que el backend levanta cuando la clave vigente fue
     * generada (o recien cambiada) y aun no la redefine el usuario. Mientras
     * sea true el router no deja entrar a ninguna otra ruta.
     */
    debeCambiarPassword: (estado): boolean =>
      estado.usuario?.debe_cambiar_password ?? false,

    /**
     * Comprueba un permiso concreto. El admin recibe todos los permisos
     * explicitos desde el backend, asi que no se hardcodea aqui ningun rol.
     */
    puede: (estado) => {
      return (permiso: string): boolean =>
        estado.usuario?.permisos.includes(permiso) ?? false
    },
  },

  actions: {
    /**
     * Inicia sesion contra la API.
     *
     * El orden importa: primero se pide la cookie CSRF y solo despues se
     * envia el login. Al terminar se marca `cargada` porque ya se conoce al
     * usuario sin necesidad de llamar a `/auth/me`.
     */
    async login(username: string, password: string, remember = false): Promise<Usuario> {
      this.cargando = true

      try {
        await obtenerTokenCsrf()

        const { data } = await api.post<RespuestaLogin>('/auth/login', {
          username,
          password,
          remember,
        })

        this.usuario = data.usuario
        this.cargada = true

        return data.usuario
      } finally {
        this.cargando = false
      }
    },

    /**
     * Recupera la sesion activa al abrir la aplicacion.
     *
     * Un 401 es la respuesta normal de "no hay sesion", no un fallo: se limpia
     * el usuario y se marca la carga como terminada para que el guard sepa que
     * debe mandar a login. Cualquier otro error tambien deja el estado en
     * "cargada" para no colgar la navegacion.
     */
    async cargarSesion(): Promise<void> {
      if (this.cargada) {
        return
      }

      this.cargando = true

      try {
        const { data } = await api.get<RespuestaPerfil>('/auth/me')
        this.usuario = data.usuario
      } catch {
        this.usuario = null
      } finally {
        this.cargada = true
        this.cargando = false
      }
    },

    async logout(): Promise<void> {
      try {
        await api.post('/auth/logout')
      } finally {
        this.limpiar()
      }
    },

    /**
     * Cambia la contraseña del usuario autenticado y baja la bandera de
     * cambio obligatorio.
     *
     * El backend solo devuelve `{message}`: el usuario en memoria se actualiza
     * aqui mismo porque el guard del router se fija en `debe_cambiar_password`
     * para decidir si deja pasar o no, y esperar a `/auth/me` dejaria una
     * ventana en la que la app creeria que aun hay que cambiar la clave.
     */
    async cambiarPassword(passwordActual: string, password: string): Promise<string> {
      this.cargando = true

      try {
        const { data } = await api.post<{ message: string }>('/auth/change-password', {
          password_actual: passwordActual,
          password,
          password_confirmation: password,
        })

        if (this.usuario) {
          this.usuario = { ...this.usuario, debe_cambiar_password: false }
        }

        return data.message
      } finally {
        this.cargando = false
      }
    },

    /**
     * Vacía el usuario sin tocar el backend. Lo usa el interceptor de 401,
     * donde la sesion ya se perdio y volver a llamar a la API seria inutil.
     */
    limpiar(): void {
      this.usuario = null
      this.cargada = true
    },
  },
})

/**
 * Puente entre axios y el store: cuando cualquier peticion recibe un 401 se
 * descarta la sesion en memoria. La redireccion a login la decide el router,
 * que observa el estado del store.
 */
alPerderSesion(() => {
  useAuthStore().limpiar()
})
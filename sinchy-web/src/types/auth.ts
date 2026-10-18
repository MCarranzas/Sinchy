export interface Usuario {
  id: number
  username: string
  email: string | null
  nombre_completo: string
  rol: string
  permisos: string[]
  activo: boolean
  debe_cambiar_password: boolean
  ultimo_acceso: string | null
}

export interface RespuestaLogin {
  message: string
  usuario: Usuario
}

export interface RespuestaPerfil {
  usuario: Usuario
}
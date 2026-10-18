/**
 * Cliente del recurso liquidaciones (8.10-8.12).
 *
 * Nota de paginacion: el backend pagina con `page` (Laravel) y `por_pagina`.
 * El listado refleja ese `page` en la URL de la ruta para que un enlace
 * copiado abra exactamente la misma pagina (8.10, paginacion por URL).
 */

import { api } from '@/api/axios'
import type {
  BandejaConteos,
  FiltrosLiquidacion,
  HistorialEstado,
  Liquidacion,
  LiquidacionPayload,
  TransicionPayload,
} from '@/types/liquidacion'

/** Lo que Laravel devuelve en `links`/`meta` del paginador. */
export interface PaginaLiquidaciones {
  data: Liquidacion[]
  links: Record<string, string | null>
  meta: {
    current_page: number
    from: number | null
    last_page: number
    links: { url: string | null; label: string; active: boolean }[]
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

function limpiar(filtros: FiltrosLiquidacion): Record<string, unknown> {
  const salida: Record<string, unknown> = {}

  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== null && valor !== '') {
      salida[clave] = valor
    }
  }

  return salida
}

export async function listarLiquidaciones(filtros: FiltrosLiquidacion): Promise<PaginaLiquidaciones> {
  const { data } = await api.get<PaginaLiquidaciones>('/liquidaciones', { params: limpiar(filtros) })
  return data
}

export async function obtenerLiquidacion(id: number): Promise<Liquidacion> {
  const { data } = await api.get<{ data: Liquidacion }>(`/liquidaciones/${id}`)
  return data.data
}

export async function crearLiquidacion(payload: LiquidacionPayload): Promise<Liquidacion> {
  const { data } = await api.post<{ data: Liquidacion }>('/liquidaciones', payload)
  return data.data
}

export async function actualizarLiquidacion(id: number, payload: LiquidacionPayload): Promise<Liquidacion> {
  const { data } = await api.put<{ data: Liquidacion }>(`/liquidaciones/${id}`, payload)
  return data.data
}

export async function historialLiquidacion(id: number): Promise<HistorialEstado[]> {
  const { data } = await api.get<{ data: HistorialEstado[] }>(`/liquidaciones/${id}/historial`)
  return data.data
}

export async function observarLiquidacion(id: number, comentario: string): Promise<void> {
  await api.post(`/liquidaciones/${id}/observaciones`, { comentario })
}

/**
 * Mueve una liquidacion por la maquina de estados (9.1).
 *
 * El backend devuelve el recurso recalculado (con su nuevo estado y sus
 * `transiciones_disponibles`), asi que el detalle se refresca con la misma
 * respuesta y no hace falta una segunda peticion de lectura.
 */
export async function transicionarLiquidacion(id: number, payload: TransicionPayload): Promise<Liquidacion> {
  const { data } = await api.post<{ message: string; data: Liquidacion }>(
    `/liquidaciones/${id}/transiciones`,
    payload,
  )
  return data.data
}

/**
 * Conteos de la bandeja (9.5). El backend deja en cero la cola que el rol no
 * puede tocar, y la vista esconde esa pestana.
 */
export async function obtenerBandeja(): Promise<BandejaConteos> {
  const { data } = await api.get<{ data: BandejaConteos }>('/bandeja')
  return data.data
}

export async function eliminarLiquidacion(id: number): Promise<void> {
  await api.delete(`/liquidaciones/${id}`)
}

/**
 * Enlaces de descarga (8.12). Van por el proxy de Vite como todo lo demas y
 * el navegador los descarga con la cookie de sesion: no hay endpoint de
 * "token temporal" y no hace falta ninguno en desarrollo.
 */
export function urlPdf(id: number): string {
  return `/api/v1/liquidaciones/${id}/pdf`
}

export function urlExcel(id: number): string {
  return `/api/v1/liquidaciones/${id}/excel`
}

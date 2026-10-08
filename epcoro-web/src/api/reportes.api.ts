/**
 * Cliente de reportes (FASE 10).
 *
 * El rango va siempre de a dos (desde/hasta): el backend normaliza `desde`/
 * `hasta` y el listado usa `fecha_desde`/`fecha_hasta`, asi que aqui se usa
 * el mismo par del listado para no inventar un tercer nombre.
 */

import { api } from '@/api/axios'
import type { ResumenReporte } from '@/types/reportes'

export async function obtenerResumen(fechaDesde?: string, fechaHasta?: string): Promise<ResumenReporte> {
  const { data } = await api.get<{ data: ResumenReporte }>('/reportes/resumen', {
    params: {
      fecha_desde: fechaDesde || undefined,
      fecha_hasta: fechaHasta || undefined,
    },
  })
  return data.data
}

/**
 * Enlace de descarga del reporte (10.5). Misma tecnica que el PDF/excel de
 * una liquidacion: el navegador descarga con la cookie de sesion a traves
 * del proxy de Vite, asi que basta un <a href>.
 */
export function urlExportar(formato: 'xlsx' | 'pdf', fechaDesde?: string, fechaHasta?: string): string {
  const parametros = new URLSearchParams()
  parametros.set('formato', formato)
  if (fechaDesde) {
    parametros.set('fecha_desde', fechaDesde)
  }
  if (fechaHasta) {
    parametros.set('fecha_hasta', fechaHasta)
  }

  return `/api/v1/reportes/exportar?${parametros.toString()}`
}
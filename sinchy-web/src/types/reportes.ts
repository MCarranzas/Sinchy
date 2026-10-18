/**
 * Forma de las respuestas de reportes (FASE 10), espejo de ReporteController.
 *
 * Los montos van como string (T3): las sumas las hace PostgreSQL y no deben
 * pasar por Number en el recorrido de la consulta al navegador. El cliente
 * solo convierte a Number al trazar un grafico, donde la precision de
 * pantalla es irrelevante y Chart.js lo exige.
 */

export interface FilaAgrupada {
  valor: string | null
  liquidaciones: number
  bruto_total: string
  neto_total: string
}

export interface GrupoReporte {
  grupo: string
  filas: FilaAgrupada[]
}

export interface TotalesReporte {
  liquidaciones: number
  peso_total: string
  peso_fino_total: string
  bruto_total: string
  aportes_total: string
  deducciones_total: string
  neto_total: string
}

export interface ResumenReporte {
  rango: { desde: string | null; hasta: string | null }
  totales: TotalesReporte
  por_estado: GrupoReporte
  por_municipio: GrupoReporte
  por_mineral: GrupoReporte
  por_area_minera: GrupoReporte
  por_mes: GrupoReporte
}
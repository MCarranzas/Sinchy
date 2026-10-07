/**
 * La forma de una liquidacion en el cliente: el ESPEJO de
 * app/Http/Resources/LiquidacionResource.php.
 *
 * Los montos van como string, igual que el backend los devuelve (T3): nunca
 * Number, porque el paso por float pierde el centavo que separa 1072018.84 de
 * 1072018.83.
 */

import type { UbicacionArea } from '@/types/catalogos'

export type EstadoLiquidacion =
  | 'creado'
  | 'en_revision'
  | 'revisado'
  | 'en_correccion'
  | 'aprobado'
  | 'rechazado'
  | 'anulado'
  | 'eliminado'

/** Etiqutas de estado para selects y badges (espejo de EstadoLiquidacion::etiqueta). */
export const ETIQUETAS_ESTADO: Record<EstadoLiquidacion, string> = {
  creado: 'Creado',
  en_revision: 'En revisión',
  revisado: 'Revisado',
  en_correccion: 'En corrección',
  aprobado: 'Aprobado',
  rechazado: 'Rechazado',
  anulado: 'Anulado',
  eliminado: 'Eliminado',
}

/** Solo en estos estados la edicion esta permitida (Liquidacion::esEditable). */
export const ESTADOS_EDITABLES: EstadoLiquidacion[] = ['creado', 'en_correccion']

export interface InsumosLiquidacion {
  peso_gramos: string
  ley_pagable: string
  tipo_cambio: string
  cotizacion_usd_oz_t: string
  cotizacion_id: number | null
  aplica_caja_salud: boolean
  gramos_por_onza_troy: string
}

export interface CalculoLiquidacion {
  peso_fino_gramos: string
  valor_usd_gramo: string
  valor_bruto_bs: string
  aporte_caja_salud_bs: string
  total_deducciones_bs: string
  valor_neto_bs: string
}

export interface DeduccionLiquidacion {
  id?: number
  concepto: string
  tipo: string
  alicuota: string | null
  base_monto: string
  monto: string
  obligatorio: boolean
}

export interface Liquidacion {
  id: number
  numero_documento: string
  codigo_formulario: string
  correlativo: number | null
  ejercicio: number
  fecha_transaccion: string
  estado: EstadoLiquidacion
  estado_label: string
  transiciones_disponibles: string[]
  insumos: InsumosLiquidacion
  calculo: CalculoLiquidacion
  comprador: {
    persona_id: number | null
    razon_social: string
    nim: string | null
    nit: string | null
  }
  vendedor: {
    persona_id: number
    nombre: string
    ci: string
  }
  procedencia: {
    area_minera_id: number
    nombre_area_minera: string
    titular_derecho_minero: string
    codigo_ajam_patente: string | null
    departamento: { id: number; nombre: string }
    provincia: { id: number; nombre: string }
    municipio: { id: number; nombre: string; codigo: string | null }
    localidad: { id: number; nombre: string }
  }
  presentacion: { id: number; nombre: string }
  observaciones: string | null
  deducciones?: DeduccionLiquidacion[]
  auditoria: {
    registrado_por: { id: number; nombre_completo: string; username: string } | null
    fecha_registro: string | null
    fecha_revision: string | null
    fecha_aprobacion: string | null
    fecha_rechazo: string | null
    motivo_rechazo: string | null
    fecha_anulacion: string | null
    motivo_anulacion: string | null
  }
}

/**
 * Payload de alta/edicion: EXACTAMENTE los campos que acepta
 * StoreLiquidacionRequest / UpdateLiquidacionRequest. Si aqui aparece un
 * campo que alla no valida, el backend lo ignora; si falta uno obligatorio,
 * el 422 lo dira.
 */
export interface LiquidacionPayload {
  numero_documento?: string | null
  fecha_transaccion: string
  observaciones?: string | null
  tipo_cambio: string
  cotizacion_usd_oz_t: string
  cotizacion_id?: number | null
  aplica_caja_salud: boolean
  peso_gramos: string
  ley_pagable: string
  deducciones?: { concepto: string; monto: string }[]
  comprador_persona_id?: number | null
  comprador_razon_social: string
  comprador_nim?: string | null
  comprador_nit?: string | null
  vendedor_persona_id: number
  vendedor_ci: string
  area_minera_id: number
  departamento_id: number
  provincia_id: number
  municipio_id: number
  localidad_id: number
  codigo_municipio?: string | null
  codigo_ajam_patente?: string | null
  presentacion_mineral_id: number
}

/**
 * Filtros del index. El backend los recibe en snake_case y pagina con
 * `page` (el estandar de Laravel), no con `pagina`, para que el enlace de
 * paginacion quede compartible tal cual (?page=2).
 */
export interface FiltrosLiquidacion {
  estado?: string
  fecha_desde?: string
  fecha_hasta?: string
  municipio_id?: number | null
  area_minera_id?: number | null
  vendedor_id?: number | null
  q?: string
  registrado_por?: number | string
  orden?: 'fecha' | 'numero' | 'neto' | 'bruto'
  direccion?: 'asc' | 'desc'
  page?: number
  por_pagina?: number
}

/**
 * Conteos de la bandeja (GET /api/v1/bandeja, FASE 9.5). El backend pone en
 * cero las colas que el rol del usuario no puede tocar, asi que la UI no
 * necesita saber de permisos para esconder pestanas: las esconde cuando el
 * conteo de la cola es irrelevante para su rol.
 */
export interface BandejaConteos {
  mis_liquidaciones: number
  por_revisar: number
  por_aprobar: number
}

/** Payload del POST /liquidaciones/{id}/transiciones (TransicionRequest). */
export interface TransicionPayload {
  estado_destino: EstadoLiquidacion
  /** Opcional siempre: es el comentario de la fila de historial. */
  comentario?: string
  /**
   * Obligatorio en `rechazado` y `anulado`: es la razon que el tecnico
   * necesita para saber que arreglar. En el resto de destinos no se manda.
   */
  motivo?: string
}

export interface HistorialEstado {
  id: number
  estado_anterior: string | null
  estado_nuevo: string
  usuario: { id: number; nombre_completo: string }
  comentario: string | null
  fecha: string | null
}

/** Ubicacion reutilizable para SelectorUbicacion y para prellenar ediciones. */
export type UbicacionSeleccionada = Partial<UbicacionArea>

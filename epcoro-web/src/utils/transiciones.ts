/**
 * Espijo del mapa `TransicionesDisponibles::habilidad()` del backend:
 * a cada destino le corresponde una accion humana, y esa accion depende del
 * origen solo en un caso (`rechazado` desde `en_revision` es "solicitar
 * correccion" y desde `revisado` es "rechazar"). La barra de estado vive de
 * esta tabla para que el boton diga lo que el usuario va a hacer, y no el
 * nombre crudo del estado.
 */

import type { EstadoLiquidacion } from '@/types/liquidacion'

export interface AccionTransicion {
  destino: EstadoLiquidacion
  etiqueta: string
  requiereMotivo: boolean
  /** `motivo` y `comentario` viajan juntos, y el backend exige uno u otro. */
  variante: 'primario' | 'secundario' | 'peligro'
}

const POR_DESTINO: Record<EstadoLiquidacion, Omit<AccionTransicion, 'destino'>> = {
  en_revision: { etiqueta: 'Enviar a revisión', requiereMotivo: false, variante: 'primario' },
  revisado: { etiqueta: 'Marcar revisado', requiereMotivo: false, variante: 'primario' },
  en_correccion: { etiqueta: 'Enviar a corrección', requiereMotivo: false, variante: 'secundario' },
  aprobado: { etiqueta: 'Aprobar', requiereMotivo: false, variante: 'primario' },
  rechazado: { etiqueta: 'Rechazar', requiereMotivo: true, variante: 'peligro' },
  anulado: { etiqueta: 'Anular', requiereMotivo: true, variante: 'peligro' },
  eliminado: { etiqueta: 'Eliminar', requiereMotivo: false, variante: 'peligro' },
  creado: { etiqueta: '', requiereMotivo: false, variante: 'secundario' },
}

export function accionTransicion(origen: EstadoLiquidacion, destino: EstadoLiquidacion): AccionTransicion {
  const base = POR_DESTINO[destino]

  if (destino === 'rechazado' && origen === 'en_revision') {
    return { ...base, etiqueta: 'Solicitar corrección', destino }
  }

  return { ...base, destino }
}

/**
 * Las pestanas de la bandeja: id para la URL, etiqueta y el filtro de estado
 * que materializa la lista. El conteo viene del backend y decide la visibilidad.
 */
export interface PestanaBandeja {
  id: 'mis_liquidaciones' | 'por_revisar' | 'por_aprobar'
  etiqueta: string
  estado?: EstadoLiquidacion
}
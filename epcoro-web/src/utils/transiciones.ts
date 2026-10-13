/**
 * Espijo del mapa `TransicionesDisponibles::habilidad()` del backend:
 * a cada destino le corresponde una accion humana. Hacia `rechazado` hay un
 * solo camino (`rechazar`), valido desde `en_revision` y desde `revisado`, y
 * el boton siempre dice "Rechazar". La barra de estado vive de esta tabla
 * para que el boton diga lo que el usuario va a hacer, y no el nombre crudo
 * del estado.
 */

import type { EstadoLiquidacion } from '@/types/liquidacion'

export interface AccionTransicion {
  destino: EstadoLiquidacion
  etiqueta: string
  requiereMotivo: boolean
  /** `motivo` para rechazar/anular; `comentario` para devolver/observación. */
  variante: 'primario' | 'secundario' | 'peligro'
  campo?: 'motivo' | 'comentario'
}

const POR_DESTINO: Record<EstadoLiquidacion, Omit<AccionTransicion, 'destino'>> = {
  en_revision: { etiqueta: 'Enviar a revisión', requiereMotivo: false, variante: 'primario', campo: 'comentario' },
  revisado: { etiqueta: 'Marcar revisado', requiereMotivo: false, variante: 'primario', campo: 'comentario' },
  en_correccion: { etiqueta: 'Devolver al técnico', requiereMotivo: true, variante: 'secundario', campo: 'comentario' },
  aprobado: { etiqueta: 'Aprobar', requiereMotivo: false, variante: 'primario', campo: 'comentario' },
  rechazado: { etiqueta: 'Rechazar', requiereMotivo: true, variante: 'peligro', campo: 'motivo' },
  anulado: { etiqueta: 'Anular', requiereMotivo: true, variante: 'peligro', campo: 'motivo' },
  eliminado: { etiqueta: 'Eliminar', requiereMotivo: false, variante: 'peligro', campo: 'comentario' },
  creado: { etiqueta: '', requiereMotivo: false, variante: 'secundario', campo: 'comentario' },
}

export function accionTransicion(origen: EstadoLiquidacion, destino: EstadoLiquidacion): AccionTransicion {
  const base = POR_DESTINO[destino]

  if (destino === 'en_revision' && origen === 'revisado') {
    return {
      ...base,
      destino,
      etiqueta: 'Devolver al revisor',
      requiereMotivo: true,
      campo: 'comentario',
    }
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
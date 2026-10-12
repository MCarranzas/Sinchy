/**
 * Clientes de los catalogos (8.2-8.6).
 *
 * Cada funcion es una linea fina sobre `api`: sin estado y sin cache, porque
 * los catalogos los resuelve el store (que si cachea) y aqui solo vive el
 * contrato HTTP.
 */

import { api } from '@/api/axios'
import type {
  AreaMinera,
  Comprador,
  CotizacionQuincenal,
  Departamento,
  Localidad,
  Municipio,
  NuevaPersona,
  ParametroGeneral,
  Persona,
  PresentacionMineral,
  Provincia,
  TipoCambioDia,
} from '@/types/catalogos'

async function lista<T>(url: string, params?: Record<string, unknown>): Promise<T[]> {
  const { data } = await api.get<{ data: T[] }>(url, { params })
  return data.data
}

export function listarDepartamentos(): Promise<Departamento[]> {
  return lista<Departamento>('/catalogos/departamentos')
}

export function listarProvincias(departamentoId: number): Promise<Provincia[]> {
  return lista<Provincia>('/catalogos/provincias', { departamento_id: departamentoId })
}

export function listarMunicipios(provinciaId: number): Promise<Municipio[]> {
  return lista<Municipio>('/catalogos/municipios', { provincia_id: provinciaId })
}

/**
 * Buscador de municipios por nombre o provincia (8.10, filtro del listado).
 * El backend trae ademas `provincia_nombre` y `departamento_nombre` para el
 * detalle de cada opcion del autocompletado.
 */
export function buscarMunicipios(q?: string): Promise<Municipio[]> {
  return lista<Municipio>('/catalogos/municipios', q ? { q } : undefined)
}

export function listarLocalidades(municipioId: number): Promise<Localidad[]> {
  return lista<Localidad>('/catalogos/localidades', { municipio_id: municipioId })
}

/**
 * Buscador de areas con su ubicacion completa (8.5): la seleccion autocompleta
 * los cuatro niveles de una, y el backend despues exige que coincidan.
 */
export function buscarAreasMineras(q?: string): Promise<AreaMinera[]> {
  return lista<AreaMinera>('/catalogos/areas-mineras', q ? { q } : undefined)
}

/** Buscador de personas por nombre, CI o NIT (8.4). */
export function buscarPersonas(q?: string): Promise<Persona[]> {
  return lista<Persona>('/catalogos/personas', q ? { q } : undefined)
}

/**
 * Alta rapida de persona (8.4). Si el CI ya existia el backend devuelve 200
 * con la persona vieja; si es nueva, 201. En los dos casos `data` es la fila
 * que hay que usar como vendedor.
 */
export async function guardarPersona(persona: NuevaPersona): Promise<Persona> {
  const { data } = await api.post<{ data: Persona }>('/catalogos/personas', persona)
  return data.data
}

export function listarPresentaciones(): Promise<PresentacionMineral[]> {
  return lista<PresentacionMineral>('/catalogos/presentaciones-mineral')
}

/** Catalogo de compradores (C11-C13). Hoy trae una sola fila: EPCORO. */
export function listarCompradores(): Promise<Comprador[]> {
  return lista<Comprador>('/catalogos/compradores')
}

export function listarCotizaciones(ejercicio?: number): Promise<CotizacionQuincenal[]> {
  return lista<CotizacionQuincenal>('/cotizaciones', ejercicio ? { ejercicio } : undefined)
}

/**
 * Tipo de cambio oficial cargado para una fecha (8.2). Devuelve `null` cuando
 * ese dia no tiene valor: el formulario lo muestra como aviso, no lo inventa,
 * porque el backend despues corta el alta con 422.
 */
export async function obtenerTipoCambio(fecha: string): Promise<TipoCambioDia | null> {
  const { data } = await api.get<{ data: TipoCambioDia | null }>('/catalogos/tipo-cambio', {
    params: { fecha },
  })

  return data.data
}

export async function listarParametros(): Promise<ParametroGeneral[]> {
  const { data } = await api.get<{ data: ParametroGeneral[] }>('/parametros')
  return data.data
}

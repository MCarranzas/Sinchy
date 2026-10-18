/**
 * Tipos de los catalogos que alimenta el formulario de captura (8.2-8.6).
 *
 * Todos son el ESPEJO de lo que devuelven los endpoints /api/v1/catalogos y
 * /api/v1/parametros: si aqui cambia un campo, la culpa es del backend y hay
 * que corregirlo alla primero, no inventar el dato en el cliente.
 */

export interface Departamento {
  id: number
  codigo: string | null
  nombre: string
}

export interface Provincia {
  id: number
  departamento_id: number
  codigo: string | null
  nombre: string
}

export interface Municipio {
  id: number
  provincia_id: number
  codigo: string | null
  nombre: string
  /**
   * Solo llegan con el buscador por `q` (filtro del listado): el detalle del
   * autocompletado. En la cascada del formulario no se piden y vienen nulos.
   */
  provincia_nombre?: string | null
  departamento_nombre?: string | null
}

export interface Localidad {
  id: number
  municipio_id: number
  codigo: string | null
  nombre: string
}

/**
 * La ubicacion que el buscador de areas trae en bloque: el formulario
 * autocompleta los cuatro niveles desde aqui y el backend despues exige que
 * coincidan con el area elegida (trait ValidaUbicacionDelArea).
 */
export interface UbicacionArea {
  departamento_id: number
  departamento_nombre: string
  provincia_id: number
  provincia_nombre: string
  municipio_id: number
  municipio_nombre: string
  codigo_municipio: string | null
  localidad_id: number
  localidad_nombre: string
}

export interface AreaMinera {
  id: number
  nombre_area_minera: string
  titular_derecho_minero: string
  codigo_ajam_patente: string | null
  activo: boolean
  ubicacion: UbicacionArea
}

export interface Persona {
  id: number
  nombre_completo: string
  nombres: string
  apellidos: string
  ci: string
  nit: string | null
  tipo_documento: string
  email: string | null
  telefono: string | null
}

/** Alta rapida de persona (8.4): el POST /catalogos/personas. */
export interface NuevaPersona {
  nombres: string
  apellidos: string
  ci: string
  nit?: string
  tipo_documento?: string
  email?: string
  telefono?: string
  direccion?: string
}

export interface PresentacionMineral {
  id: number
  nombre: string
}

/**
 * Comprador del mineral (tabla `compradores`, hoy una sola fila: SINCHI WAYRA,
 * celdas C11-C13). `nim`/`nit` van como string: son identificadores.
 */
export interface Comprador {
  id: number
  razon_social: string
  nim: string | null
  nit: string | null
}

/**
 * Se queda con `valor_usd_oz_t` como string a proposito (igual que el
 * modelo): los 4 decimales exactos no sobreviven a un paso por float.
 */
export interface CotizacionQuincenal {
  id: number
  ejercicio: number
  quincena: number
  fecha_inicio: string
  fecha_fin: string
  valor_usd_oz_t: string
  fuente: string | null
}

/**
 * El tipo de cambio oficial Bs/$ de un dia (tabla `tipos_cambio`). Ya no se
 * captura por liquidacion: el backend lo resuelve al guardar y el formulario
 * solo lo muestra de lectura. `valor` va como string por los 4 decimales.
 */
export interface TipoCambioDia {
  fecha: string
  valor: string
  fuente: string | null
}

export interface ParametroGeneral {
  clave: string
  valor: string
  tipo: string
  descripcion: string
  actualizado_en: string | null
}

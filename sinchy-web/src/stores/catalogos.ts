/**
 * Catalogos en memoria (8.2-8.6).
 *
 * Solo lo que el formulario de captura necesita y que casi no cambia durante
 * la sesion: parametros generales, departamentos, presentaciones y las
 * cotizaciones mas recientes. Las cascadas PROVINCIA/MUNICIPIO/LOCALIDAD no
 * se cachean aqui: dependen del padre y se piden al vuelo en
 * SelectorUbicacion.vue.
 *
 * `cargado` evita recargar en cada visita a la vista de captura; `cargando`
 * evita la carrera de dos watch que disparan la misma carga a la vez.
 */

import { defineStore } from 'pinia'

import {
  listarCompradores,
  listarCotizaciones,
  listarDepartamentos,
  listarParametros,
  listarPresentaciones,
} from '@/api/catalogos.api'
import type {
  Comprador,
  CotizacionQuincenal,
  Departamento,
  ParametroGeneral,
  PresentacionMineral,
} from '@/types/catalogos'

interface EstadoCatalogos {
  parametros: ParametroGeneral[]
  departamentos: Departamento[]
  presentaciones: PresentacionMineral[]
  compradores: Comprador[]
  cotizaciones: CotizacionQuincenal[]
  cargado: boolean
  cargando: boolean
}

/**
 * Carga en curso, compartida entre llamadores concurrentes.
 *
 * En escritorio la plantilla de captura monta las cinco secciones a la vez,
 * asi que `SelectorUbicacion` (dentro de Procedencia) dispara su `onMounted`
 * ANTES que el de `CreateView` (Vue monta a los hijos primero). Sin esta
 * promesa compartida, el segundo `cargar()` veia `cargando = true`, retornaba
 * enseguida y la vista seguia con los catalogos vacios: los defaults del
 * comprador (y de la cotizacion quincenal) se aplicaban sobre listas vacias y
 * no prellenaban nada. Con la promesa, el segundo llamador ESPERA a que la
 * carga termine.
 */
let promesaEnCurso: Promise<void> | null = null

export const useCatalogosStore = defineStore('catalogos', {
  state: (): EstadoCatalogos => ({
    parametros: [],
    departamentos: [],
    presentaciones: [],
    compradores: [],
    cotizaciones: [],
    cargado: false,
    cargando: false,
  }),

  getters: {
    /** Valor de un parametro por clave, o '' si aun no existe. */
    parametro: (estado) => {
      return (clave: string): string => estado.parametros.find((p) => p.clave === clave)?.valor ?? ''
    },

    /**
     * Defaults del formulario (8.3): el comprador (celdas C11-C13) llega del
     * catalogo `compradores`. El tipo de cambio ya no es un default: se
     * resuelve por fecha contra /catalogos/tipo-cambio.
     */
    gramosPorOnzaTroy: (estado): string =>
      estado.parametros.find((p) => p.clave === 'GRAMOS_POR_ONZA_TROY')?.valor ?? '31.1035',

    alicuotaAporte: (estado): string =>
      estado.parametros.find((p) => p.clave === 'ALICUOTA_APORTE')?.valor ?? '0.018',

    compradorFijo: (estado) => {
      const comprador = estado.compradores[0] ?? null

      return {
        razonSocial: comprador?.razon_social ?? '',
        nim: comprador?.nim ?? '',
        nit: comprador?.nit ?? '',
      }
    },

    /** La cotizacion mas reciente, la que el formulario sugiere al abrir. */
    cotizacionSugerida: (estado): CotizacionQuincenal | null => estado.cotizaciones[0] ?? null,
  },

  actions: {
    /**
     * Carga lo estatico una sola vez. Si algo falla no se marca `cargado`,
     * de modo que el siguiente intento vuelve a probar.
     *
     * Si ya hay una carga en curso, NO retorna enseguida: devuelve esa misma
     * promesa para que el llamador espere a los datos (ver `promesaEnCurso`).
     */
    async cargar(): Promise<void> {
      if (this.cargado) {
        return
      }

      if (promesaEnCurso !== null) {
        return promesaEnCurso
      }

      promesaEnCurso = (async () => {
        this.cargando = true

        try {
          const [parametros, departamentos, presentaciones, compradores, cotizaciones] = await Promise.all([
            listarParametros(),
            listarDepartamentos(),
            listarPresentaciones(),
            listarCompradores(),
            listarCotizaciones(),
          ])

          this.parametros = parametros
          this.departamentos = departamentos
          this.presentaciones = presentaciones
          this.compradores = compradores
          this.cotizaciones = cotizaciones
          this.cargado = true
        } finally {
          this.cargando = false
          promesaEnCurso = null
        }
      })()

      return promesaEnCurso
    },
  },
})

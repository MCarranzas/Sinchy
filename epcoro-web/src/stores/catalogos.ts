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
  listarCotizaciones,
  listarDepartamentos,
  listarParametros,
  listarPresentaciones,
} from '@/api/catalogos.api'
import type {
  CotizacionQuincenal,
  Departamento,
  ParametroGeneral,
  PresentacionMineral,
} from '@/types/catalogos'

interface EstadoCatalogos {
  parametros: ParametroGeneral[]
  departamentos: Departamento[]
  presentaciones: PresentacionMineral[]
  cotizaciones: CotizacionQuincenal[]
  cargado: boolean
  cargando: boolean
}

export const useCatalogosStore = defineStore('catalogos', {
  state: (): EstadoCatalogos => ({
    parametros: [],
    departamentos: [],
    presentaciones: [],
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
     * Defaults del formulario (8.2 y 8.3):
     * tipo de cambio sugerido (celda F6) y los tres datos fijos del
     * comprador (celdas C11-C13 de hoja.xlsx).
     */
    tipoCambioDefecto: (estado): string =>
      estado.parametros.find((p) => p.clave === 'TIPO_CAMBIO_DEFECTO')?.valor ?? '',

    gramosPorOnzaTroy: (estado): string =>
      estado.parametros.find((p) => p.clave === 'GRAMOS_POR_ONZA_TROY')?.valor ?? '31.1035',

    alicuotaAporte: (estado): string =>
      estado.parametros.find((p) => p.clave === 'ALICUOTA_APORTE')?.valor ?? '0.018',

    compradorFijo: (estado) => ({
      razonSocial: estado.parametros.find((p) => p.clave === 'NOMBRE_COMPRADOR')?.valor ?? '',
      nim: estado.parametros.find((p) => p.clave === 'NIM_COMPRADOR')?.valor ?? '',
      nit: estado.parametros.find((p) => p.clave === 'NIT_COMPRADOR')?.valor ?? '',
    }),

    /** La cotizacion mas reciente, la que el formulario sugiere al abrir. */
    cotizacionSugerida: (estado): CotizacionQuincenal | null => estado.cotizaciones[0] ?? null,
  },

  actions: {
    /**
     * Carga lo estatico una sola vez. Si algo falla no se marca `cargado`,
     * de modo que el siguiente intento vuelve a probar.
     */
    async cargar(): Promise<void> {
      if (this.cargado || this.cargando) {
        return
      }

      this.cargando = true

      try {
        const [parametros, departamentos, presentaciones, cotizaciones] = await Promise.all([
          listarParametros(),
          listarDepartamentos(),
          listarPresentaciones(),
          listarCotizaciones(),
        ])

        this.parametros = parametros
        this.departamentos = departamentos
        this.presentaciones = presentaciones
        this.cotizaciones = cotizaciones
        this.cargado = true
      } finally {
        this.cargando = false
      }
    },
  },
})

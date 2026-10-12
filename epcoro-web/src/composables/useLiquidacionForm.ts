/**
 * Formulario de captura (8.1-8.9).
 *
 * UN solo formulario para escritorio y celular: en escritorio se pintan las
 * cinco secciones seguidas; en celular el mismo formulario avanza paso a paso
 * (8.8) reutilizando los mismos componentes. Como los campos se registran
 * aqui (y no dentro de cada seccion), un paso desmontado no pierde ni sus
 * valores ni sus reglas: el wizard puede ir y venir sin borrar nada.
 *
 * Division de responsabilidades:
 *   - las REGLAS espejo del backend viven en composables/validaciones.ts;
 *   - este composable las aplica con vee-validate, arma el payload y
 *     previsualiza el calculo (utils/calculo.ts) con los parametros del
 *     store de catalogos;
 *   - las secciones SOLO pintan y enlazan (`formulario.valores.x`,
 *     `formulario.error(x)`, `formulario.tocar(x)`).
 */

import { useForm } from 'vee-validate'
import { computed, reactive, ref, watch, type Ref } from 'vue'

import { erroresDeValidacion } from '@/api/axios'
import { obtenerTipoCambio } from '@/api/catalogos.api'
import { useCatalogosStore } from '@/stores/catalogos'
import { calcular, type ResultadoCalculo } from '@/utils/calculo'
import { porcentajeAFraccion, fraccionAPorcentaje, reglas, type Regla } from '@/composables/validaciones'
import type { Liquidacion, LiquidacionPayload } from '@/types/liquidacion'

export interface ValoresCaptura {
  numero_documento: string
  fecha_transaccion: string
  tipo_cambio: string
  cotizacion_usd_oz_t: string
  cotizacion_id: number | null
  aplica_caja_salud: boolean
  comprador_razon_social: string
  comprador_nim: string
  comprador_nit: string
  vendedor_persona_id: number | null
  vendedor_ci: string
  area_minera_id: number | null
  departamento_id: number | null
  provincia_id: number | null
  municipio_id: number | null
  localidad_id: number | null
  codigo_municipio: string
  codigo_ajam_patente: string
  presentacion_mineral_id: number | null
  peso_gramos: string
  /** En PORCENTAJE (input con %, 8.6); la fraccion va solo en el payload. */
  ley_pagable: string
  observaciones: string
}

export interface PasoCaptura {
  id: string
  titulo: string
  campos: (keyof ValoresCaptura)[]
}

export const PASOS: PasoCaptura[] = [
  {
    id: 'transaccion',
    titulo: 'Transacción',
    campos: ['numero_documento', 'fecha_transaccion', 'tipo_cambio', 'cotizacion_usd_oz_t'],
  },
  {
    id: 'comprador',
    titulo: 'Comprador',
    campos: ['comprador_razon_social', 'comprador_nim', 'comprador_nit'],
  },
  {
    id: 'vendedor',
    titulo: 'Vendedor',
    campos: ['vendedor_persona_id', 'vendedor_ci'],
  },
  {
    id: 'procedencia',
    titulo: 'Procedencia',
    campos: [
      'area_minera_id',
      'departamento_id',
      'provincia_id',
      'municipio_id',
      'localidad_id',
      'codigo_municipio',
      'codigo_ajam_patente',
    ],
  },
  {
    id: 'tecnico',
    titulo: 'Técnico',
    campos: ['presentacion_mineral_id', 'peso_gramos', 'ley_pagable', 'observaciones'],
  },
]

const REGLAS: Record<keyof ValoresCaptura, Regla> = {
  numero_documento: reglas.numeroDocumento,
  fecha_transaccion: reglas.fechaTransaccion,
  tipo_cambio: reglas.tipoCambio,
  cotizacion_usd_oz_t: reglas.cotizacion,
  cotizacion_id: () => true,
  aplica_caja_salud: () => true,
  comprador_razon_social: reglas.razonSocial,
  comprador_nim: reglas.nim,
  comprador_nit: reglas.nit,
  vendedor_persona_id: reglas.vendedor,
  vendedor_ci: reglas.cedulaIdentidad,
  area_minera_id: reglas.catalogo,
  departamento_id: reglas.catalogo,
  provincia_id: reglas.catalogo,
  municipio_id: reglas.catalogo,
  localidad_id: reglas.catalogo,
  codigo_municipio: reglas.codigoMunicipio,
  codigo_ajam_patente: reglas.codigoAjam,
  presentacion_mineral_id: reglas.catalogo,
  peso_gramos: reglas.pesoGramos,
  ley_pagable: reglas.leyPagable,
  observaciones: reglas.observaciones,
}
export function valoresPorDefecto(): ValoresCaptura {
  return {
    numero_documento: '',
    fecha_transaccion: new Date().toISOString().slice(0, 10),
    tipo_cambio: '',
    cotizacion_usd_oz_t: '',
    cotizacion_id: null,
    aplica_caja_salud: true,
    comprador_razon_social: '',
    comprador_nim: '',
    comprador_nit: '',
    vendedor_persona_id: null,
    vendedor_ci: '',
    area_minera_id: null,
    departamento_id: null,
    provincia_id: null,
    municipio_id: null,
    localidad_id: null,
    codigo_municipio: '',
    codigo_ajam_patente: '',
    presentacion_mineral_id: null,
    peso_gramos: '',
    ley_pagable: '',
    observaciones: '',
  }
}

/**
 * Carga inicial de un formulario de EDICION a partir de lo que devuelve el
 * backend. La ley vuelve a porcentaje (fraccion * 100) para que el input la
 * muestre como la escribio el usuario.
 */
export function valoresDesdeLiquidacion(liquidacion: Liquidacion): ValoresCaptura {
  return {
    numero_documento: liquidacion.numero_documento ?? '',
    fecha_transaccion: liquidacion.fecha_transaccion,
    tipo_cambio: liquidacion.insumos.tipo_cambio,
    cotizacion_usd_oz_t: liquidacion.insumos.cotizacion_usd_oz_t,
    cotizacion_id: liquidacion.insumos.cotizacion_id,
    aplica_caja_salud: liquidacion.insumos.aplica_caja_salud,
    comprador_razon_social: liquidacion.comprador.razon_social,
    comprador_nim: liquidacion.comprador.nim ?? '',
    comprador_nit: liquidacion.comprador.nit ?? '',
    vendedor_persona_id: liquidacion.vendedor.persona_id,
    vendedor_ci: liquidacion.vendedor.ci,
    area_minera_id: liquidacion.procedencia.area_minera_id,
    departamento_id: liquidacion.procedencia.departamento.id,
    provincia_id: liquidacion.procedencia.provincia.id,
    municipio_id: liquidacion.procedencia.municipio.id,
    localidad_id: liquidacion.procedencia.localidad.id,
    codigo_municipio: liquidacion.procedencia.municipio.codigo ?? '',
    codigo_ajam_patente: liquidacion.procedencia.codigo_ajam_patente ?? '',
    presentacion_mineral_id: liquidacion.presentacion.id,
    peso_gramos: liquidacion.insumos.peso_gramos,
    ley_pagable: fraccionAPorcentaje(liquidacion.insumos.ley_pagable),
    observaciones: liquidacion.observaciones ?? '',
  }
}

export interface OpcionesFormulario {
  /** Se ejecuta con el payload listo; la vista decide navegacion y mensaje. */
  guardar: (payload: LiquidacionPayload) => Promise<Liquidacion>
}

export interface FormularioCaptura {
  valores: ValoresCaptura
  errores: Ref<Record<string, string | undefined>>
  tocados: Record<string, boolean>
  intentado: Ref<boolean>
  enviando: Ref<boolean>
  mensajeError: Ref<string>
  pasoActual: Ref<number>
  resultado: Ref<ResultadoCalculo>
  tipoCambioCargando: Ref<boolean>
  tipoCambioDisponible: Ref<boolean>
  cargarTipoCambioDelDia: () => Promise<void>
  marcarTipoCambioDelDia: (fecha: string) => void
  enlazar: (campo: keyof ValoresCaptura) => {
    // any a proposito: cada seccion enlaza campos de tipo distinto contra
    // componentes que aceptan uniones mas anchas que el tipo del campo.
    modelValue: any
    'onUpdate:modelValue': (valor: any) => void
  }
  error: (campo: string) => string | undefined
  visible: (campo: string) => boolean
  tocar: (campo: string) => void
  asignar: (campo: keyof ValoresCaptura, valor: unknown) => void
  validarPaso: () => Promise<boolean>
  irAPaso: (indice: number) => Promise<boolean>
  enviar: () => Promise<void>
  aplicarDefaults: () => void
}

export function useLiquidacionForm(opciones: OpcionesFormulario): FormularioCaptura {
  const catalogos = useCatalogosStore()

  const tocados = reactive<Record<string, boolean>>({})
  const intentado = ref(false)
  const enviando = ref(false)
  const mensajeError = ref('')
  const pasoActual = ref(0)

  const { setErrors, errors, values, setFieldValue } = useForm<ValoresCaptura>({
    initialValues: valoresPorDefecto(),
    // Las secciones se desmontan al pasar de paso en el wizard: sin esto
    // vee-validate borraria los valores de los pasos ya visitados.
    keepValuesOnUnmount: true,
  })

  /**
   * Tipo de cambio del dia (8.2): YA NO SE CAPTURA. El backend lo resuelve por
   * `fecha_transaccion` al guardar, y aqui solo se trae para MOSTRARLO de solo
   * lectura y previsualizar el calculo. `fechaTipoCambio` guarda para que
   * fecha se resolvio el valor actual: al editar, mientras la fecha no cambie
   * se conserva la copia del expediente (que es lo que hara el backend).
   */
  const tipoCambioCargando = ref(false)
  const tipoCambioDisponible = ref(false)
  const fechaTipoCambio = ref<string | null>(null)

  async function cargarTipoCambio(fecha: string): Promise<void> {
    tipoCambioCargando.value = true

    try {
      const tipoCambio = fecha === '' ? null : await obtenerTipoCambio(fecha)

      // La fecha cambio mientras llegaba la respuesta: se descarta la vieja.
      if (values.fecha_transaccion !== fecha) {
        return
      }

      fechaTipoCambio.value = fecha
      tipoCambioDisponible.value = tipoCambio !== null
      setFieldValue('tipo_cambio', tipoCambio?.valor ?? '')
    } catch {
      if (values.fecha_transaccion === fecha) {
        fechaTipoCambio.value = fecha
        tipoCambioDisponible.value = false
        setFieldValue('tipo_cambio', '')
      }
    } finally {
      tipoCambioCargando.value = false
    }
  }

  // Al cambiar la fecha de la transaccion se vuelve a resolver el tipo de
  // cambio del dia, salvo que sea la fecha ya resuelta (edicion sin tocar la
  // fecha: se conserva la copia que trajo el expediente).
  watch(
    () => values.fecha_transaccion,
    (fecha) => {
      if (fecha === fechaTipoCambio.value) {
        return
      }

      void cargarTipoCambio(String(fecha ?? ''))
    },
  )

  /** Carga inicial del alta: resuelve el tipo de cambio de la fecha por defecto. */
  async function cargarTipoCambioDelDia(): Promise<void> {
    await cargarTipoCambio(String(values.fecha_transaccion ?? ''))
  }

  /**
   * Edicion: fija como resuelta la fecha que trae el expediente para que el
   * watch no pise la copia guardada. Si el usuario cambia la fecha, el watch
   * si resuelve el valor de la fecha nueva.
   */
  function marcarTipoCambioDelDia(fecha: string): void {
    fechaTipoCambio.value = fecha
    tipoCambioDisponible.value = true
  }

  /**
   * Valida el formulario COMPLETO contra el espejo del backend. Es la misma
   * funcion para el avance de pasos (que filtra solo su subset) y para el
   * envio final, de modo que un dato valido en un paso sigue validandose al
   * enviar y el server 422 es informacion nueva, no un detalle repetido.
   */
  function validar(v: ValoresCaptura): Record<string, string> {
    const fallas: Record<string, string> = {}

    for (const [campo, regla] of Object.entries(REGLAS)) {
      const resultado = regla(v[campo as keyof ValoresCaptura])

      if (resultado !== true) {
        fallas[campo] = resultado
      }
    }

    return fallas
  }

  const resultado = computed<ResultadoCalculo>(() => {
    const leyFraccion = porcentajeAFraccion(String(values.ley_pagable ?? ''))

    return calcular(
      {
        peso_gramos: String(values.peso_gramos ?? ''),
        ley_pagable: leyFraccion,
        tipo_cambio: String(values.tipo_cambio ?? ''),
        cotizacion_usd_oz_t: String(values.cotizacion_usd_oz_t ?? ''),
        aplica_caja_salud: values.aplica_caja_salud === true,
      },
      catalogos.gramosPorOnzaTroy,
      catalogos.alicuotaAporte,
    )
  })

  function error(campo: string): string | undefined {
    if (!intentado.value && !tocados[campo]) {
      return undefined
    }

    return (errors.value as Record<string, string | undefined>)[campo]
  }

  function visible(campo: string): boolean {
    return error(campo) !== undefined
  }

  function tocar(campo: string): void {
    tocados[campo] = true
  }

  function asignar(campo: keyof ValoresCaptura, valor: unknown): void {
    setFieldValue(campo, valor as never)
    tocados[campo] = true
  }

  function enlazar(campo: keyof ValoresCaptura): {
    modelValue: any
    'onUpdate:modelValue': (valor: any) => void
  } {
    return {
      get modelValue() {
        return values[campo]
      },
      'onUpdate:modelValue': (valor: unknown) => {
        asignar(campo, valor)
      },
    }
  }

  async function validarPaso(): Promise<boolean> {
    const paso = PASOS[pasoActual.value]
    const fallas = validar(values as unknown as ValoresCaptura)
    setErrors(fallas)

    let fallo = false
    for (const campo of paso.campos) {
      tocados[campo] = true
      if (fallas[campo]) {
        fallo = true
      }
    }

    return !fallo
  }

  async function irAPaso(indice: number): Promise<boolean> {
    // Solo se puede saltar hacia adelante validando todos los pasos previos.
    while (pasoActual.value < indice) {
      const paso = pasoActual.value
      const valido = await validarPaso()

      if (!valido) {
        pasoActual.value = paso
        return false
      }

      pasoActual.value = paso + 1
    }

    pasoActual.value = indice
    return true
  }

  function armarPayload(valores: ValoresCaptura): LiquidacionPayload {
    const texto = (valor: string | null | undefined): string => (valor ?? '').trim()
    const id = (campo: keyof ValoresCaptura): number => Number(valores[campo]) || 0

    return {
      numero_documento: texto(valores.numero_documento) || null,
      fecha_transaccion: valores.fecha_transaccion,
      observaciones: texto(valores.observaciones) || null,
      tipo_cambio: texto(valores.tipo_cambio),
      cotizacion_usd_oz_t: texto(valores.cotizacion_usd_oz_t),
      cotizacion_id: valores.cotizacion_id ?? null,
      aplica_caja_salud: valores.aplica_caja_salud,
      peso_gramos: texto(valores.peso_gramos),
      ley_pagable: porcentajeAFraccion(texto(valores.ley_pagable)),
      comprador_razon_social: texto(valores.comprador_razon_social),
      comprador_nim: texto(valores.comprador_nim) || null,
      comprador_nit: texto(valores.comprador_nit) || null,
      vendedor_ci: texto(valores.vendedor_ci),
      vendedor_persona_id: id('vendedor_persona_id'),
      area_minera_id: id('area_minera_id'),
      departamento_id: id('departamento_id'),
      provincia_id: id('provincia_id'),
      municipio_id: id('municipio_id'),
      localidad_id: id('localidad_id'),
      codigo_municipio: texto(valores.codigo_municipio) || null,
      codigo_ajam_patente: texto(valores.codigo_ajam_patente) || null,
      presentacion_mineral_id: id('presentacion_mineral_id'),
    }
  }

  /**
   * Envio final: valida TODO el formulario (incluso pasos desmontados en el
   * wizard) y, si pasa, delega el guardado en `guardar`. Un 422 del backend
   * se mapea campo por campo y se marca como tocado para que el error se
   * vea donde corresponde.
   */
  async function enviar(): Promise<void> {
    const fallas = validar(values as unknown as ValoresCaptura)
    setErrors(fallas)

    if (Object.keys(fallas).length > 0) {
      for (const clave of Object.keys(fallas)) {
        tocados[clave] = true
      }

      return
    }

    enviando.value = true
    mensajeError.value = ''

    try {
      await opciones.guardar(armarPayload(values as unknown as ValoresCaptura))
    } catch (errorDeRed) {
      const porCampo = erroresDeValidacion(errorDeRed)

      if (Object.keys(porCampo).length > 0) {
        setErrors(porCampo)

        for (const clave of Object.keys(porCampo)) {
          tocados[clave] = true
        }
      }

      mensajeError.value = Object.values(porCampo)[0] ?? 'No se pudo guardar la liquidación.'
    } finally {
      enviando.value = false
    }
  }

  /**
   * Defaults al abrir (8.3): cotizacion quincenal mas reciente y los tres
   * datos del comprador, que salen del catalogo `compradores` (celdas
   * C11-C13 de hoja.xlsx). El tipo de cambio no es un default: se resuelve
   * con `cargarTipoCambioDelDia()` contra la fecha de la transaccion.
   */
  function aplicarDefaults(): void {
    if (!values.cotizacion_usd_oz_t && catalogos.cotizacionSugerida) {
      setFieldValue('cotizacion_usd_oz_t', catalogos.cotizacionSugerida.valor_usd_oz_t)
      setFieldValue('cotizacion_id', catalogos.cotizacionSugerida.id)
    }

    const comprador = catalogos.compradorFijo

    if (!values.comprador_razon_social && comprador.razonSocial) {
      setFieldValue('comprador_razon_social', comprador.razonSocial)
      setFieldValue('comprador_nim', comprador.nim)
      setFieldValue('comprador_nit', comprador.nit)
    }
  }

  return {
    valores: values as unknown as ValoresCaptura,
    errores: errors as unknown as Ref<Record<string, string | undefined>>,
    tocados,
    intentado,
    enviando,
    mensajeError,
    pasoActual,
    resultado,
    tipoCambioCargando,
    tipoCambioDisponible,
    cargarTipoCambioDelDia,
    marcarTipoCambioDelDia,
    enlazar,
    error,
    visible,
    tocar,
    asignar,
    validarPaso,
    irAPaso,
    enviar,
    aplicarDefaults,
  }
}

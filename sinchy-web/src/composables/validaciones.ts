/**
 * Reglas de UX espejo de StoreLiquidacionRequest (8.9).
 *
 * Cada funcion aqui corresponde 1:1 a una regla del backend, con el MISMO
 * corte y un mensaje en español. Dos peculiaridades que no son detalles:
 *
 *  - Los identificadores (NIT, NIM, CI, codigos) validan con REGEX de digitos,
 *    nunca con "es numero": C5. Un NIT en notacion cientifica es basura.
 *  - `ley_pagable` se captura en PORCENTAJE (input con %, 8.6) y aqui se
 *  valida como tal (0 < x <= 100); la conversion a fraccion para el backend
 *    (lte:1, trampa T1) la hace el composable al armar el payload.
 *
 * El backend sigue siendo el juez: estos cortes evitan viajes inutiles, no
 * reemplazan su validacion. Un 422 se mapea campo por campo con
 * `erroresDeValidacion()`.
 */

export type Regla = (valor: unknown) => true | string

/** Decimal con hasta `decimales` posiciones y opcionalmente > cero. */
function decimal(valor: unknown, decimales: number, requerido = true): true | string {
  const texto = String(valor ?? '').trim()

  if (texto === '') {
    return requerido ? 'Este campo es obligatorio.' : true
  }

  if (!/^\d+(\.\d{1,20})?$/.test(texto)) {
    return 'Debe ser un número decimal válido.'
  }

  const [, decimalParte = ''] = texto.split('.')

  if (decimalParte.length > decimales) {
    return `Máximo ${decimales} decimales.`
  }

  return true
}

export const reglas = {
  numeroDocumento: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()

    if (texto === '') {
      return true // opcional: si falta, lo asigna el numerador del ejercicio
    }

    if (texto.length > 50) {
      return 'Máximo 50 caracteres.'
    }

    return /^EP-BCB-\d{1,9}$/.test(texto)
      ? true
      : 'Formato inválido. Debe ser EP-BCB- seguido de uno a 9 dígitos (ej.: EP-BCB-000123).'
  },

  fechaTransaccion: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()

    if (texto === '') {
      return 'La fecha de transacción es obligatoria.'
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(texto)) {
      return 'La fecha debe ir en formato YYYY-MM-DD.'
    }

    return Number.isNaN(Date.parse(texto)) ? 'La fecha no es válida.' : true
  },

  /**
   * Espejo de la resolucion del backend: el tipo de cambio NO lo escribe el
   * usuario, lo carga el sistema para la fecha de la transaccion. Vacio
   * significa que ese dia no tiene valor cargado, y el alta se bloquea.
   */
  tipoCambio: (valor: unknown): true | string => {
    if (String(valor ?? '').trim() === '') {
      return 'No hay tipo de cambio cargado para la fecha de la transacción.'
    }

    const corte = decimal(valor, 2)
    if (corte !== true) {
      return corte
    }

    return Number(valor) > 0 ? true : 'El tipo de cambio debe ser mayor a 0.'
  },

  cotizacion: (valor: unknown): true | string => {
    const corte = decimal(valor, 2)
    if (corte !== true) {
      return corte
    }

    return Number(valor) > 0 ? true : 'La cotización debe ser mayor a 0.'
  },

  pesoGramos: (valor: unknown): true | string => {
    const corte = decimal(valor, 2)
    if (corte !== true) {
      return corte
    }

    if (Number(valor) <= 0) {
      return 'El peso debe ser mayor a 0.'
    }

    return Number(valor) <= 99999999.99 ? true : 'El peso supera el máximo permitido.'
  },

  /**
   * En PORCENTAJE (el input trae %): 0 < x <= 100. El backend recibe la
   * fraccion (0.9302) y ahi aplica su `lte:1` (T1): 93,02 % pasa, 9302 no.
   */
  leyPagable: (valor: unknown): true | string => {
    const corte = decimal(valor, 2)
    if (corte !== true) {
      return corte
    }

    const porcentaje = Number(valor)

    if (porcentaje <= 0) {
      return 'La ley pagable debe ser mayor a 0.'
    }

    return porcentaje <= 100
      ? true
      : 'La ley va en porcentaje: el máximo es 100 % (equivale a la fracción 1).'
  },

  razonSocial: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()

    if (texto === '') {
      return 'La razón social del comprador es obligatoria.'
    }

    return texto.length <= 200 ? true : 'Máximo 200 caracteres.'
  },

  nim: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()

    if (texto === '') {
      return true
    }

    return /^[0-9\-]{5,30}$/.test(texto)
      ? true
      : 'El NIM solo admite dígitos y guiones (5 a 30 caracteres).'
  },

  nit: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()

    if (texto === '') {
      return true
    }

    return /^\d{1,20}$/.test(texto) ? true : 'El NIT debe tener solo dígitos (hasta 20).'
  },

  vendedor: (valor: unknown): true | string =>
    Number.isInteger(Number(valor)) && Number(valor) > 0
      ? true
      : 'Debe seleccionar el vendedor en la lista.',

  cedulaIdentidad: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()

    if (texto === '') {
      return 'La cédula de identidad es obligatoria.'
    }

    return /^\d{1,10}$/.test(texto) ? true : 'El CI debe tener solo dígitos (hasta 10).'
  },

  catalogo: (valor: unknown): true | string =>
    Number.isInteger(Number(valor)) && Number(valor) > 0
      ? true
      : 'Debe seleccionar una opción.',

  codigoMunicipio: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()
    return texto === '' || /^\d{1,10}$/.test(texto)
      ? true
      : 'El código debe tener solo dígitos (hasta 10).'
  },

  codigoAjam: (valor: unknown): true | string => {
    const texto = String(valor ?? '').trim()
    return texto === '' || /^\d{1,20}$/.test(texto)
      ? true
      : 'La patente AJAM debe tener solo dígitos (hasta 20).'
  },

  observaciones: (valor: unknown): true | string =>
    String(valor ?? '').length <= 5000 ? true : 'Máximo 5000 caracteres.',

  presente: (valor: unknown): true | string =>
    valor === true || valor === false ? true : 'Complete este campo.',
} satisfies Record<string, Regla>

/**
 * Convierte el porcentaje capturado (93.02) en la fraccion que exige el
 * backend (0.9302), en CADENA y sin pasar por float: 0.9302 no es
 * representable en binario y el corte T1 vive en el decimal exacto.
 */
export function porcentajeAFraccion(porcentaje: string): string {
  const texto = String(porcentaje).trim()

  if (!/^\d+(\.\d+)?$/.test(texto)) {
    return ''
  }

  const [entero = '0', decimalParte = ''] = texto.split('.')
  const digitos = (entero + decimalParte).replace(/^0+(?=\d)/, '')
  // La fraccion mueve el punto DOS lugares a la izquierda desde el porcentaje.
  const decimales = decimalParte.length + 2

  if (/^0+$/.test(digitos)) {
    return '0'
  }

  if (digitos.length <= decimales) {
    // Menos de 100%: siempre 0.<digitos> con el ancho justo. 93.02 -> 0.9302,
    // 90 -> 0.90 (=> 0.9), 0.5 -> 0.005.
    return `0.${digitos.padStart(decimales, '0')}`.replace(/(\.\d*?)0+$/, '$1')
  }

  // Cola del 100%: 100 -> 1.00 (=> 1). Arriba del 100 la regla lte:1 la corta.
  const corte = digitos.length - decimales

  return `${digitos.slice(0, corte)}.${digitos.slice(corte)}`.replace(/\.0+$/, '')
}

/**
 * La inversa de `porcentajeAFraccion`: la ley llega del backend como FRACCION
 * en cadena ("0.9302") y el input de captura la muestra en porcentaje
 * ("93.02"). Desplaza el punto dos lugares a la derecha en dominio de
 * cadenas, sin pasar por float (0.9302 * 100 en binario da 93.019999...).
 */
export function fraccionAPorcentaje(fraccion: string | number): string {
  const texto = String(fraccion ?? '').trim()

  if (!/^\d+(\.\d+)?$/.test(texto)) {
    return texto
  }

  const [entero = '0', decimalParte = ''] = texto.split('.')
  const digitos = entero + decimalParte
  // Multiplicar por 100 = correr el punto dos lugares a la derecha, medido
  // desde los decimales de la fraccion: 0.9302 (4 dec) -> 93.02 ; 1 (0 dec)
  // -> 100 ; 0.9 (1 dec) -> 90. Sin pasar por float ni por regex con
  // captura.
  const desdeDerecha = decimalParte.length - 2
  let resultado: string

  if (desdeDerecha <= 0) {
    resultado = `${digitos}${'0'.repeat(-desdeDerecha)}`
  } else {
    const rellenado = digitos.padStart(desdeDerecha + 1, '0')
    const corte = rellenado.length - desdeDerecha
    resultado = `${rellenado.slice(0, corte)}.${rellenado.slice(corte)}`
    resultado = resultado.replace(/\.0+$/, '').replace(/\.$/, '')
  }

  resultado = resultado.replace(/^0+(?=\d)/, '')

  return resultado === '' ? '0' : resultado
}

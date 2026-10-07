/**
 * Previsualizacion del calculo en vivo (8.7).
 *
 * ESPEJO EXACTO de app/Services/CalculoLiquidacionService.php + el halfUp de
 * app/Support/Decimal.php, reimplementado con BigInt a escala 20 para no
 * depender de float en ningun paso:
 *
 *     peso_fino  = peso * ley
 *     usd_gramo  = cotizacion / gramos_por_onza_troy
 *     bruto      = usd_gramo * tipo_cambio * peso_fino      (sin redondeo)
 *     aporte     = aplica_caja_salud ? bruto_EXACTO * ALICUOTA_APORTE : 0
 *     neto       = bruto - (aporte + deducciones)
 *     UN solo redondeo half-up a 2 decimales y al FINAL, sobre montos en Bs.
 *
 * Tres reglas que aqui se repiten porque son bugs que casi entran (C1, C2, D4):
 *
 *  1. Todo el calculo intermedio vive con BigInt escalado por 10^20, la misma
 *     escala que usa bcmath en el backend. Las operaciones son truncamiento
 *     hacia cero, identico a bcmul/bcdiv con valores positivos.
 *  2. El aporte se calcula sobre el bruto SIN redondear (D4), nunca sobre el
 *     bruto ya redondeado.
 *  3. Aqui SOLO se previsualiza. El dato que se guarda lo devuelve el backend
 *     (regla de separation of concerns): si este espejo y el backend alguna vez
 *     discrepan, manda el backend y el bug se corrige aqui.
 */

/** Los montos en Bs salen siempre con 2 decimales. */
const DECIMALES_MONTO = 2
const ESCALA = 20n
const DIEZ = 10n
const POTENCIA = DIEZ ** ESCALA
const UNIDAD = POTENCIA / DIEZ ** BigInt(DECIMALES_MONTO) // 10^18

export interface InsumosCalculo {
  peso_gramos: string
  ley_pagable: string
  tipo_cambio: string
  cotizacion_usd_oz_t: string
  aplica_caja_salud: boolean
  /** Montos en Bs, uno por renglon de deduccion variable. */
  deducciones: string[]
}

export interface ResultadoCalculo {
  gramos_por_onza_troy: string
  peso_fino_gramos: string
  valor_usd_gramo: string
  valor_bruto_bs: string
  aporte_caja_salud_bs: string
  total_deducciones_bs: string
  valor_neto_bs: string
  /** false cuando algun insumo todavia no es usable; no pintar montos. */
  valido: boolean
}

/**
 * Convierte una cadena decimal en entero escalado por 10^20, o null si no es
 * un numero utilizable. La notacion cientifica se rechaza: es la misma trampa
 * que el backend corta en `Decimal::texto()` ('1e3' es error de captura).
 */
function escalar(valor: string | null | undefined): bigint | null {
  if (valor === null || valor === undefined) {
    return null
  }

  const texto = String(valor).trim()

  if (texto === '' || !/^-?\d*(\.\d*)?$/.test(texto) || texto === '.' || texto === '-.') {
    return null
  }

  const [entero, decimal = ''] = texto.replace('-', '').split('.')
  const decimales = decimal.slice(0, Number(ESCALA)) // trunca a escala 20
  const digitos = (entero || '0') + decimales
  const factor = ESCALA - BigInt(decimales.length)
  const magnitud = BigInt(digitos) * DIEZ ** factor

  return texto.startsWith('-') ? -magnitud : magnitud
}

/** bcmul: producto a escala 20 con truncamiento hacia cero. */
function mul(a: bigint, b: bigint): bigint {
  return (a * b) / POTENCIA
}

/** bcdiv: cociente a escala 20 con truncamiento hacia cero. */
function div(a: bigint, b: bigint): bigint {
  if (b === 0n) {
    return 0n
  }

  return (a * POTENCIA) / b
}

/**
 * Decimal::halfUp a 2 decimales: desplazar, sumar media unidad EN EL DOMINIO
 * YA DESPLAZADO (10^18 y no 0,5), truncar y recien ahi formatear. Mitad hacia
 * arriba, como el `round()` de PHP para positivos.
 */
function halfUp(valor: bigint): string {
  const negativo = valor < 0n
  const absoluto = negativo ? -valor : valor

  let entero = absoluto / UNIDAD
  const resto = absoluto % UNIDAD

  if (resto * 2n >= UNIDAD) {
    entero += 1n
  }

  // Punto decimal, igual que el backend: la coma la pone formato.ts al pintar.
  const texto = entero.toString().padStart(DECIMALES_MONTO + 1, '0')
  const parte = texto.slice(0, -DECIMALES_MONTO)
  const centavos = texto.slice(-DECIMALES_MONTO)

  return `${negativo ? '-' : ''}${parte}.${centavos}`
}

/** Decimal::normalizar: quita los ceros sobrantes a la derecha. */
function normalizar(valor: bigint): string {
  const negativo = valor < 0n
  const absoluto = negativo ? -valor : valor

  const texto = absoluto.toString().padStart(Number(ESCALA) + 1, '0')
  const entero = texto.slice(0, -Number(ESCALA))
  const decimal = texto.slice(-Number(ESCALA)).replace(/0+$/, '')
  const limpio = decimal === '' ? entero : `${entero}.${decimal}`

  if (limpio === '' || limpio === '0') {
    return '0'
  }

  return negativo ? `-${limpio}` : limpio
}

export const resultadoVacio: ResultadoCalculo = {
  gramos_por_onza_troy: '',
  peso_fino_gramos: '',
  valor_usd_gramo: '',
  valor_bruto_bs: '',
  aporte_caja_salud_bs: '',
  total_deducciones_bs: '',
  valor_neto_bs: '',
  valido: false,
}

/**
 * Calcula la previsualizacion con los insumos del formulario.
 *
 * @param gramosPorOnzaTroy parametro GRAMOS_POR_ONZA_TROY (31.1035)
 * @param alicuotaAporte    parametro ALICUOTA_APORTE (0.018)
 */
export function calcular(
  insumos: InsumosCalculo,
  gramosPorOnzaTroy: string,
  alicuotaAporte: string,
): ResultadoCalculo {
  const peso = escalar(insumos.peso_gramos)
  const ley = escalar(insumos.ley_pagable)
  const tipoCambio = escalar(insumos.tipo_cambio)
  const cotizacion = escalar(insumos.cotizacion_usd_oz_t)
  const porOnza = escalar(gramosPorOnzaTroy)
  const alicuota = escalar(alicuotaAporte)

  const montos = insumos.deducciones.map((monto) => escalar(monto))

  const completo =
    peso !== null &&
    ley !== null &&
    tipoCambio !== null &&
    cotizacion !== null &&
    porOnza !== null &&
    porOnza > 0n &&
    alicuota !== null &&
    montos.every((monto) => monto !== null && monto >= 0n)

  // Mismos cortes fisicos que CalculoLiquidacionService::validar().
  const dentroDeRango =
    completo &&
    peso > 0n &&
    ley > 0n &&
    ley <= POTENCIA && // fraccion lte:1 (T1)
    tipoCambio > 0n &&
    cotizacion > 0n

  if (!dentroDeRango) {
    return { ...resultadoVacio }
  }

  const pesoFino = mul(peso!, ley!)
  const usdGramo = div(cotizacion!, porOnza!)
  const brutoExacto = mul(mul(usdGramo, tipoCambio!), pesoFino!)

  const aporteExacto = insumos.aplica_caja_salud ? mul(brutoExacto, alicuota!) : 0n

  let totalDeducciones = aporteExacto
  for (const monto of montos as bigint[]) {
    totalDeducciones += monto
  }

  const netoExacto = brutoExacto - totalDeducciones

  return {
    gramos_por_onza_troy: gramosPorOnzaTroy,
    peso_fino_gramos: normalizar(pesoFino),
    valor_usd_gramo: normalizar(usdGramo),
    valor_bruto_bs: halfUp(brutoExacto),
    aporte_caja_salud_bs: halfUp(aporteExacto),
    total_deducciones_bs: halfUp(totalDeducciones),
    valor_neto_bs: halfUp(netoExacto),
    valido: true,
  }
}

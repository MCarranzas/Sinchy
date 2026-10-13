/**
 * Formatos de presentacion (8.x): todo lo que el usuario VE pasa por aqui,
 * para que un mismo numero no se pinte de tres formas segun la vista.
 *
 * Regla del proyecto (T3): los montos llegan como CADENA desde el backend y
 * como cadena se manipulan; convertir a Number para "formatear" reintroduce
 * la perdida de precision binaria que la cadena existe para evitar.
 */

const formatoBs = new Intl.NumberFormat('es-BO', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

/**
 * Monto en bolivianos con su símbolo: `Bs 1.072.018,84`.
 * Un valor no numerico (todavia vacio) se pinta como guion, no como NaN.
 */
export function bs(valor: string | number | null | undefined): string {
  const numero = Number(valor)

  if (valor === null || valor === undefined || valor === '' || !Number.isFinite(numero)) {
    return 'Bs 0,00'
  }

  return `Bs ${formatoBs.format(numero)}`
}

/** Numero con separadores es-BO, sin moneda. */
export function numero(valor: string | number | null | undefined, decimales = 2): string {
  const n = Number(valor)

  if (valor === null || valor === undefined || valor === '' || !Number.isFinite(n)) {
    return '—'
  }

  return new Intl.NumberFormat('es-BO', {
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
  }).format(n)
}

/**
 * Ley pagable: en la base es FRACCION (0.9302) y en pantalla se muestra como
 * porcentaje (93,02 %). Es solo presentacion: el valor que viaja al backend
 * sigue siendo la fraccion (trampa T1).
 *
 * Se pinta con 2 decimales, igual que el resto de los valores numericos.
 */
export function porcentaje(fraccion: string | number | null | undefined): string {
  const n = Number(fraccion)

  if (fraccion === null || fraccion === undefined || fraccion === '' || !Number.isFinite(n)) {
    return '—'
  }

  return `${new Intl.NumberFormat('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n * 100)} %`
}

/** Fecha ISO (YYYY-MM-DD) a `dd/mm/aaaa`, el formato que se lee en Bolivia. */
export function fecha(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }

  const [anio, mes, dia] = iso.slice(0, 10).split('-')

  return dia && mes && anio ? `${dia}/${mes}/${anio}` : iso
}

/** Fecha-hora ISO a `dd/mm/aaaa hh:mm`. */
export function fechaHora(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }

  const momento = new Date(iso)
  if (Number.isNaN(momento.getTime())) {
    return '—'
  }

  const reloj = new Intl.DateTimeFormat('es-BO', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(momento)

  return `${fecha(iso.slice(0, 10))} ${reloj}`
}

/** Peso en gramos con separadores: `1.944,53 g`. */
export function gramos(valor: string | number | null | undefined): string {
  return `${numero(valor)} g`
}

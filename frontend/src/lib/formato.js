/*
 * Formato de fechas, dinero y textos. Todo el sitio muestra los datos a través
 * de estas funciones, así que cambiar cómo se ve una fecha se hace en un sitio.
 */

const FECHA_LARGA = new Intl.DateTimeFormat('es-CO', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
})

const FECHA_CORTA = new Intl.DateTimeFormat('es-CO', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
})

const HORA = new Intl.DateTimeFormat('es-CO', {
  hour: 'numeric',
  minute: '2-digit',
})

function aFecha(valor) {
  if (!valor) return null

  const fecha = new Date(valor)

  return Number.isNaN(fecha.getTime()) ? null : fecha
}

export function fechaLarga(valor) {
  const fecha = aFecha(valor)
  return fecha ? FECHA_LARGA.format(fecha) : '—'
}

export function fechaCorta(valor) {
  const fecha = aFecha(valor)
  return fecha ? FECHA_CORTA.format(fecha) : '—'
}

export function hora(valor) {
  const fecha = aFecha(valor)
  return fecha ? HORA.format(fecha) : '—'
}

export function fechaYHora(valor) {
  const fecha = aFecha(valor)
  return fecha ? `${FECHA_CORTA.format(fecha)} · ${HORA.format(fecha)}` : 'Por definir'
}

/**
 * Valor para un <input type="datetime-local">, que espera la hora local sin
 * zona horaria.
 */
export function paraInputDeFecha(valor) {
  const fecha = aFecha(valor)

  if (!fecha) return ''

  const desfase = fecha.getTimezoneOffset() * 60000

  return new Date(fecha.getTime() - desfase).toISOString().slice(0, 16)
}

export function iniciales(nombre = '') {
  return (
    nombre
      .split(' ')
      .filter(Boolean)
      .map((parte) => parte[0])
      .slice(0, 2)
      .join('')
      .toUpperCase() || 'JZ'
  )
}

/**
 * Pesos a centavos y al revés, para los formularios de dinero: el backend
 * siempre habla en centavos enteros.
 */
export function aCentavos(pesos) {
  return Math.round(Number(pesos || 0) * 100)
}

export function aPesos(centavos) {
  return (Number(centavos || 0) / 100).toString()
}

export function pluralizar(cantidad, singular, plural) {
  return `${cantidad} ${cantidad === 1 ? singular : plural}`
}

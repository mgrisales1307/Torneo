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

/**
 * Fecha corta para datos sueltos: «3 oct · 7:00 p. m.». El año solo aparece
 * si no es el actual.
 */
const DIA_Y_MES = new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short' })

export function fechaCompacta(valor) {
  const fecha = aFecha(valor)

  if (!fecha) return 'Por definir'

  const anio = fecha.getFullYear() !== new Date().getFullYear() ? ` ${fecha.getFullYear()}` : ''

  return `${DIA_Y_MES.format(fecha).replace('.', '')}${anio} · ${HORA.format(fecha)}`
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
  const partes = nombre.split(/[\s_-]+/).filter(Boolean)

  const letras = partes.length > 1 ? partes.map((parte) => parte[0]).join('') : nombre.slice(0, 2)

  return letras.slice(0, 2).toUpperCase() || 'NZ'
}

/**
 * «Cuánto falta» en palabras: en 2 h, en 3 días, hace 10 min.
 */
const RELATIVO = new Intl.RelativeTimeFormat('es-CO', { numeric: 'auto' })

export function relativo(valor) {
  const fecha = aFecha(valor)

  if (!fecha) return ''

  const minutos = Math.round((fecha.getTime() - Date.now()) / 60000)

  if (Math.abs(minutos) < 60) return RELATIVO.format(minutos, 'minute')
  if (Math.abs(minutos) < 60 * 24) return RELATIVO.format(Math.round(minutos / 60), 'hour')

  return RELATIVO.format(Math.round(minutos / (60 * 24)), 'day')
}

/**
 * Nombre de una ronda del cuadro: la última es la final, la anterior la
 * semifinal… El número de rondas lo da el cuadro, no el partido.
 */
export function nombreDeRonda(ronda, totalDeRondas, llave) {
  if (llave === 'gran_final') return 'Gran final'
  if (llave === 'inferior') return `Inferior · ronda ${ronda}`

  const restantes = totalDeRondas - ronda

  if (restantes === 0) return 'Final'
  if (restantes === 1) return 'Semifinal'
  if (restantes === 2) return 'Cuartos'

  return `Ronda ${ronda}`
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

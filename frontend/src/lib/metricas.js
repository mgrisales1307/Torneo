import { registrarMetrica } from './api'

const CLAVE = 'nexzone-metricas'
const CLAVE_SESION = 'nexzone-sesion-metricas'
let rendimientoIniciado = false

export function leerPreferenciaMetricas() {
  try { return localStorage.getItem(CLAVE) } catch { return 'rechazadas' }
}

export function privacidadDelNavegador() {
  return navigator.doNotTrack === '1' || navigator.globalPrivacyControl === true
}

export function guardarPreferenciaMetricas(preferencia) {
  try {
    localStorage.setItem(CLAVE, preferencia)
    if (preferencia !== 'aceptadas') sessionStorage.removeItem(CLAVE_SESION)
  } catch { /* Sin almacenamiento, la captura permanece deshabilitada. */ }
}

/** Clasifica rutas sin transmitir slugs, apodos, referencias, consultas ni fragmentos. */
export function clasificarPagina(ruta) {
  if (ruta.startsWith('/organizacion')) return null
  if (ruta === '/') return 'inicio'
  if (ruta.startsWith('/panel')) return 'panel'
  if (ruta === '/torneos') return 'torneos'
  const prefijos = { juegos: 'juego', torneos: 'torneo', partidos: 'partido', equipos: 'equipo', jugadores: 'jugador' }
  const segmento = ruta.split('/')[1]
  return prefijos[segmento] ?? (['ingreso', 'registro'].includes(segmento) ? segmento : 'otra')
}

export function enviarMetrica(pagina, tipo = 'pagina', valor, id = crypto.randomUUID()) {
  if (!pagina || leerPreferenciaMetricas() !== 'aceptadas' || privacidadDelNavegador()) return
  try {
    const ahora = Date.now()
    let sesion = JSON.parse(sessionStorage.getItem(CLAVE_SESION) ?? 'null')
    if (!sesion || ahora - sesion.ultimo > 30 * 60 * 1000) sesion = { id: crypto.randomUUID() }
    sesion.ultimo = ahora
    sessionStorage.setItem(CLAVE_SESION, JSON.stringify(sesion))
    const dispositivo = window.innerWidth < 768 ? 'movil' : window.innerWidth < 1024 ? 'tableta' : 'escritorio'
    registrarMetrica(
      { id, sesion: sesion.id, consentimiento: true, pagina, tipo, dispositivo, ...(valor !== undefined ? { valor } : {}) },
      () => leerPreferenciaMetricas() === 'aceptadas' && !privacidadDelNavegador(),
    ).catch(() => {})
  } catch { /* La analítica nunca impide usar la aplicación. */ }
}

/** Core Web Vitals pertenecen a la carga del documento, no a cada ruta de la SPA. */
export async function iniciarRendimiento(paginaInicial) {
  if (rendimientoIniciado || !paginaInicial) return
  rendimientoIniciado = true
  try {
    const { onCLS, onINP, onLCP } = await import('web-vitals')
    const identificadores = new Map()
    const informar = (metrica) => {
      if (!identificadores.has(metrica.id)) identificadores.set(metrica.id, crypto.randomUUID())
      enviarMetrica(paginaInicial, metrica.name.toLowerCase(), metrica.value, identificadores.get(metrica.id))
    }
    onCLS(informar)
    onINP(informar)
    onLCP(informar)
  } catch { /* Navegadores sin soporte no producen muestras artificiales. */ }
}

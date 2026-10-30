import axios from 'axios'

/*
 * Cliente de la API.
 *
 * Todas las rutas son relativas: el navegador solo habla con el origen desde el
 * que se sirvió la página, y Vite reenvía /api, /sanctum y /storage al backend
 * (ver vite.config.js). Al no haber dos dominios no hay CORS que configurar, ni
 * cookies que se pierdan, ni una URL de API que se quede desactualizada.
 *
 * La sesión viaja en una cookie HttpOnly firmada por Laravel. Aquí no se guarda
 * ningún token: lo que el JavaScript no puede leer, un XSS tampoco se lo lleva.
 */

const BASE = '/api/v1'

const api = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/**
 * Laravel exige el token CSRF en toda escritura. Se pide una sola vez, antes de
 * la primera petición que modifique algo, y queda en una cookie que axios
 * reenvía solo.
 */
let csrfEnCurso = null

function hayCookieCsrf() {
  return document.cookie.split('; ').some((cookie) => cookie.startsWith('XSRF-TOKEN='))
}

async function asegurarCsrf() {
  if (hayCookieCsrf()) return

  // Si dos peticiones salen a la vez, comparten la misma solicitud de cookie.
  csrfEnCurso ??= axios
    .get('/sanctum/csrf-cookie', { withCredentials: true })
    .finally(() => {
      csrfEnCurso = null
    })

  await csrfEnCurso
}

api.interceptors.request.use(async (config) => {
  const metodo = (config.method ?? 'get').toLowerCase()

  if (!['get', 'head', 'options'].includes(metodo)) {
    await asegurarCsrf()
  }

  return config
})

/* ------------------------------------------------------------------ Errores */

export function esNoAutenticado(error) {
  return axios.isAxiosError(error) && error.response?.status === 401
}

export function esProhibido(error) {
  return axios.isAxiosError(error) && error.response?.status === 403
}

export function esNoEncontrado(error) {
  return axios.isAxiosError(error) && error.response?.status === 404
}

/**
 * Errores de validación por campo, tal como los devuelve Laravel.
 */
export function erroresPorCampo(error) {
  if (!axios.isAxiosError(error)) return {}

  const errores = error.response?.data?.errors ?? {}

  return Object.fromEntries(
    Object.entries(errores).map(([campo, mensajes]) => [campo, [].concat(mensajes)[0]]),
  )
}

/**
 * Un único mensaje para mostrarle a la persona, en español y sin tecnicismos.
 */
export function mensajeDeError(error, porDefecto = 'No pudimos completar la acción. Inténtalo otra vez.') {
  if (!axios.isAxiosError(error)) return porDefecto

  if (!error.response) {
    return 'No pudimos conectar con el servidor. Revisa que la API esté en ejecución.'
  }

  if (error.response.status === 429) {
    return 'Demasiados intentos seguidos. Espera un minuto y vuelve a probar.'
  }

  if (error.response.status === 419) {
    return 'Tu sesión caducó. Recarga la página e inténtalo de nuevo.'
  }

  if (error.response.status === 403) {
    return error.response.data?.message ?? 'No tienes permiso para hacer esto.'
  }

  const primerErrorDeCampo = Object.values(error.response.data?.errors ?? {}).flat().find(Boolean)

  return primerErrorDeCampo ?? error.response.data?.message ?? porDefecto
}

const datos = (promesa) => promesa.then((respuesta) => respuesta.data)

/* ------------------------------------------------------------ Autenticación */

export async function obtenerPerfil() {
  const { data } = await api.get(`${BASE}/perfil`)
  return data.usuario
}

export async function ingresar(credenciales) {
  const { data } = await api.post(`${BASE}/ingreso`, credenciales)
  return data.usuario
}

export async function registrarse(formulario) {
  const { data } = await api.post(`${BASE}/registro`, formulario)
  return data.usuario
}

export async function salir() {
  await api.post(`${BASE}/salida`)
}

/* ------------------------------------------------------------------ Público */

export const obtenerInicio = () => datos(api.get(`${BASE}/inicio`))
export const listarJuegos = () => datos(api.get(`${BASE}/juegos`)).then((r) => r.data)
export const obtenerJuego = (juego) => datos(api.get(`${BASE}/juegos/${juego}`))
export const listarTorneos = (parametros = {}) => datos(api.get(`${BASE}/torneos`, { params: parametros }))
export const obtenerTorneo = (slug) => datos(api.get(`${BASE}/torneos/${slug}`)).then((r) => r.data)
export const obtenerEquiposDelTorneo = (slug) => datos(api.get(`${BASE}/torneos/${slug}/equipos`)).then((r) => r.data)
export const obtenerCuadro = (slug) => datos(api.get(`${BASE}/torneos/${slug}/cuadro`))
export const obtenerPartido = (id) => datos(api.get(`${BASE}/partidos/${id}`))
export const obtenerEquipo = (slug) => datos(api.get(`${BASE}/equipos/${slug}`))
export const obtenerJugador = (apodo) => datos(api.get(`${BASE}/jugadores/${encodeURIComponent(apodo)}`))

/* ---------------------------------------------------------- Panel del jugador */

export const listarMisCuentas = () => datos(api.get(`${BASE}/panel/cuentas`)).then((r) => r.data)
export const guardarCuenta = (juego, identificador) =>
  datos(api.put(`${BASE}/panel/cuentas/${juego}`, { identificador }))
export const quitarCuenta = (juego) => datos(api.delete(`${BASE}/panel/cuentas/${juego}`))

export const listarMisEquipos = () => datos(api.get(`${BASE}/panel/equipos`)).then((r) => r.data)
export const obtenerMiEquipo = (slug) => datos(api.get(`${BASE}/panel/equipos/${slug}`)).then((r) => r.data)
export const crearEquipo = (formulario) => datos(api.post(`${BASE}/panel/equipos`, formulario))
export const actualizarEquipo = (slug, formulario) => datos(api.patch(`${BASE}/panel/equipos/${slug}`, formulario))
export const eliminarEquipo = (slug) => datos(api.delete(`${BASE}/panel/equipos/${slug}`))
export const nuevoCodigo = (slug) => datos(api.post(`${BASE}/panel/equipos/${slug}/codigo`))
export const unirseAEquipo = (codigo) => datos(api.post(`${BASE}/panel/unirse`, { codigo }))

export function subirLogo(slug, archivo) {
  const cuerpo = new FormData()
  cuerpo.append('logo', archivo)

  return datos(api.post(`${BASE}/panel/equipos/${slug}/logo`, cuerpo))
}

export const actualizarMiembro = (slug, usuarioId, cambios) =>
  datos(api.patch(`${BASE}/panel/equipos/${slug}/miembros/${usuarioId}`, cambios))
export const sacarMiembro = (slug, usuarioId) =>
  datos(api.delete(`${BASE}/panel/equipos/${slug}/miembros/${usuarioId}`))

export const listarMisInscripciones = () => datos(api.get(`${BASE}/panel/inscripciones`)).then((r) => r.data)
export const inscribirEquipo = (slugTorneo, equipoId) =>
  datos(api.post(`${BASE}/panel/torneos/${slugTorneo}/inscripciones`, { equipo_id: equipoId }))
export const inscribirmeIndividual = (slugTorneo) => datos(api.post(`${BASE}/panel/torneos/${slugTorneo}/inscripciones`, {}))
export const obtenerCartera = (page = 1) => datos(api.get(`${BASE}/panel/cartera`, { params: { page } }))
export const retirarDeCartera = (monto_centavos, referencia) => datos(api.post(`${BASE}/panel/cartera/retiros`, { monto_centavos, referencia }))
export const pagarConCartera = (inscripcionId) => datos(api.post(`${BASE}/panel/inscripciones/${inscripcionId}/pagar-cartera`))
export const retirarInscripcion = (id) => datos(api.delete(`${BASE}/panel/inscripciones/${id}`))
export const hacerCheckIn = (id) => datos(api.post(`${BASE}/panel/inscripciones/${id}/check-in`))

export const crearOrdenDePago = (inscripcionId) => datos(api.post(`${BASE}/panel/inscripciones/${inscripcionId}/orden`))
export const obtenerOrden = (referencia) => datos(api.get(`${BASE}/panel/ordenes/${referencia}`)).then((r) => r.data)
export const pagarConLaPasarelaSimulada = (referencia, resultado) =>
  datos(api.post(`${BASE}/panel/ordenes/${referencia}/pagar-simulado`, { resultado }))

export const listarMisPartidos = () => datos(api.get(`${BASE}/panel/partidos`)).then((r) => r.data)

/**
 * El reporte va como formulario multiparte porque lleva la captura.
 */
export function reportarResultado(partidoId, { victorias_a, victorias_b, detalle, comentario, captura }) {
  const cuerpo = new FormData()
  cuerpo.append('victorias_a', victorias_a)
  cuerpo.append('victorias_b', victorias_b)

  if (comentario) cuerpo.append('comentario', comentario)
  if (captura) cuerpo.append('captura', captura)

  ;(detalle ?? []).forEach((mapa, indice) => {
    cuerpo.append(`detalle[${indice}][ganador]`, mapa.ganador)
    if (mapa.mapa) cuerpo.append(`detalle[${indice}][mapa]`, mapa.mapa)
  })

  return datos(api.post(`${BASE}/panel/partidos/${partidoId}/reporte`, cuerpo))
}

/* --------------------------------------------------- Panel de organización */

export const obtenerResumenAdmin = () => datos(api.get(`${BASE}/admin/resumen`))
export const obtenerMetricasAdmin = () => datos(api.get(`${BASE}/admin/metricas`))
export const obtenerConfiguracionMetricas = () => datos(api.get(`${BASE}/metricas/configuracion`))

/** keepalive permite terminar la muestra al ocultar la pestaña, conservando CSRF. */
export async function registrarMetrica(evento, sigueAutorizada) {
  await asegurarCsrf()
  if (!sigueAutorizada()) return
  const cookie = document.cookie.split('; ').find((valor) => valor.startsWith('XSRF-TOKEN='))
  if (!cookie) return
  await fetch(`${BASE}/metricas/eventos`, {
    method: 'POST', credentials: 'same-origin', keepalive: true,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) },
    body: JSON.stringify(evento),
  })
}
export const listarTorneosAdmin = (parametros = {}) => datos(api.get(`${BASE}/admin/torneos`, { params: parametros }))
export const obtenerTorneoAdmin = (slug) => datos(api.get(`${BASE}/admin/torneos/${slug}`))
export const crearTorneo = (formulario) => datos(api.post(`${BASE}/admin/torneos`, formulario))
export const actualizarTorneo = (slug, formulario) => datos(api.patch(`${BASE}/admin/torneos/${slug}`, formulario))
export const cambiarEstadoDelTorneo = (slug, estado) => datos(api.post(`${BASE}/admin/torneos/${slug}/estado`, { estado }))
export const iniciarTorneo = (slug) => datos(api.post(`${BASE}/admin/torneos/${slug}/iniciar`))
export const eliminarTorneo = (slug) => datos(api.delete(`${BASE}/admin/torneos/${slug}`))

export const listarInscripcionesAdmin = (parametros = {}) =>
  datos(api.get(`${BASE}/admin/inscripciones`, { params: parametros }))
export const aprobarInscripcion = (id, notas) => datos(api.post(`${BASE}/admin/inscripciones/${id}/aprobar`, { notas }))
export const rechazarInscripcion = (id, notas) => datos(api.post(`${BASE}/admin/inscripciones/${id}/rechazar`, { notas }))

export const listarPartidosAdmin = (parametros = {}) => datos(api.get(`${BASE}/admin/partidos`, { params: parametros }))
export const programarPartido = (id, programado_para) =>
  datos(api.patch(`${BASE}/admin/partidos/${id}`, { programado_para }))
export const resolverPartido = (id, resultado) => datos(api.post(`${BASE}/admin/partidos/${id}/resultado`, resultado))
export const cargarBattleRoyale = (id, resultados) =>
  datos(api.post(`${BASE}/admin/partidos/${id}/battle-royale`, { resultados }))

export const listarUsuarios = (parametros = {}) => datos(api.get(`${BASE}/admin/usuarios`, { params: parametros }))

export default api

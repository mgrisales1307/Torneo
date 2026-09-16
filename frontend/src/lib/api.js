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

  const primerErrorDeCampo = Object.values(error.response.data?.errors ?? {}).flat().find(Boolean)

  return primerErrorDeCampo ?? error.response.data?.message ?? porDefecto
}

/* ------------------------------------------------------------ Autenticación */

export async function obtenerPerfil() {
  const { data } = await api.get(`${BASE}/perfil`)
  return data.usuario
}

export async function ingresar(credenciales) {
  const { data } = await api.post(`${BASE}/ingreso`, credenciales)
  return data.usuario
}

export async function registrarse(datos) {
  const { data } = await api.post(`${BASE}/registro`, datos)
  return data.usuario
}

export async function salir() {
  await api.post(`${BASE}/salida`)
}

/* ------------------------------------------------------------------ Público */

export async function obtenerInicio() {
  const { data } = await api.get(`${BASE}/inicio`)
  return data
}

export async function listarTorneos(parametros = {}) {
  const { data } = await api.get(`${BASE}/torneos`, { params: parametros })
  return data
}

export async function obtenerTorneo(slug) {
  const { data } = await api.get(`${BASE}/torneos/${slug}`)
  return data.data
}

export async function obtenerTabla(slug) {
  const { data } = await api.get(`${BASE}/torneos/${slug}/tabla`)
  return data.data
}

export async function obtenerPartidosDelTorneo(slug, parametros = {}) {
  const { data } = await api.get(`${BASE}/torneos/${slug}/partidos`, { params: parametros })
  return data
}

export async function obtenerGoleadores(slug) {
  const { data } = await api.get(`${BASE}/torneos/${slug}/goleadores`)
  return data.data
}

export async function obtenerEquiposDelTorneo(slug) {
  const { data } = await api.get(`${BASE}/torneos/${slug}/equipos`)
  return data.data
}

export async function obtenerEquipo(slug) {
  const { data } = await api.get(`${BASE}/equipos/${slug}`)
  return data
}

export async function obtenerPartido(id) {
  const { data } = await api.get(`${BASE}/partidos/${id}`)
  return data.data
}

/* ------------------------------------------------------- Panel del dueño */

export async function listarMisEquipos() {
  const { data } = await api.get(`${BASE}/panel/equipos`)
  return data.data
}

export async function obtenerMiEquipo(slug) {
  const { data } = await api.get(`${BASE}/panel/equipos/${slug}`)
  return data.data
}

export async function crearEquipo(datos) {
  const { data } = await api.post(`${BASE}/panel/equipos`, datos)
  return data.data
}

export async function actualizarEquipo(slug, datos) {
  const { data } = await api.put(`${BASE}/panel/equipos/${slug}`, datos)
  return data.data
}

export async function eliminarEquipo(slug) {
  await api.delete(`${BASE}/panel/equipos/${slug}`)
}

export async function subirImagenDeEquipo(slug, tipo, archivo) {
  const cuerpo = new FormData()
  cuerpo.append('tipo', tipo)
  cuerpo.append('imagen', archivo)

  const { data } = await api.post(`${BASE}/panel/equipos/${slug}/imagenes`, cuerpo)
  return data.data
}

export async function listarJugadores(slug) {
  const { data } = await api.get(`${BASE}/panel/equipos/${slug}/jugadores`)
  return data.data
}

export async function crearJugador(slug, datos) {
  const { data } = await api.post(`${BASE}/panel/equipos/${slug}/jugadores`, datos)
  return data.data
}

export async function actualizarJugador(id, datos) {
  const { data } = await api.put(`${BASE}/panel/jugadores/${id}`, datos)
  return data.data
}

export async function eliminarJugador(id) {
  await api.delete(`${BASE}/panel/jugadores/${id}`)
}

export async function subirFotoDeJugador(id, archivo) {
  const cuerpo = new FormData()
  cuerpo.append('foto', archivo)

  const { data } = await api.post(`${BASE}/panel/jugadores/${id}/foto`, cuerpo)
  return data.data
}

export async function listarMisInscripciones() {
  const { data } = await api.get(`${BASE}/panel/inscripciones`)
  return data.data
}

export async function inscribirEquipo(slugTorneo, equipoId) {
  const { data } = await api.post(`${BASE}/panel/torneos/${slugTorneo}/inscripciones`, {
    equipo_id: equipoId,
  })
  return data.data
}

export async function retirarInscripcion(id) {
  const { data } = await api.delete(`${BASE}/panel/inscripciones/${id}`)
  return data.data
}

export async function crearOrdenDePago(inscripcionId) {
  const { data } = await api.post(`${BASE}/panel/inscripciones/${inscripcionId}/orden`)
  return data
}

export async function obtenerOrden(referencia) {
  const { data } = await api.get(`${BASE}/panel/ordenes/${referencia}`)
  return data.data
}

export async function pagarConLaPasarelaSimulada(referencia, resultado) {
  const { data } = await api.post(`${BASE}/panel/ordenes/${referencia}/pagar-simulado`, { resultado })
  return data
}

/* --------------------------------------------------- Panel de organización */

export async function obtenerResumenAdmin() {
  const { data } = await api.get(`${BASE}/admin/resumen`)
  return data
}

export async function listarTorneosAdmin(parametros = {}) {
  const { data } = await api.get(`${BASE}/admin/torneos`, { params: parametros })
  return data
}

export async function obtenerTorneoAdmin(slug) {
  const { data } = await api.get(`${BASE}/admin/torneos/${slug}`)
  return data.data
}

export async function crearTorneo(datos) {
  const { data } = await api.post(`${BASE}/admin/torneos`, datos)
  return data.data
}

export async function actualizarTorneo(slug, datos) {
  const { data } = await api.put(`${BASE}/admin/torneos/${slug}`, datos)
  return data.data
}

export async function eliminarTorneo(slug) {
  await api.delete(`${BASE}/admin/torneos/${slug}`)
}

export async function generarCalendario(slug, reiniciar = false) {
  const { data } = await api.post(`${BASE}/admin/torneos/${slug}/calendario`, { reiniciar })
  return data
}

export async function listarInscripcionesAdmin(parametros = {}) {
  const { data } = await api.get(`${BASE}/admin/inscripciones`, { params: parametros })
  return data
}

export async function aprobarInscripcion(id, notas) {
  const { data } = await api.post(`${BASE}/admin/inscripciones/${id}/aprobar`, { notas })
  return data.data
}

export async function rechazarInscripcion(id, notas) {
  const { data } = await api.post(`${BASE}/admin/inscripciones/${id}/rechazar`, { notas })
  return data.data
}

export async function reprogramarPartido(id, datos) {
  const { data } = await api.put(`${BASE}/admin/partidos/${id}`, datos)
  return data.data
}

export async function registrarResultado(id, datos) {
  const { data } = await api.post(`${BASE}/admin/partidos/${id}/resultado`, datos)
  return data.data
}

export async function listarUsuarios(parametros = {}) {
  const { data } = await api.get(`${BASE}/admin/usuarios`, { params: parametros })
  return data
}

export default api

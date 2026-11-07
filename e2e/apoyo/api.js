import { request as playwrightRequest } from '@playwright/test'

export const ORIGEN = `http://127.0.0.1:${process.env.E2E_WEB_PORT ?? '5183'}`

/**
 * Cuentas de desarrollo que crea UsuariosSeeder en una base nueva.
 */
export const CUENTAS = {
  admin: { correo: 'admin@juegoszone.com', clave: 'admin123' },
  empleado: { correo: 'empleado@juegoszone.com', clave: 'trabajador123' },
  jugador: { correo: 'jugador@juegoszone.com', clave: 'jugador123' },
}

/**
 * Un cliente HTTP con su propia sesión, que habla con la API a través de Vite
 * igual que el navegador: mismo origen, cookie de sesión y token CSRF.
 */
export async function crearCliente() {
  const contexto = await playwrightRequest.newContext({
    baseURL: ORIGEN,
    extraHTTPHeaders: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      // Sanctum solo trata la petición como «de la aplicación» si llega de
      // un origen de confianza.
      Origin: ORIGEN,
      Referer: `${ORIGEN}/`,
    },
  })

  await contexto.get('/sanctum/csrf-cookie')

  async function enviar(metodo, ruta, datos) {
    const { cookies } = await contexto.storageState()
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')
    const opciones = { headers: xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {} }

    if (datos !== undefined) opciones.data = datos

    return contexto[metodo](ruta, opciones)
  }

  return {
    get: (ruta) => enviar('get', ruta),
    post: (ruta, datos = {}) => enviar('post', ruta, datos),
    put: (ruta, datos = {}) => enviar('put', ruta, datos),
    patch: (ruta, datos = {}) => enviar('patch', ruta, datos),
    delete: (ruta) => enviar('delete', ruta),
    cerrar: () => contexto.dispose(),
  }
}

/**
 * Falla con el cuerpo de la respuesta a la vista, que es lo que hace falta
 * para entender por qué una preparación no salió.
 */
export async function exigirOk(respuesta, contexto = 'La API') {
  if (!respuesta.ok()) {
    throw new Error(`${contexto} respondió ${respuesta.status()}: ${await respuesta.text()}`)
  }

  return respuesta.json()
}

export async function iniciarSesion(cliente, { correo, clave }) {
  return exigirOk(await cliente.post('/api/v1/ingreso', { correo, clave }), 'El ingreso')
}

export function sufijoUnico() {
  return `${Date.now().toString(36).slice(-4)}${Math.random().toString(36).slice(2, 6)}`
}

/**
 * Registra a un jugador nuevo (queda con la sesión abierta en ese cliente).
 */
export async function registrarJugador(cliente, sufijo = sufijoUnico()) {
  const datos = {
    apodo: `E2E${sufijo}`,
    nombre: `Jugador E2E ${sufijo}`,
    correo: `e2e.${sufijo}@ejemplo.test`,
    clave: 'Secreto123',
    clave_confirmation: 'Secreto123',
  }

  await exigirOk(await cliente.post('/api/v1/registro', datos), 'El registro')

  return { ...datos, sufijo }
}

export async function vincularCuenta(cliente, juego, identificador) {
  return exigirOk(await cliente.put(`/api/v1/panel/cuentas/${juego}`, { identificador }), 'Vincular la cuenta')
}

/**
 * Un torneo individual de Valorant de dos cupos, listo para inscribirse.
 */
export async function crearTorneoIndividual(admin, nombre, cupo = 2) {
  const { data } = await exigirOk(
    await admin.post('/api/v1/admin/torneos', {
      juego: 'valorant',
      nombre,
      formato: 'eliminacion_simple',
      modalidad: 'individual',
      cupo_maximo_equipos: cupo,
      mejor_de: 1,
      mejor_de_final: 1,
      costo_inscripcion_centavos: 100_000,
      cuenta_regresiva_segundos: 0,
    }),
    'Crear el torneo',
  )

  return data
}

/**
 * Inscribe a un jugador en un torneo individual y paga con la pasarela
 * simulada, todo por la API.
 */
export async function inscribirYPagar(cliente, slugTorneo) {
  const { data: inscripcion } = await exigirOk(
    await cliente.post(`/api/v1/panel/torneos/${slugTorneo}/inscripciones`, {}),
    'Inscribirse',
  )
  const { data: orden } = await exigirOk(
    await cliente.post(`/api/v1/panel/inscripciones/${inscripcion.id}/orden`),
    'Crear la orden',
  )

  await exigirOk(
    await cliente.post(`/api/v1/panel/ordenes/${orden.referencia}/pagar-simulado`, { resultado: 'aprobado' }),
    'Pagar',
  )

  return inscripcion
}

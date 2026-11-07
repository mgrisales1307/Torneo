import { expect, test } from '@playwright/test'
import {
  CUENTAS,
  ORIGEN,
  crearCliente,
  crearTorneoIndividual,
  exigirOk,
  inscribirYPagar,
  iniciarSesion,
  registrarJugador,
  sufijoUnico,
  vincularCuenta,
} from '../apoyo/api.js'

/*
 * Recorrido completo de un torneo individual con el flujo automático:
 *
 *   la organización publica → un jugador se inscribe y paga POR LA PANTALLA →
 *   otro lo hace por la API y se llena el cupo → la organización inicia →
 *   carga el resultado → el torneo termina con campeón.
 */
test('un torneo individual recorre su ciclo completo, de la inscripción al campeón', async ({ browser }) => {
  const admin = await crearCliente()
  await iniciarSesion(admin, CUENTAS.admin)

  const torneo = await crearTorneoIndividual(admin, `Copa E2E ${sufijoUnico()}`)

  expect(torneo.estado.valor).toBe('inscripciones_abiertas')

  /* ------------------------------------------- Jugador 1: por la pantalla */
  const clienteUno = await crearCliente()
  const uno = await registrarJugador(clienteUno)
  await vincularCuenta(clienteUno, 'valorant', `Uno${uno.sufijo}#COL`)

  const contexto = await browser.newContext({ baseURL: ORIGEN, locale: 'es-CO' })
  const page = await contexto.newPage()

  await page.goto('/ingreso')
  await page.getByLabel('Correo').fill(uno.correo)
  await page.getByLabel('Contraseña').fill(uno.clave)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page).toHaveURL(/\/panel$/)

  await page.goto(`/torneos/${torneo.slug}`)
  await expect(page.getByRole('heading', { name: torneo.nombre })).toBeVisible()

  await page.getByRole('button', { name: 'Inscribirme' }).click()
  await page.getByRole('button', { name: 'Confirmar inscripción' }).click()

  // Tras inscribirse, la pantalla lleva directo a pagar. Si no lo hiciera, el
  // botón «Pagar» de la ficha del torneo lleva al mismo sitio.
  const enPago = await page.waitForURL(/\/panel\/pagos\//, { timeout: 8_000 }).then(() => true, () => false)

  if (!enPago) {
    await page.getByRole('button', { name: /^Pagar/ }).click()
  }

  await expect(page).toHaveURL(/\/panel\/pagos\//)

  await page.getByRole('button', { name: /Confirmar pago simulado/ }).click()
  await expect(page.getByText('Tu cupo está confirmado')).toBeVisible()

  /* ----------------------------------------------- Jugador 2: por la API */
  const clienteDos = await crearCliente()
  const dos = await registrarJugador(clienteDos)
  await vincularCuenta(clienteDos, 'valorant', `Dos${dos.sufijo}#COL`)
  await inscribirYPagar(clienteDos, torneo.slug)

  /* --------------------------------------------------- Cupo lleno e inicio */
  const detalle = await exigirOk(await admin.get(`/api/v1/admin/torneos/${torneo.slug}`))

  expect(detalle.data.inscripcion.equipos_inscritos).toBe(2)
  expect(detalle.data.inicio.puede_iniciar).toBe(true)

  const iniciado = await exigirOk(await admin.post(`/api/v1/admin/torneos/${torneo.slug}/iniciar`), 'Iniciar')

  expect(iniciado.data.estado.valor).toBe('en_curso')

  /* -------------------------------------------------- Resultado y campeón */
  const cuadro = await exigirOk(await admin.get(`/api/v1/torneos/${torneo.slug}/cuadro`))
  const partidos = cuadro.data.flatMap((fase) => fase.partidos)

  expect(partidos).toHaveLength(1)

  await exigirOk(
    await admin.post(`/api/v1/admin/partidos/${partidos[0].id}/resultado`, { victorias_a: 1, victorias_b: 0 }),
    'Cargar el resultado',
  )

  const final = await exigirOk(await admin.get(`/api/v1/torneos/${torneo.slug}`))

  expect(final.data.estado.valor).toBe('finalizado')
  expect(final.data.campeon).not.toBeNull()
  expect(final.data.clasificacion_final).toHaveLength(2)

  /* ------------------------------------- Un visitante ve el torneo cerrado */
  const visitante = await browser.newContext({ baseURL: ORIGEN, locale: 'es-CO' })
  const paginaPublica = await visitante.newPage()

  await paginaPublica.goto(`/torneos/${torneo.slug}`)
  await expect(paginaPublica.getByRole('heading', { name: torneo.nombre })).toBeVisible()
  await expect(paginaPublica.getByText('Finalizado').first()).toBeVisible()

  await Promise.all([visitante.close(), contexto.close(), admin.cerrar(), clienteUno.cerrar(), clienteDos.cerrar()])
})

test('no se puede inscribir sin la cuenta del juego vinculada', async () => {
  const admin = await crearCliente()
  await iniciarSesion(admin, CUENTAS.admin)
  const torneo = await crearTorneoIndividual(admin, `Copa sin cuenta ${sufijoUnico()}`)

  const jugador = await crearCliente()
  await registrarJugador(jugador)

  const respuesta = await jugador.post(`/api/v1/panel/torneos/${torneo.slug}/inscripciones`, {})

  expect(respuesta.status()).toBe(422)

  await Promise.all([admin.cerrar(), jugador.cerrar()])
})

test('el cupo lleno cierra la puerta: el tercer jugador no entra', async () => {
  const admin = await crearCliente()
  await iniciarSesion(admin, CUENTAS.admin)
  const torneo = await crearTorneoIndividual(admin, `Copa llena ${sufijoUnico()}`)
  const clientes = []

  for (let indice = 0; indice < 3; indice++) {
    const cliente = await crearCliente()
    const jugador = await registrarJugador(cliente)
    await vincularCuenta(cliente, 'valorant', `Cupo${indice}${jugador.sufijo}#COL`)
    clientes.push(cliente)
  }

  await inscribirYPagar(clientes[0], torneo.slug)
  await inscribirYPagar(clientes[1], torneo.slug)

  const tercero = await clientes[2].post(`/api/v1/panel/torneos/${torneo.slug}/inscripciones`, {})

  expect(tercero.status()).toBe(422)

  await Promise.all([admin.cerrar(), ...clientes.map((cliente) => cliente.cerrar())])
})

test('un pago rechazado deja la inscripción sin confirmar', async () => {
  const admin = await crearCliente()
  await iniciarSesion(admin, CUENTAS.admin)
  const torneo = await crearTorneoIndividual(admin, `Copa rechazo ${sufijoUnico()}`)

  const cliente = await crearCliente()
  const jugador = await registrarJugador(cliente)
  await vincularCuenta(cliente, 'valorant', `Rech${jugador.sufijo}#COL`)

  const { data: inscripcion } = await exigirOk(await cliente.post(`/api/v1/panel/torneos/${torneo.slug}/inscripciones`, {}))
  const { data: orden } = await exigirOk(await cliente.post(`/api/v1/panel/inscripciones/${inscripcion.id}/orden`))

  const pago = await exigirOk(
    await cliente.post(`/api/v1/panel/ordenes/${orden.referencia}/pagar-simulado`, { resultado: 'rechazado' }),
  )

  expect(pago.data.estado.valor).not.toBe('pagada')

  const mias = await exigirOk(await cliente.get('/api/v1/panel/inscripciones'))

  expect(mias.data).toHaveLength(1)
  expect(mias.data[0].estado.valor).toBe('pendiente_pago')

  await Promise.all([admin.cerrar(), cliente.cerrar()])
})

test('la cartera de un jugador nuevo está en cero y no permite retirar', async () => {
  const cliente = await crearCliente()
  await registrarJugador(cliente)

  const cartera = await exigirOk(await cliente.get('/api/v1/panel/cartera'))

  expect(cartera.saldo_centavos).toBe(0)

  const retiro = await cliente.post('/api/v1/panel/cartera/retiros', {
    monto_centavos: 10_000,
    referencia: crypto.randomUUID(),
  })

  expect(retiro.status()).toBe(422)

  await cliente.cerrar()
})

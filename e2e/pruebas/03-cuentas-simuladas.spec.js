import { expect, test } from '@playwright/test'
import {
  CUENTAS,
  crearCliente,
  crearTorneoIndividual,
  exigirOk,
  inscribirYPagar,
  iniciarSesion,
  registrarJugador,
  sufijoUnico,
} from '../apoyo/api.js'

/*
 * La vinculación de cuentas de juego es SIMULADA: no se verifica con el juego
 * y no exige un formato. Basta un texto de entre 3 y 60 caracteres.
 */
test.describe('cuentas de juego simuladas', () => {
  test('la pantalla avisa que es simulada y guarda cualquier texto', async ({ page }) => {
    const cliente = await crearCliente()
    const jugador = await registrarJugador(cliente)

    await page.goto('/ingreso')
    await page.getByLabel('Correo').fill(jugador.correo)
    await page.getByLabel('Contraseña').fill(jugador.clave)
    await page.getByRole('button', { name: 'Entrar' }).click()
    await expect(page).toHaveURL(/\/panel$/)

    await page.goto('/panel/cuentas')
    await expect(page.getByText(/Vinculación simulada/)).toBeVisible()

    const campo = page.getByLabel('Riot ID en Valorant')

    await campo.fill(`mi cuenta de prueba ${jugador.sufijo}`)
    await page.locator('form', { has: campo }).getByRole('button', { name: 'Guardar' }).click()

    await expect(page.getByText('Riot ID guardado.').first()).toBeVisible()

    await cliente.cerrar()
  })

  test('por la API vale texto libre, pero no uno demasiado corto', async () => {
    const cliente = await crearCliente()
    const jugador = await registrarJugador(cliente)

    const libre = await cliente.put('/api/v1/panel/cuentas/dota2', { identificador: `cualquier cosa ${jugador.sufijo}` })
    const corto = await cliente.put('/api/v1/panel/cuentas/pubg', { identificador: 'ab' })

    expect(libre.status()).toBe(200)
    expect(corto.status()).toBe(422)

    await cliente.cerrar()
  })

  test('una cuenta con texto libre alcanza para inscribirse y pagar', async () => {
    const admin = await crearCliente()
    await iniciarSesion(admin, CUENTAS.admin)
    const torneo = await crearTorneoIndividual(admin, `Copa texto libre ${sufijoUnico()}`)

    const cliente = await crearCliente()
    const jugador = await registrarJugador(cliente)

    await exigirOk(
      await cliente.put('/api/v1/panel/cuentas/valorant', { identificador: `sin formato ${jugador.sufijo}` }),
      'Vincular la cuenta',
    )

    const inscripcion = await inscribirYPagar(cliente, torneo.slug)

    expect(inscripcion.id).toBeGreaterThan(0)

    const mias = await exigirOk(await cliente.get('/api/v1/panel/inscripciones'))

    expect(mias.data[0].estado.valor).toBe('aprobada')

    await Promise.all([admin.cerrar(), cliente.cerrar()])
  })

  test('la cuenta no puede estar a nombre de dos personas', async () => {
    const compartida = `Compartida ${sufijoUnico()}`
    const uno = await crearCliente()
    const dos = await crearCliente()

    await registrarJugador(uno)
    await registrarJugador(dos)

    expect((await uno.put('/api/v1/panel/cuentas/valorant', { identificador: compartida })).status()).toBe(200)
    expect((await dos.put('/api/v1/panel/cuentas/valorant', { identificador: compartida })).status()).toBe(422)

    await Promise.all([uno.cerrar(), dos.cerrar()])
  })
})

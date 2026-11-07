import { expect, test } from '@playwright/test'
import { CUENTAS, crearCliente, iniciarSesion, sufijoUnico } from '../apoyo/api.js'

// Sin esta cabecera Laravel contesta con una redirección en lugar de un 401.
const JSON_ = { headers: { Accept: 'application/json' } }

async function entrarPorLaPantalla(page, { correo, clave }) {
  await page.goto('/ingreso')
  await page.getByLabel('Correo').fill(correo)
  await page.getByLabel('Contraseña').fill(clave)
  await page.getByRole('button', { name: 'Entrar' }).click()
}

test.describe('visitante sin cuenta', () => {
  test('ve el sitio público y puede navegar a los torneos', async ({ page }) => {
    await page.goto('/')

    await page.getByRole('navigation', { name: 'Principal' }).getByRole('link', { name: 'Torneos' }).click()

    await expect(page).toHaveURL(/\/torneos$/)
  })

  test('una dirección que no existe muestra la página de error amable', async ({ page }) => {
    await page.goto('/esta-pagina-no-existe')

    await expect(page.getByText('Esta página no existe')).toBeVisible()
    await expect(page.getByRole('link', { name: 'Volver al inicio' })).toBeVisible()
  })

  test('las zonas con sesión lo mandan al ingreso', async ({ page }) => {
    await page.goto('/panel')
    await expect(page).toHaveURL(/\/ingreso$/)

    await page.goto('/organizacion')
    await expect(page).toHaveURL(/\/ingreso$/)
  })

  test('la API de los paneles responde 401 sin sesión', async ({ request }) => {
    expect((await request.get('/api/v1/panel/equipos', JSON_)).status()).toBe(401)
    expect((await request.get('/api/v1/panel/cartera', JSON_)).status()).toBe(401)
    expect((await request.get('/api/v1/admin/usuarios', JSON_)).status()).toBe(401)
  })

  test('las respuestas llevan las cabeceras de seguridad', async ({ request }) => {
    const respuesta = await request.get('/api/v1/inicio', JSON_)

    expect(respuesta.status()).toBe(200)
    expect(respuesta.headers()['x-frame-options']).toBe('DENY')
    expect(respuesta.headers()['x-content-type-options']).toBe('nosniff')
  })

  test('la notificación de pago sin firma se rechaza', async ({ request }) => {
    const respuesta = await request.post('/api/v1/pagos/notificaciones/simulada', { data: {}, ...JSON_ })

    expect(respuesta.status()).toBe(403)
  })
})

test.describe('registro', () => {
  test('una persona nueva crea su cuenta y llega a vincular sus cuentas de juego', async ({ page }) => {
    const sufijo = sufijoUnico()

    await page.goto('/registro')
    await page.getByLabel('Apodo').fill(`Nuevo${sufijo}`)
    await page.getByLabel('Nombre completo').fill('Persona de Prueba')
    await page.getByLabel('Correo').fill(`nuevo.${sufijo}@ejemplo.test`)
    await page.getByLabel('Contraseña', { exact: true }).fill('Secreto123')
    await page.getByLabel('Repítela').fill('Secreto123')
    await page.getByRole('button', { name: 'Crear cuenta' }).click()

    await expect(page).toHaveURL(/\/panel\/cuentas$/)
    await expect(page.getByRole('heading', { name: 'Cuentas de juego' })).toBeVisible()
  })

  test('si las contraseñas no coinciden avisa y no crea la cuenta', async ({ page }) => {
    await page.goto('/registro')
    await page.getByLabel('Apodo').fill(`Distinta${sufijoUnico()}`)
    await page.getByLabel('Nombre completo').fill('Persona de Prueba')
    await page.getByLabel('Correo').fill(`distinta.${sufijoUnico()}@ejemplo.test`)
    await page.getByLabel('Contraseña', { exact: true }).fill('Secreto123')
    await page.getByLabel('Repítela').fill('OtraClave123')
    await page.getByRole('button', { name: 'Crear cuenta' }).click()

    await expect(page.getByText('Las dos contraseñas deben ser iguales.')).toBeVisible()
    await expect(page).toHaveURL(/\/registro$/)
  })

  test('un correo ya registrado se rechaza con un mensaje en el campo', async ({ page }) => {
    await page.goto('/registro')
    await page.getByLabel('Apodo').fill(`Repetido${sufijoUnico()}`)
    await page.getByLabel('Nombre completo').fill('Persona de Prueba')
    await page.getByLabel('Correo').fill(CUENTAS.jugador.correo)
    await page.getByLabel('Contraseña', { exact: true }).fill('Secreto123')
    await page.getByLabel('Repítela').fill('Secreto123')
    await page.getByRole('button', { name: 'Crear cuenta' }).click()

    await expect(page.getByRole('alert').first()).toBeVisible()
    await expect(page).toHaveURL(/\/registro$/)
  })
})

test.describe('ingreso y permisos por rol', () => {
  test('con una contraseña incorrecta no entra y ve un error', async ({ page }) => {
    await entrarPorLaPantalla(page, { correo: CUENTAS.jugador.correo, clave: 'incorrecta123' })

    await expect(page.getByRole('alert')).toBeVisible()
    await expect(page).toHaveURL(/\/ingreso$/)
  })

  test('el jugador entra a su panel pero no a la zona de la organización', async ({ page }) => {
    await entrarPorLaPantalla(page, CUENTAS.jugador)
    await expect(page).toHaveURL(/\/panel$/)

    await page.goto('/organizacion')
    await expect(page.getByText('Esta zona es de la organización')).toBeVisible()
  })

  test('el empleado sí entra a la zona de la organización', async ({ page }) => {
    await entrarPorLaPantalla(page, CUENTAS.empleado)
    await expect(page).toHaveURL(/\/panel$/)

    await page.goto('/organizacion')
    await expect(page).toHaveURL(/\/organizacion$/)
    await expect(page.getByText('Esta zona es de la organización')).toHaveCount(0)
  })

  test('después de iniciar sesión vuelve a la página que intentaba abrir', async ({ page }) => {
    await page.goto('/panel/cartera')
    await expect(page).toHaveURL(/\/ingreso$/)

    await page.getByLabel('Correo').fill(CUENTAS.jugador.correo)
    await page.getByLabel('Contraseña').fill(CUENTAS.jugador.clave)
    await page.getByRole('button', { name: 'Entrar' }).click()

    await expect(page).toHaveURL(/\/panel\/cartera$/)
    await expect(page.getByRole('heading', { name: 'Cartera', exact: true })).toBeVisible()
  })
})

test.describe('permisos en la API', () => {
  test('el jugador recibe 403 en las rutas de la organización', async () => {
    const cliente = await crearCliente()

    await iniciarSesion(cliente, CUENTAS.jugador)

    expect((await cliente.get('/api/v1/admin/usuarios')).status()).toBe(403)
    expect((await cliente.get('/api/v1/admin/resumen')).status()).toBe(403)
    expect((await cliente.post('/api/v1/admin/torneos', {})).status()).toBe(403)

    await cliente.cerrar()
  })

  test('el empleado ve el listado de usuarios sin la clave de nadie', async () => {
    const cliente = await crearCliente()

    await iniciarSesion(cliente, CUENTAS.empleado)

    const respuesta = await cliente.get('/api/v1/admin/usuarios')
    const texto = await respuesta.text()

    expect(respuesta.status()).toBe(200)
    expect(texto).not.toContain('"clave"')
    expect(texto).not.toContain('$2y$')

    await cliente.cerrar()
  })

  test('cerrar sesión invalida la cookie', async () => {
    const cliente = await crearCliente()

    await iniciarSesion(cliente, CUENTAS.jugador)
    expect((await cliente.get('/api/v1/perfil')).status()).toBe(200)

    await cliente.post('/api/v1/salida')
    expect((await cliente.get('/api/v1/perfil')).status()).toBe(401)

    await cliente.cerrar()
  })
})

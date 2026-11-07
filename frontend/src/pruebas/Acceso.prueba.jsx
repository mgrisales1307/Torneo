import { AxiosError } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { ProveedorDeSesion } from '../contexto/ProveedorDeSesion'
import Ingreso from '../paginas/acceso/Ingreso'
import Registro from '../paginas/acceso/Registro'

// Solo se reemplazan las llamadas a la red; los ayudantes de errores son los reales.
vi.mock('../lib/api', async (importarOriginal) => ({
  ...(await importarOriginal()),
  obtenerPerfil: vi.fn(),
  ingresar: vi.fn(),
  registrarse: vi.fn(),
  salir: vi.fn(),
}))

const api = await import('../lib/api')

const JUGADOR = { id: 1, apodo: 'Condor', rol: { valor: 'jugador' } }

function errorHttp(status, data) {
  return new AxiosError('fallo', 'ERR_BAD_REQUEST', undefined, undefined, { status, data })
}

function montar(pantalla, ruta) {
  const cliente = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={cliente}>
      <ProveedorDeSesion>
        <MemoryRouter initialEntries={[ruta]}>
          <Routes>
            <Route path="/ingreso" element={<Ingreso />} />
            <Route path="/registro" element={<Registro />} />
            <Route path="/panel" element={<p>Panel del jugador</p>} />
            <Route path="/panel/cuentas" element={<p>Mis cuentas de juego</p>} />
            <Route path="/otra" element={pantalla} />
          </Routes>
        </MemoryRouter>
      </ProveedorDeSesion>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  // Visitante anónimo: la API responde 401 al pedir el perfil.
  api.obtenerPerfil.mockRejectedValue(errorHttp(401, { message: 'No autenticado.' }))
})

describe('Ingreso', () => {
  it('envía el correo sin espacios y entra al panel', async () => {
    api.ingresar.mockResolvedValue(JUGADOR)
    const usuario = userEvent.setup()

    montar(null, '/ingreso')

    await usuario.type(await screen.findByLabelText('Correo'), '  ana@ejemplo.test  ')
    await usuario.type(screen.getByLabelText('Contraseña'), 'secreto123')
    await usuario.click(screen.getByRole('button', { name: 'Entrar' }))

    await waitFor(() => expect(screen.getByText('Panel del jugador')).toBeInTheDocument())

    expect(api.ingresar).toHaveBeenCalledTimes(1)
    expect(api.ingresar.mock.calls[0][0]).toEqual({
      correo: 'ana@ejemplo.test',
      clave: 'secreto123',
      recordarme: false,
    })
  })

  it('con credenciales incorrectas muestra el mensaje y vacía la contraseña', async () => {
    api.ingresar.mockRejectedValue(errorHttp(422, { errors: { correo: ['Las credenciales no son correctas.'] } }))
    const usuario = userEvent.setup()

    montar(null, '/ingreso')

    await usuario.type(await screen.findByLabelText('Correo'), 'ana@ejemplo.test')
    await usuario.type(screen.getByLabelText('Contraseña'), 'incorrecta1')
    await usuario.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Las credenciales no son correctas.')
    expect(screen.getByLabelText('Contraseña')).toHaveValue('')
    expect(screen.getByLabelText('Correo')).toHaveValue('ana@ejemplo.test')
    expect(screen.queryByText('Panel del jugador')).not.toBeInTheDocument()
  })

  it('si el servidor limita los intentos lo explica', async () => {
    api.ingresar.mockRejectedValue(errorHttp(429, {}))
    const usuario = userEvent.setup()

    montar(null, '/ingreso')

    await usuario.type(await screen.findByLabelText('Correo'), 'ana@ejemplo.test')
    await usuario.type(screen.getByLabelText('Contraseña'), 'incorrecta1')
    await usuario.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(/Demasiados intentos/)
  })

  it('quien ya tiene sesión no ve el formulario: pasa directo al panel', async () => {
    api.obtenerPerfil.mockResolvedValue(JUGADOR)

    montar(null, '/ingreso')

    expect(await screen.findByText('Panel del jugador')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Entrar' })).not.toBeInTheDocument()
  })

  it('ofrece el enlace para crear una cuenta', async () => {
    montar(null, '/ingreso')

    expect(await screen.findByRole('link', { name: 'Crear cuenta' })).toHaveAttribute('href', '/registro')
  })
})

describe('Registro', () => {
  async function llenar(usuario, { clave = 'Secreto123', repetida = 'Secreto123' } = {}) {
    await usuario.type(await screen.findByLabelText(/^Apodo/), 'CondorAndino')
    await usuario.type(screen.getByLabelText('Nombre completo'), 'Laura Gómez')
    await usuario.type(screen.getByLabelText('Correo'), 'laura@ejemplo.test')
    await usuario.type(screen.getByLabelText('Contraseña'), clave)
    await usuario.type(screen.getByLabelText('Repítela'), repetida)
  }

  it('si las contraseñas no coinciden no llama a la API', async () => {
    const usuario = userEvent.setup()

    montar(null, '/registro')
    await llenar(usuario, { repetida: 'OtraClave123' })
    await usuario.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    expect(await screen.findByText('Las dos contraseñas deben ser iguales.')).toBeInTheDocument()
    expect(api.registrarse).not.toHaveBeenCalled()
  })

  it('con datos correctos crea la cuenta y lleva a vincular las cuentas de juego', async () => {
    api.registrarse.mockResolvedValue(JUGADOR)
    const usuario = userEvent.setup()

    montar(null, '/registro')
    await llenar(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    await waitFor(() => expect(screen.getByText('Mis cuentas de juego')).toBeInTheDocument())

    expect(api.registrarse.mock.calls[0][0]).toMatchObject({
      apodo: 'CondorAndino',
      nombre: 'Laura Gómez',
      correo: 'laura@ejemplo.test',
      clave: 'Secreto123',
      clave_confirmation: 'Secreto123',
    })
  })

  it('muestra los errores del servidor junto a cada campo', async () => {
    api.registrarse.mockRejectedValue(
      errorHttp(422, { errors: { apodo: ['Ese apodo ya está en uso.'], correo: ['Ese correo ya tiene cuenta.'] } }),
    )
    const usuario = userEvent.setup()

    montar(null, '/registro')
    await llenar(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    expect(await screen.findByText('Ese apodo ya está en uso.')).toBeInTheDocument()
    expect(screen.getByText('Ese correo ya tiene cuenta.')).toBeInTheDocument()
    expect(screen.queryByText('Mis cuentas de juego')).not.toBeInTheDocument()
  })

  it('el campo de apodo limita la longitud a 20 caracteres', async () => {
    montar(null, '/registro')

    expect(await screen.findByLabelText(/^Apodo/)).toHaveAttribute('maxlength', '20')
  })
})

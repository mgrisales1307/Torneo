import { beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import Cuentas from '../paginas/panel/Cuentas'

vi.mock('../lib/api', async (importarOriginal) => ({
  ...(await importarOriginal()),
  listarJuegos: vi.fn(),
  listarMisCuentas: vi.fn(),
  guardarCuenta: vi.fn(),
  quitarCuenta: vi.fn(),
}))

// La navegación del panel es de otra pantalla y depende de la sesión; aquí no
// se prueba, así que se reemplaza por algo vacío.
vi.mock('../paginas/panel/NavegacionDelPanel', () => ({ NavegacionDelPanel: () => null }))

const api = await import('../lib/api')

const JUEGOS = [
  { valor: 'valorant', nombre: 'Valorant', cuenta: { nombre: 'Riot ID', ejemplo: 'Nombre#LAN' } },
  { valor: 'dota2', nombre: 'Dota 2', cuenta: { nombre: 'Steam ID', ejemplo: '76561198012345678' } },
]

function pintar() {
  const cliente = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={cliente}>
      <MemoryRouter>
        <Cuentas />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  api.listarJuegos.mockResolvedValue(JUEGOS)
  api.listarMisCuentas.mockResolvedValue([])
})

describe('Cuentas de juego (vinculación simulada)', () => {
  it('avisa que la vinculación es simulada y no se verifica con el juego', async () => {
    pintar()

    expect(await screen.findByText(/Vinculación simulada/)).toBeInTheDocument()
    expect(screen.getByText(/no se verifica con el juego/)).toBeInTheDocument()
  })

  it('muestra una fila por juego con el nombre de su cuenta', async () => {
    pintar()

    expect(await screen.findByLabelText('Riot ID en Valorant')).toBeInTheDocument()
    expect(screen.getByLabelText('Steam ID en Dota 2')).toBeInTheDocument()
  })

  it('el formato clásico solo se sugiere como ejemplo, no se exige', async () => {
    pintar()

    expect(await screen.findByLabelText('Riot ID en Valorant')).toHaveAttribute('placeholder', 'Nombre#LAN')
  })

  it('guarda tal cual el texto libre que escribe la persona', async () => {
    api.guardarCuenta.mockResolvedValue({ message: 'Riot ID guardado.' })
    const usuario = userEvent.setup()

    pintar()

    await usuario.type(await screen.findByLabelText('Riot ID en Valorant'), '  mi cuenta de prueba  ')
    await usuario.click(screen.getAllByRole('button', { name: 'Guardar' })[0])

    await waitFor(() => expect(api.guardarCuenta).toHaveBeenCalledTimes(1))
    expect(api.guardarCuenta).toHaveBeenCalledWith('valorant', 'mi cuenta de prueba')
    expect(await screen.findByText('Riot ID guardado.')).toBeInTheDocument()
  })

  it('el botón Guardar está apagado mientras el campo está vacío', async () => {
    pintar()

    await screen.findByLabelText('Riot ID en Valorant')

    for (const boton of screen.getAllByRole('button', { name: 'Guardar' })) {
      expect(boton).toBeDisabled()
    }
  })

  it('muestra el error que devuelve el servidor, por ejemplo una cuenta repetida', async () => {
    const { AxiosError } = await import('axios')

    api.guardarCuenta.mockRejectedValue(
      new AxiosError('fallo', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 422,
        data: { errors: { identificador: ['Ese Riot ID ya está vinculado a otra cuenta.'] } },
      }),
    )
    const usuario = userEvent.setup()

    pintar()

    await usuario.type(await screen.findByLabelText('Riot ID en Valorant'), 'Repetida')
    await usuario.click(screen.getAllByRole('button', { name: 'Guardar' })[0])

    expect(await screen.findByText('Ese Riot ID ya está vinculado a otra cuenta.')).toBeInTheDocument()
  })

  it('con una cuenta ya vinculada ofrece quitarla', async () => {
    api.listarMisCuentas.mockResolvedValue([{ juego: 'valorant', identificador: 'Parcero#LAN' }])

    pintar()

    expect(await screen.findByDisplayValue('Parcero#LAN')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Quitar' })).toBeInTheDocument()
  })
})

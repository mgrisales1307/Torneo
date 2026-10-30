import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { NavegacionDelPanel } from '../paginas/panel/NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR, ENLACES_DE_ORGANIZACION } from '../paginas/panel/enlacesDelPanel'
import { useSesion } from '../contexto/contextoDeSesion'

vi.mock('../contexto/contextoDeSesion', () => ({ useSesion: vi.fn() }))

beforeEach(() => vi.clearAllMocks())

function montar(enlaces = ENLACES_DEL_JUGADOR) {
  render(<MemoryRouter><NavegacionDelPanel enlaces={enlaces} /></MemoryRouter>)
}

describe('Acceso a métricas desde Mi panel', () => {
  it.each(['admin', 'empleado'])('muestra las herramientas de organización para %s', (rol) => {
    useSesion.mockReturnValue({ esStaff: true, rol })
    montar()
    expect(screen.getByRole('link', { name: 'Métricas', exact: true })).toHaveAttribute('href', '/organizacion/metricas')
    expect(screen.getByRole('link', { name: 'Organización', exact: true })).toHaveAttribute('href', '/organizacion')
    expect(screen.getByRole('link', { name: 'Cartera', exact: true })).toBeInTheDocument()
  })

  it('mantiene el panel de jugador sin accesos administrativos', () => {
    useSesion.mockReturnValue({ esStaff: false, rol: 'jugador' })
    montar()
    expect(screen.queryByRole('link', { name: 'Métricas', exact: true })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Organización', exact: true })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Pendientes', exact: true })).toBeInTheDocument()
  })

  it('no duplica accesos en la navegación de Organización', () => {
    useSesion.mockReturnValue({ esStaff: true, rol: 'admin' })
    montar(ENLACES_DE_ORGANIZACION)
    expect(screen.getAllByRole('link', { name: 'Métricas', exact: true })).toHaveLength(1)
  })
})

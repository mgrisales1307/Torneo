import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { RutaProtegida } from '../componentes/diseno/RutaProtegida'
import { ContextoDeSesion } from '../contexto/contextoDeSesion'

function PantallaDeIngreso() {
  const ubicacion = useLocation()

  return <p>Pantalla de ingreso. Destino: {ubicacion.state?.destino}</p>
}

function montar({ sesion, rutas, inicial = '/panel' }) {
  const valor = {
    cargandoSesion: false,
    estaAutenticado: false,
    rol: null,
    ...sesion,
  }

  return render(
    <ContextoDeSesion.Provider value={valor}>
      <MemoryRouter initialEntries={[inicial]}>
        <Routes>
          <Route path="/ingreso" element={<PantallaDeIngreso />} />
          <Route path="/" element={<p>Inicio</p>} />
          <Route element={<RutaProtegida rolesPermitidos={rutas} />}>
            <Route path="/panel" element={<p>Contenido privado</p>} />
            <Route path="/organizacion" element={<p>Contenido de la organización</p>} />
          </Route>
        </Routes>
      </MemoryRouter>
    </ContextoDeSesion.Provider>,
  )
}

describe('RutaProtegida', () => {
  it('mientras comprueba la sesión no muestra contenido ni redirige', () => {
    montar({ sesion: { cargandoSesion: true } })

    expect(screen.getByText('Comprobando tu sesión…')).toBeInTheDocument()
    expect(screen.queryByText('Contenido privado')).not.toBeInTheDocument()
    expect(screen.queryByText(/Pantalla de ingreso/)).not.toBeInTheDocument()
  })

  it('sin sesión manda al ingreso y recuerda a dónde iba', () => {
    montar({ sesion: { estaAutenticado: false }, inicial: '/panel' })

    expect(screen.getByText(/Pantalla de ingreso/)).toHaveTextContent('Destino: /panel')
    expect(screen.queryByText('Contenido privado')).not.toBeInTheDocument()
  })

  it('con sesión y sin restricción de rol deja pasar a cualquiera', () => {
    montar({ sesion: { estaAutenticado: true, rol: 'jugador' } })

    expect(screen.getByText('Contenido privado')).toBeInTheDocument()
  })

  it('un jugador no entra a la zona de la organización', () => {
    montar({
      sesion: { estaAutenticado: true, rol: 'jugador' },
      rutas: ['admin', 'empleado'],
      inicial: '/organizacion',
    })

    expect(screen.getByText('Esta zona es de la organización')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al inicio' })).toHaveAttribute('href', '/')
    expect(screen.queryByText('Contenido de la organización')).not.toBeInTheDocument()
  })

  it.each(['admin', 'empleado'])('el rol %s sí entra a la zona de la organización', (rol) => {
    montar({
      sesion: { estaAutenticado: true, rol },
      rutas: ['admin', 'empleado'],
      inicial: '/organizacion',
    })

    expect(screen.getByText('Contenido de la organización')).toBeInTheDocument()
  })
})

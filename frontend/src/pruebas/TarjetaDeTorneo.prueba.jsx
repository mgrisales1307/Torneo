import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { TarjetaDeTorneo } from '../componentes/torneo/TarjetaDeTorneo'

function torneoDePrueba(cambios = {}) {
  return {
    slug: 'copa-andina',
    nombre: 'Copa Andina',
    modalidad: 'equipos',
    juego: { valor: 'valorant', nombre: 'Valorant' },
    formato: { valor: 'eliminacion_simple', etiqueta: 'Eliminación simple' },
    estado: { valor: 'inscripciones_abiertas', etiqueta: 'Inscripciones abiertas' },
    ajustes: { cupo_maximo_equipos: 8 },
    inscripcion: { equipos_inscritos: 4, costo_formateado: '$ 50.000' },
    fechas: { inicia_en: '2030-05-10T15:00:00' },
    premios: { total_formateado: null },
    inicio: { por_cupo: false },
    campeon: null,
    ...cambios,
  }
}

function pintar(torneo) {
  return render(
    <MemoryRouter>
      <TarjetaDeTorneo torneo={torneo} />
    </MemoryRouter>,
  )
}

describe('TarjetaDeTorneo', () => {
  it('enlaza al torneo por su slug y muestra nombre, juego, formato y estado', () => {
    pintar(torneoDePrueba())

    expect(screen.getByRole('link')).toHaveAttribute('href', '/torneos/copa-andina')
    expect(screen.getByRole('heading', { name: 'Copa Andina' })).toBeInTheDocument()
    expect(screen.getByText('Valorant')).toBeInTheDocument()
    expect(screen.getByText('Eliminación simple')).toBeInTheDocument()
    expect(screen.getByText('Inscripciones abiertas')).toBeInTheDocument()
  })

  it('muestra los cupos ocupados sobre el máximo', () => {
    pintar(torneoDePrueba())

    expect(screen.getByText(/4\/8/)).toBeInTheDocument()
  })

  it('sin premios muestra el costo de inscripción', () => {
    pintar(torneoDePrueba())

    expect(screen.getByText('Inscripción')).toBeInTheDocument()
    expect(screen.getByText('$ 50.000')).toBeInTheDocument()
  })

  it('con premios muestra el total de premios en lugar del costo', () => {
    pintar(torneoDePrueba({ premios: { total_formateado: '$ 3.000.000' } }))

    expect(screen.getByText('Premios')).toBeInTheDocument()
    expect(screen.getByText('$ 3.000.000')).toBeInTheDocument()
  })

  it('un torneo con campeón muestra al campeón', () => {
    pintar(
      torneoDePrueba({
        estado: { valor: 'finalizado', etiqueta: 'Finalizado' },
        campeon: { nombre: 'Cóndores Andinos' },
      }),
    )

    expect(screen.getByText('Campeón')).toBeInTheDocument()
    expect(screen.getByText('Cóndores Andinos')).toBeInTheDocument()
  })

  it('si el inicio depende del cupo avisa que arranca al completarse', () => {
    pintar(torneoDePrueba({ inicio: { por_cupo: true } }))

    expect(screen.getByText(/Inicio al completar los cupos/)).toBeInTheDocument()
  })

  it('con el torneo en curso muestra la fecha aunque el inicio sea por cupo', () => {
    pintar(torneoDePrueba({ inicio: { por_cupo: true }, estado: { valor: 'en_curso', etiqueta: 'En curso' } }))

    expect(screen.queryByText(/Inicio al completar los cupos/)).not.toBeInTheDocument()
  })

  it('distingue la modalidad individual', () => {
    pintar(torneoDePrueba({ modalidad: 'individual' }))

    expect(screen.getByText('Individual')).toBeInTheDocument()
  })

  it('con el cupo en cero no divide por cero', () => {
    pintar(torneoDePrueba({ ajustes: { cupo_maximo_equipos: 0 }, inscripcion: { equipos_inscritos: 0, costo_formateado: '$ 0' } }))

    expect(screen.getByText(/0\/0/)).toBeInTheDocument()
  })
})

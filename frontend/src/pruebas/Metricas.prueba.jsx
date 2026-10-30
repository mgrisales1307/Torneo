import { StrictMode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Link } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MetricasDeNavegacion } from '../componentes/diseno/MetricasDeNavegacion'
import { clasificarPagina, enviarMetrica, guardarPreferenciaMetricas } from '../lib/metricas'
import { obtenerConfiguracionMetricas, registrarMetrica } from '../lib/api'

vi.mock('../lib/api', () => ({ obtenerConfiguracionMetricas: vi.fn(), registrarMetrica: vi.fn().mockResolvedValue(undefined) }))
vi.mock('web-vitals', () => ({ onCLS: vi.fn(), onINP: vi.fn(), onLCP: vi.fn() }))

function montar() {
  return render(<StrictMode><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter><MetricasDeNavegacion /><Link to="/torneos">Ver torneos</Link></MemoryRouter></QueryClientProvider></StrictMode>)
}

beforeEach(() => {
  localStorage.clear()
  sessionStorage.clear()
  vi.clearAllMocks()
  obtenerConfiguracionMetricas.mockResolvedValue({ habilitadas: true })
  Object.defineProperty(navigator, 'doNotTrack', { value: undefined, configurable: true })
  Object.defineProperty(navigator, 'globalPrivacyControl', { value: undefined, configurable: true })
})

describe('Privacidad de métricas', () => {
  it('espera consentimiento y evita visitas duplicadas en StrictMode', async () => {
    montar()
    await screen.findByRole('button', { name: 'Aceptar métricas' })
    expect(registrarMetrica).not.toHaveBeenCalled()
    await userEvent.click(screen.getByRole('button', { name: 'Aceptar métricas' }))
    await waitFor(() => expect(registrarMetrica).toHaveBeenCalledTimes(1))
    expect(registrarMetrica).toHaveBeenCalledWith(expect.objectContaining({ tipo: 'pagina', pagina: 'inicio', consentimiento: true }), expect.any(Function))
    await userEvent.click(screen.getByRole('link', { name: 'Ver torneos' }))
    await waitFor(() => expect(registrarMetrica).toHaveBeenCalledTimes(2))
  })

  it('rechazar o retirar permiso impide transmitir', async () => {
    montar()
    await userEvent.click(await screen.findByRole('button', { name: 'Rechazar métricas' }))
    await userEvent.click(screen.getByRole('link', { name: 'Ver torneos' }))
    expect(registrarMetrica).not.toHaveBeenCalled()
    guardarPreferenciaMetricas('aceptadas')
    enviarMetrica('inicio')
    expect(registrarMetrica).toHaveBeenCalledTimes(1)
    guardarPreferenciaMetricas('rechazadas')
    enviarMetrica('inicio')
    expect(registrarMetrica).toHaveBeenCalledTimes(1)
    expect(sessionStorage.getItem('nexzone-sesion-metricas')).toBeNull()
  })

  it('no mide con GPC aunque existiera consentimiento previo', async () => {
    guardarPreferenciaMetricas('aceptadas')
    Object.defineProperty(navigator, 'globalPrivacyControl', { value: true, configurable: true })
    montar()
    await screen.findByRole('button', { name: 'Privacidad de métricas' })
    expect(registrarMetrica).not.toHaveBeenCalled()
  })

  it('no mide con Do Not Track', () => {
    guardarPreferenciaMetricas('aceptadas')
    Object.defineProperty(navigator, 'doNotTrack', { value: '1', configurable: true })
    enviarMetrica('inicio')
    expect(registrarMetrica).not.toHaveBeenCalled()
  })

  it('respeta desactivación del servidor', async () => {
    obtenerConfiguracionMetricas.mockResolvedValue({ habilitadas: false })
    guardarPreferenciaMetricas('aceptadas')
    montar()
    await waitFor(() => expect(obtenerConfiguracionMetricas).toHaveBeenCalled())
    expect(registrarMetrica).not.toHaveBeenCalled()
    expect(screen.queryByRole('button', { name: 'Privacidad de métricas' })).not.toBeInTheDocument()
  })

  it('elimina identidades de rutas y excluye organización', () => {
    expect(clasificarPagina('/jugadores/Jose')).toBe('jugador')
    expect(clasificarPagina('/panel/pagos/referencia-secreta')).toBe('panel')
    expect(clasificarPagina('/organizacion/metricas')).toBeNull()
    expect(clasificarPagina('/desconocida')).toBe('otra')
  })

  it('rota la sesión tras treinta minutos sin actividad', () => {
    guardarPreferenciaMetricas('aceptadas')
    enviarMetrica('inicio')
    const primera = registrarMetrica.mock.calls[0][0].sesion
    sessionStorage.setItem('nexzone-sesion-metricas', JSON.stringify({ id: primera, ultimo: Date.now() - 31 * 60 * 1000 }))
    enviarMetrica('torneos')
    expect(registrarMetrica.mock.calls[1][0].sesion).not.toBe(primera)
  })
})

import { afterEach, describe, expect, it, vi } from 'vitest'
import { registrarMetrica } from '../lib/api'

afterEach(() => {
  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'
  vi.unstubAllGlobals()
})

describe('Transporte de métricas', () => {
  it('conserva CSRF y permite completar la muestra al ocultarse la página', async () => {
    document.cookie = 'XSRF-TOKEN=prueba%3D; path=/'
    const enviar = vi.fn().mockResolvedValue({ ok: true })
    vi.stubGlobal('fetch', enviar)
    await registrarMetrica({ tipo: 'pagina' }, () => true)
    expect(enviar).toHaveBeenCalledWith('/api/v1/metricas/eventos', expect.objectContaining({
      keepalive: true, credentials: 'same-origin',
      headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'prueba=' }),
    }))
  })

  it('cancela una transmisión pendiente si se retira el consentimiento', async () => {
    document.cookie = 'XSRF-TOKEN=prueba; path=/'
    const enviar = vi.fn()
    vi.stubGlobal('fetch', enviar)
    let autorizado = true
    const pendiente = registrarMetrica({ tipo: 'pagina' }, () => autorizado)
    autorizado = false
    await pendiente
    expect(enviar).not.toHaveBeenCalled()
  })
})

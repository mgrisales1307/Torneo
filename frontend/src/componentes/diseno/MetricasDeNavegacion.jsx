import { useEffect, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { obtenerConfiguracionMetricas } from '../../lib/api'
import { clasificarPagina, enviarMetrica, guardarPreferenciaMetricas, iniciarRendimiento, leerPreferenciaMetricas, privacidadDelNavegador } from '../../lib/metricas'
import { Boton } from '../ui/Basicos'

export function MetricasDeNavegacion() {
  const ubicacion = useLocation()
  const [preferencia, cambiarPreferencia] = useState(leerPreferenciaMetricas)
  const [abierto, cambiarAbierto] = useState(false)
  const ultimaNavegacion = useRef(null)
  const paginaInicial = useRef(clasificarPagina(ubicacion.pathname))
  const configuracion = useQuery({ queryKey: ['configuracion-metricas'], queryFn: obtenerConfiguracionMetricas, staleTime: 5 * 60 * 1000, retry: false })
  const habilitadas = configuracion.data?.habilitadas === true
  const bloqueadas = privacidadDelNavegador()

  useEffect(() => {
    if (!habilitadas || preferencia !== 'aceptadas' || bloqueadas) return
    if (ultimaNavegacion.current !== ubicacion.key) {
      enviarMetrica(clasificarPagina(ubicacion.pathname))
      ultimaNavegacion.current = ubicacion.key
    }
    iniciarRendimiento(paginaInicial.current)
  }, [habilitadas, preferencia, bloqueadas, ubicacion.key, ubicacion.pathname])

  useEffect(() => {
    const sincronizar = () => cambiarPreferencia(leerPreferenciaMetricas())
    window.addEventListener('storage', sincronizar)
    return () => window.removeEventListener('storage', sincronizar)
  }, [])

  function elegir(valor) {
    guardarPreferenciaMetricas(valor)
    cambiarPreferencia(leerPreferenciaMetricas())
    cambiarAbierto(false)
    ultimaNavegacion.current = null
  }

  if (!habilitadas) return null

  return (
    <>
      <button className="preferencias-metricas" type="button" onClick={() => cambiarAbierto((valor) => !valor)} aria-expanded={abierto || (!preferencia && !bloqueadas)}>Privacidad de métricas</button>
      {(abierto || (!preferencia && !bloqueadas)) && (
        <section className="consentimiento-metricas" aria-label="Preferencias de métricas">
          <div>
            <strong>Ayúdanos a mejorar NexZone</strong>
            <p>Con tu permiso medimos visitas y rendimiento. No guardamos tu IP, correo ni las páginas con tu nombre. Puedes cambiar tu decisión aquí.</p>
            {bloqueadas && <p>Respetamos la señal de privacidad de tu navegador: la captura está desactivada.</p>}
          </div>
          <div className="fila">
            <Boton variante="fantasma" onClick={() => elegir('rechazadas')}>Rechazar métricas</Boton>
            <Boton disabled={bloqueadas} onClick={() => elegir('aceptadas')}>Aceptar métricas</Boton>
          </div>
        </section>
      )}
    </>
  )
}

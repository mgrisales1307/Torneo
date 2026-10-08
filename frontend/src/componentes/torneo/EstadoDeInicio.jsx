import { useEffect, useState } from 'react'

export function EstadoDeInicio({ torneo }) {
  const [ahora, setAhora] = useState(() => Date.now())
  useEffect(() => {
    const intervalo = window.setInterval(() => setAhora(Date.now()), 1000)
    return () => window.clearInterval(intervalo)
  }, [])
  if (!torneo.inicio?.por_cupo || !['inscripciones_abiertas', 'inscripciones_cerradas'].includes(torneo.estado.valor)) return null
  const restantes = torneo.inicio.disponible_en ? Math.max(0, Math.ceil((new Date(torneo.inicio.disponible_en).getTime() - ahora) / 1000)) : null
  const cantidad = torneo.inscripcion.equipos_inscritos
  const cupo = torneo.ajustes.cupo_maximo_equipos
  return (
    <section className="estado-inicio" aria-label="Preparación del torneo">
      <div className="fila fila--entre">
        <strong>{restantes === null ? 'Esperando participantes' : restantes > 0 ? 'El torneo está completo' : 'Listo para iniciar'}</strong>
        <span>{cantidad}/{cupo} {torneo.modalidad === 'individual' ? 'jugadores' : 'equipos'}</span>
      </div>
      <progress max={cupo} value={cantidad} aria-label="Cupos confirmados" />
      {restantes === null ? <p>Faltan {Math.max(0, cupo - cantidad)} cupos confirmados. Al llenarse comienza la cuenta regresiva.</p>
        : restantes > 0 ? <p className="cifra">{String(Math.floor(restantes / 60)).padStart(2, '0')}:{String(restantes % 60).padStart(2, '0')} <span className="apagado">para habilitar el inicio</span></p>
          : <p>La cuenta regresiva terminó. Esperando que el administrador inicie el torneo.</p>}
    </section>
  )
}

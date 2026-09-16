import { CalendarX } from 'lucide-react'
import { EstadoVacio } from '../ui/Basicos'
import { TarjetaDePartido } from './TarjetaDePartido'

/**
 * Calendario agrupado por jornada, que es como se lee un torneo de liga.
 */
export function ListaDePartidos({ partidos = [], agruparPorJornada = true, accionPorPartido }) {
  if (partidos.length === 0) {
    return (
      <EstadoVacio icono={<CalendarX size={28} />} titulo="Todavía no hay partidos">
        Cuando la organización genere el calendario, aparecerán aquí todas las fechas.
      </EstadoVacio>
    )
  }

  if (!agruparPorJornada) {
    return (
      <div className="lista-partidos">
        {partidos.map((partido) => (
          <TarjetaDePartido
            key={partido.id}
            partido={partido}
            mostrarTorneo
            accion={accionPorPartido?.(partido)}
          />
        ))}
      </div>
    )
  }

  const jornadas = partidos.reduce((agrupadas, partido) => {
    const clave = partido.jornada

    agrupadas.set(clave, [...(agrupadas.get(clave) ?? []), partido])

    return agrupadas
  }, new Map())

  return (
    <div>
      {[...jornadas.entries()].map(([jornada, partidosDeLaJornada]) => (
        <section key={jornada}>
          <h3 className="jornada-titulo">Jornada {jornada}</h3>
          <div className="lista-partidos">
            {partidosDeLaJornada.map((partido) => (
              <TarjetaDePartido
                key={partido.id}
                partido={partido}
                accion={accionPorPartido?.(partido)}
              />
            ))}
          </div>
        </section>
      ))}
    </div>
  )
}

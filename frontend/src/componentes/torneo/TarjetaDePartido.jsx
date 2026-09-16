import { Link } from 'react-router-dom'
import { fechaYHora } from '../../lib/formato'
import { Escudo } from '../ui/Escudo'
import { Insignia } from '../ui/Basicos'

/**
 * Un partido: los dos escudos, y en el centro el marcador si ya se jugó o la
 * hora si está por jugarse.
 */
export function TarjetaDePartido({ partido, mostrarTorneo = false, accion }) {
  const { equipo_local: local, equipo_visitante: visitante, marcador } = partido

  return (
    <article className="partido">
      <div className="partido__equipo">
        <Escudo equipo={local} tamano="chico" />
        <span>{local?.nombre ?? 'Por definir'}</span>
      </div>

      <div className="partido__centro">
        {marcador ? (
          <span className="partido__marcador">
            {marcador.local} – {marcador.visitante}
          </span>
        ) : (
          <span className="partido__hora">{fechaYHora(partido.programado_para)}</span>
        )}
        <Insignia estado={partido.estado} />
      </div>

      <div className="partido__equipo partido__equipo--visitante">
        <Escudo equipo={visitante} tamano="chico" />
        <span>{visitante?.nombre ?? 'Por definir'}</span>
      </div>

      <div className="partido__pie">
        <span>
          Jornada {partido.jornada}
          {partido.es_vuelta ? ' · vuelta' : ''}
          {partido.sede ? ` · ${partido.sede}` : ''}
          {mostrarTorneo && partido.torneo ? ` · ${partido.torneo.nombre}` : ''}
        </span>
        {accion ?? (
          <Link className="enlace-mas" to={`/partidos/${partido.id}`}>
            Detalle →
          </Link>
        )}
      </div>
    </article>
  )
}

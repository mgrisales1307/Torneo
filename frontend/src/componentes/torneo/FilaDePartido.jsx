import { Link } from 'react-router-dom'
import { fechaYHora } from '../../lib/formato'
import { LogoDeEquipo } from '../equipo/LogoDeEquipo'
import { Insignia } from '../ui/Basicos'

/**
 * Un partido en una lista: los dos equipos y el marcador, o la fecha si
 * todavía no se jugó.
 */
export function FilaDePartido({ partido, mostrarTorneo = false }) {
  const finalizado = partido.estado.valor === 'finalizado'
  const ganaA = finalizado && partido.ganador_id === partido.equipo_a?.id
  const ganaB = finalizado && partido.ganador_id === partido.equipo_b?.id

  return (
    <Link className="partido-fila" to={`/partidos/${partido.id}`}>
      <Lado equipo={partido.equipo_a} ganador={ganaA} perdedor={ganaB} />

      <div className="partido-fila__marcador">
        {finalizado ? (
          <strong>
            {partido.victorias_a ?? 0}–{partido.victorias_b ?? 0}
          </strong>
        ) : (
          <span className="rotulo">vs</span>
        )}
      </div>

      <Lado equipo={partido.equipo_b} ganador={ganaB} perdedor={ganaA} lado="b" />

      <div className="partido-fila__meta">
        <span>
          {mostrarTorneo && partido.torneo ? `${partido.torneo.nombre} · ` : ''}
          {partido.mejor_de > 1 ? `Al mejor de ${partido.mejor_de}` : 'Al mejor de 1'}
        </span>
        {finalizado ? <span>{fechaYHora(partido.finalizado_en)}</span> : <Insignia estado={partido.estado} />}
      </div>
    </Link>
  )
}

function Lado({ equipo, ganador, perdedor, lado = 'a' }) {
  const clases = [
    'partido-fila__lado',
    lado === 'b' ? 'partido-fila__lado--b' : '',
    ganador ? 'partido-fila__lado--ganador' : '',
    perdedor ? 'partido-fila__lado--perdedor' : '',
  ].join(' ')

  return (
    <span className={clases}>
      <LogoDeEquipo equipo={equipo} />
      <strong className="equipo-en-linea">
        <span>{equipo?.nombre ?? 'Por definir'}</span>
      </strong>
    </span>
  )
}

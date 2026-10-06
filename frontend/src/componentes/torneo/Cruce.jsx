import { Link } from 'react-router-dom'
import { fechaCompacta } from '../../lib/formato'
import { LogoDeEquipo } from '../equipo/LogoDeEquipo'

const PIE = {
  pendiente: 'Esperando rival',
  por_jugar: null,
  reportado: 'Esperando confirmación',
  en_disputa: 'En disputa',
  finalizado: 'Terminado',
}

/**
 * Un partido dentro del cuadro: dos renglones, uno por equipo, con su
 * marcador.
 */
export function Cruce({ partido }) {
  const estado = partido.estado.valor
  const finalizado = estado === 'finalizado'

  const clases = [
    'cruce',
    partido.es_final ? 'cruce--final' : '',
    estado === 'reportado' ? 'cruce--atencion' : '',
    estado === 'en_disputa' ? 'cruce--disputa' : '',
  ].join(' ')

  return (
    <Link className={clases} to={`/partidos/${partido.id}`}>
      <Renglon equipo={partido.equipo_a} puntos={partido.victorias_a} finalizado={finalizado} ganador={partido.ganador_id} />
      <Renglon equipo={partido.equipo_b} puntos={partido.victorias_b} finalizado={finalizado} ganador={partido.ganador_id} />
      <div className="cruce__pie">
        <span>{PIE[estado] ?? (partido.programado_para ? fechaCompacta(partido.programado_para) : 'Por jugar')}</span>
        <span>BO{partido.mejor_de}</span>
      </div>
    </Link>
  )
}

function Renglon({ equipo, puntos, finalizado, ganador }) {
  if (!equipo) {
    return (
      <div className="cruce__lado cruce__lado--pendiente">
        <LogoDeEquipo equipo={null} tamano="1.4rem" />
        <span>Por definir</span>
        <span className="cruce__puntos" />
      </div>
    )
  }

  const gano = finalizado && ganador === equipo.id
  const perdio = finalizado && ganador !== equipo.id

  return (
    <div className={`cruce__lado ${gano ? 'cruce__lado--ganador' : ''} ${perdio ? 'cruce__lado--perdedor' : ''}`}>
      <LogoDeEquipo equipo={equipo} tamano="1.4rem" />
      <span>{equipo.nombre}</span>
      <span className="cruce__puntos">{finalizado ? (puntos ?? 0) : ''}</span>
    </div>
  )
}

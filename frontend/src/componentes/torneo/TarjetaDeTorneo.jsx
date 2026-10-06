import { Link } from 'react-router-dom'
import { CalendarDays, Swords, Users } from 'lucide-react'
import { colorDeJuego, juegoDe } from '../../lib/juegos'
import { fechaYHora } from '../../lib/formato'
import { Insignia } from '../ui/Basicos'
import { EtiquetaDeJuego } from '../juego/PortadaDeJuego'

export function TarjetaDeTorneo({ torneo }) {
  const visual = juegoDe(torneo.juego.valor)
  const { equipos_inscritos: inscritos } = torneo.inscripcion
  const cupo = torneo.ajustes.cupo_maximo_equipos
  const llenado = Math.min(100, Math.round((inscritos / Math.max(cupo, 1)) * 100))

  return (
    <Link className="tarjeta-torneo" to={`/torneos/${torneo.slug}`} style={colorDeJuego(torneo.juego.valor)}>
      <div className="tarjeta-torneo__banda" aria-hidden="true">
        {visual.portada && <img src={visual.portada} alt="" loading="lazy" />}
      </div>

      <span className="tarjeta-torneo__estado">
        <Insignia estado={torneo.estado} />
      </span>

      <div className="tarjeta-torneo__cuerpo">
        <EtiquetaDeJuego juego={torneo.juego.valor} />
        <h3>{torneo.nombre}</h3>

        <div className="tarjeta-torneo__datos">
          <span>
            <Swords size={14} aria-hidden="true" /> {torneo.formato.etiqueta}
          </span>
          <span>
            <CalendarDays size={14} aria-hidden="true" /> {fechaYHora(torneo.fechas.inicia_en)}
          </span>
        </div>

        <div className="tarjeta-torneo__pie">
          <div>
            <p className="rotulo">{torneo.campeon ? 'Campeón' : torneo.premios.total_formateado ? 'Premios' : 'Inscripción'}</p>
            <p className="tarjeta-torneo__premio">
              {torneo.campeon?.nombre ?? torneo.premios.total_formateado ?? torneo.inscripcion.costo_formateado}
            </p>
          </div>

          <div className="barra-cupos">
            <span>
              <Users size={12} aria-hidden="true" /> {inscritos}/{cupo}
            </span>
            <div className="barra-cupos__pista" aria-hidden="true">
              <div className="barra-cupos__relleno" style={{ width: `${llenado}%` }} />
            </div>
          </div>
        </div>
      </div>
    </Link>
  )
}

import { Link } from 'react-router-dom'
import { CalendarDays, MapPin, Users } from 'lucide-react'
import { fechaCorta } from '../../lib/formato'
import { Insignia } from '../ui/Basicos'

export function TarjetaDeTorneo({ torneo }) {
  const { inscripcion, reglas } = torneo

  return (
    <article className="torneo-tarjeta">
      <div className="tarjeta__titulo" style={{ marginBottom: 0 }}>
        <h3>
          <Link to={`/torneos/${torneo.slug}`}>{torneo.nombre}</Link>
        </h3>
        <Insignia estado={torneo.estado} />
      </div>

      <div className="torneo-tarjeta__meta">
        {torneo.ciudad && (
          <span>
            <MapPin size={14} aria-hidden="true" /> {torneo.ciudad}
          </span>
        )}
        <span>
          <CalendarDays size={14} aria-hidden="true" /> {fechaCorta(torneo.fechas.inicia_en)}
        </span>
        <span>
          <Users size={14} aria-hidden="true" />
          {inscripcion.equipos_inscritos} / {reglas.cupo_maximo_equipos} equipos
        </span>
      </div>

      <div className="torneo-tarjeta__pie">
        <strong>{inscripcion.costo_formateado}</strong>
        <Link className="enlace-mas" to={`/torneos/${torneo.slug}`}>
          Ver torneo →
        </Link>
      </div>
    </article>
  )
}

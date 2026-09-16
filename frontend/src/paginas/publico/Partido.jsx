import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CalendarX, Goal, RectangleVertical } from 'lucide-react'
import { obtenerPartido } from '../../lib/api'
import { fechaYHora } from '../../lib/formato'
import { Cargando, EstadoVacio, Insignia, Tarjeta } from '../../componentes/ui/Basicos'
import { TarjetaDePartido } from '../../componentes/torneo/TarjetaDePartido'

const ICONOS = {
  gol: Goal,
  gol_de_penal: Goal,
  autogol: Goal,
  tarjeta_amarilla: RectangleVertical,
  tarjeta_roja: RectangleVertical,
}

const COLORES = {
  tarjeta_amarilla: '#e0b100',
  tarjeta_roja: '#a23b2a',
}

export default function Partido() {
  const { id } = useParams()

  const { data, isPending, isError } = useQuery({
    queryKey: ['partido', id],
    queryFn: () => obtenerPartido(id),
  })

  if (isPending) return <Cargando />

  if (isError) {
    return (
      <EstadoVacio
        icono={<CalendarX size={28} />}
        titulo="No encontramos este partido"
        accion={
          <Link className="boton boton--contorno boton--chico" to="/torneos">
            Ver torneos
          </Link>
        }
      />
    )
  }

  const eventos = data.eventos ?? []

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>{data.torneo?.nombre}</h1>
          <p>
            Jornada {data.jornada}
            {data.es_vuelta ? ' · vuelta' : ''} · {fechaYHora(data.programado_para)}
          </p>
        </div>
        <Insignia estado={data.estado} />
      </header>

      <TarjetaDePartido partido={data} accion={<span />} />

      <div className="rejilla rejilla--dos seccion">
        <Tarjeta titulo="Goles y tarjetas">
          {eventos.length === 0 ? (
            <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
              Todavía no hay incidencias registradas para este partido.
            </p>
          ) : (
            <div>
              {eventos.map((evento) => {
                const Icono = ICONOS[evento.tipo.valor] ?? Goal
                const esLocal = evento.equipo_id === data.equipo_local?.id

                return (
                  <div className="evento-fila" key={evento.id}>
                    <span className="evento-fila__minuto">
                      {evento.minuto != null ? `${evento.minuto}'` : '—'}
                    </span>
                    <span>
                      <strong>{evento.jugador?.nombre_completo ?? 'Jugador'}</strong>
                      <span style={{ color: 'var(--apagado)' }}>
                        {' '}
                        · {esLocal ? data.equipo_local?.abreviatura : data.equipo_visitante?.abreviatura}
                      </span>
                    </span>
                    <span
                      style={{ display: 'inline-flex', alignItems: 'center', gap: '.35rem', fontSize: '.74rem' }}
                    >
                      <Icono size={15} color={COLORES[evento.tipo.valor]} aria-hidden="true" />
                      {evento.tipo.etiqueta}
                    </span>
                  </div>
                )
              })}
            </div>
          )}
        </Tarjeta>

        <Tarjeta titulo="Datos del partido">
          <div className="detalle-lista">
            <div>
              <span>Sede</span>
              <strong>{data.sede ?? 'Por definir'}</strong>
            </div>
            <div>
              <span>Árbitro</span>
              <strong>{data.arbitro ?? 'Por designar'}</strong>
            </div>
            <div>
              <span>Estado</span>
              <strong>{data.estado.etiqueta}</strong>
            </div>
            {data.observaciones && (
              <div>
                <span>Observaciones</span>
                <strong>{data.observaciones}</strong>
              </div>
            )}
          </div>
        </Tarjeta>
      </div>
    </>
  )
}

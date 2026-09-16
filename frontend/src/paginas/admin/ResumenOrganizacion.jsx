import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ClipboardCheck, Flag } from 'lucide-react'
import { mensajeDeError, obtenerResumenAdmin } from '../../lib/api'
import { fechaYHora } from '../../lib/formato'
import { Aviso, Cargando, Insignia, Tarjeta } from '../../componentes/ui/Basicos'
import { Escudo } from '../../componentes/ui/Escudo'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

export default function ResumenOrganizacion() {
  const { data, isPending, isError, error } = useQuery({
    queryKey: ['admin', 'resumen'],
    queryFn: obtenerResumenAdmin,
  })

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Organización</h1>
          <p>El estado de la competencia y lo que está esperando por ti.</p>
        </div>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      {isPending && <Cargando />}
      {isError && <Aviso tono="error">{mensajeDeError(error)}</Aviso>}

      {data && (
        <>
          <div className="cifras">
            <div className="cifra">
              <strong>{data.cifras.torneos_activos}</strong>
              <span>Torneos activos</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.equipos}</strong>
              <span>Equipos</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.inscripciones_por_revisar}</strong>
              <span>Por revisar</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.partidos_sin_resultado}</strong>
              <span>Sin resultado</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.recaudado_formateado}</strong>
              <span>Recaudado</span>
            </div>
          </div>

          <div className="rejilla rejilla--dos seccion">
            <Tarjeta
              titulo="Inscripciones por revisar"
              accion={
                <Link className="enlace-mas" to="/organizacion/inscripciones">
                  Ver todas →
                </Link>
              }
            >
              {data.por_revisar.length === 0 ? (
                <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
                  <ClipboardCheck size={15} /> No hay nada pendiente. Todo al día.
                </p>
              ) : (
                <div className="detalle-lista">
                  {data.por_revisar.map((inscripcion) => (
                    <div key={inscripcion.id}>
                      <span>
                        {inscripcion.equipo?.nombre} · {inscripcion.torneo?.nombre}
                      </span>
                      <Insignia estado={inscripcion.estado} />
                    </div>
                  ))}
                </div>
              )}
            </Tarjeta>

            <Tarjeta titulo="Partidos jugados sin resultado">
              {data.partidos_pendientes.length === 0 ? (
                <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
                  <Flag size={15} /> Todos los partidos disputados tienen su marcador cargado.
                </p>
              ) : (
                <div className="detalle-lista">
                  {data.partidos_pendientes.map((partido) => (
                    <div key={partido.id}>
                      <span>
                        <Escudo equipo={partido.equipo_local} tamano="chico" />{' '}
                        {partido.equipo_local?.abreviatura} vs {partido.equipo_visitante?.abreviatura}
                      </span>
                      <Link
                        className="enlace-mas"
                        to={`/organizacion/torneos/${partido.torneo?.slug}?vista=calendario`}
                      >
                        {fechaYHora(partido.programado_para)} →
                      </Link>
                    </div>
                  ))}
                </div>
              )}
            </Tarjeta>
          </div>
        </>
      )}
    </>
  )
}

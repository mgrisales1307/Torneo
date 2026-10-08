import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { mensajeDeError, obtenerResumenAdmin } from '../../lib/api'
import { fechaYHora } from '../../lib/formato'
import { EquipoEnLinea } from '../../componentes/equipo/LogoDeEquipo'
import { Aviso, Cargando, Insignia, Tarjeta } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

export default function ResumenOrganizacion() {
  const resumen = useQuery({ queryKey: ['resumen-admin'], queryFn: obtenerResumenAdmin, refetchInterval: 10000 })

  if (resumen.isPending) return <Cargando />
  if (resumen.isError) return <Aviso tono="error">{mensajeDeError(resumen.error)}</Aviso>

  const { cifras, por_revisar: porRevisar, disputas, por_iniciar: porIniciar } = resumen.data

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Organización</h1>
      </header>

      <NavegacionDelPanel
        enlaces={ENLACES_DE_ORGANIZACION}
        contadores={{
          '/organizacion/inscripciones': cifras.inscripciones_por_revisar,
          '/organizacion/disputas': cifras.disputas,
        }}
      />

      <div className="pila pila--chica">
        <div className="cifras">
          <Link to="/organizacion/torneos">
            <span className="rotulo">Torneos activos</span>
            <strong>{cifras.torneos_activos}</strong>
          </Link>
          <Link to="/organizacion/inscripciones">
            <span className="rotulo">Por revisar</span>
            <strong>{cifras.inscripciones_por_revisar}</strong>
          </Link>
          <Link to="/organizacion/disputas">
            <span className="rotulo">Disputas</span>
            <strong>{cifras.disputas}</strong>
          </Link>
          <div>
            <span className="rotulo">Equipos</span>
            <strong>{cifras.equipos}</strong>
          </div>
          <div>
            <span className="rotulo">Pagos simulados</span>
            <strong style={{ fontSize: '1.4rem' }}>{cifras.recaudado_formateado}</strong>
          </div>
        </div>

        <div className="rejilla rejilla--anchas">
          <Tarjeta titulo="Marcadores por cargar"><div className="lista-detalle">{(resumen.data.por_cargar ?? []).length === 0 ? <p className="apagado">No hay partidos pendientes.</p> : resumen.data.por_cargar.map((partido) => <div key={partido.id}><Link to={`/partidos/${partido.id}`}>{partido.equipo_a ? `${partido.equipo_a.nombre} vs ${partido.equipo_b?.nombre ?? 'Por definir'}` : `Partida ${partido.ronda}`}</Link><span>{partido.torneo.nombre}</span></div>)}</div></Tarjeta>
          <Tarjeta titulo="Disputas">
            {disputas.length === 0 ? (
              <p className="apagado">Ninguna.</p>
            ) : (
              <div className="lista-detalle">
                {disputas.map((partido) => (
                  <div key={partido.id}>
                    <Link to={`/partidos/${partido.id}`}>
                      {partido.equipo_a?.nombre} vs {partido.equipo_b?.nombre}
                    </Link>
                    <span>{partido.torneo?.nombre}</span>
                  </div>
                ))}
              </div>
            )}
          </Tarjeta>

          <Tarjeta titulo="Inscripciones por revisar">
            {porRevisar.length === 0 ? (
              <p className="apagado">Ninguna.</p>
            ) : (
              <div className="lista-detalle">
                {porRevisar.map((inscripcion) => (
                  <div key={inscripcion.id}>
                    <EquipoEnLinea equipo={inscripcion.equipo} enlazar={false} />
                    <Link to={`/organizacion/torneos/${inscripcion.torneo.slug}`}>{inscripcion.torneo.nombre}</Link>
                  </div>
                ))}
              </div>
            )}
          </Tarjeta>

          <Tarjeta titulo="Por iniciar">
            {porIniciar.length === 0 ? (
              <p className="apagado">Ninguno.</p>
            ) : (
              <div className="lista-detalle">
                {porIniciar.map((torneo) => (
                  <div key={torneo.id}>
                    <Link to={`/organizacion/torneos/${torneo.slug}`}>{torneo.nombre}</Link>
                    <span className="fila">
                      {fechaYHora(torneo.fechas.inicia_en)}
                      <Insignia estado={torneo.estado} />
                    </span>
                  </div>
                ))}
              </div>
            )}
          </Tarjeta>
        </div>
      </div>
    </div>
  )
}

import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { esNoEncontrado, mensajeDeError, obtenerEquipo } from '../../lib/api'
import { useSesion } from '../../contexto/contextoDeSesion'
import { iniciales } from '../../lib/formato'
import { colorDeJuego } from '../../lib/juegos'
import { LogoDeEquipo } from '../../componentes/equipo/LogoDeEquipo'
import { EtiquetaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { FilaDePartido } from '../../componentes/torneo/FilaDePartido'
import { TarjetaDeTorneo } from '../../componentes/torneo/TarjetaDeTorneo'
import { Aviso, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import NoEncontrada from './NoEncontrada'

export default function Equipo() {
  const { slug } = useParams()
  const { usuario } = useSesion()
  const consulta = useQuery({ queryKey: ['equipo', slug], queryFn: () => obtenerEquipo(slug) })

  if (consulta.isPending) return <Cargando />
  if (esNoEncontrado(consulta.error)) return <NoEncontrada />
  if (consulta.isError) return <Aviso tono="error">{mensajeDeError(consulta.error)}</Aviso>

  const { data: equipo, torneos, partidos, titulos } = consulta.data
  const soyMiembro = equipo.miembros.some((miembro) => miembro.id === usuario?.id)

  return (
    <div className="pila" style={colorDeJuego(equipo.juego.valor)}>
      <header className="ficha">
        <LogoDeEquipo equipo={equipo} />
        <div>
          <EtiquetaDeJuego juego={equipo.juego.valor} />
          <h1 className="titulo-pagina">{equipo.nombre}</h1>
          <div className="fila">
            <span className="insignia insignia--neutra">{equipo.abreviatura}</span>
            {titulos > 0 && <Insignia tono="acento">{titulos === 1 ? '1 título' : `${titulos} títulos`}</Insignia>}
            {soyMiembro && (
              <Link className="boton boton--contorno boton--chico" to={`/panel/equipos/${equipo.slug}`}>
                Gestionar
              </Link>
            )}
          </div>
        </div>
      </header>

      <div className="dos-columnas">
        <section>
          <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
            Partidos
          </h2>
          {partidos.length === 0 ? (
            <EstadoVacio titulo="Todavía no juega partidos" />
          ) : (
            <div className="pila pila--chica">
              {partidos.map((partido) => (
                <FilaDePartido key={partido.id} partido={partido} mostrarTorneo />
              ))}
            </div>
          )}
        </section>

        <section>
          <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
            Plantilla
          </h2>
          <div className="miembros">
            {equipo.miembros.map((miembro) => (
              <Link className="miembro" key={miembro.id} to={`/jugadores/${miembro.apodo}`}>
                <span className="avatar" aria-hidden="true">
                  {iniciales(miembro.apodo)}
                </span>
                <span className="miembro__datos">
                  <strong>{miembro.apodo}</strong>
                  <span>{miembro.cuenta ?? `Sin ${equipo.juego.cuenta}`}</span>
                </span>
                {miembro.es_capitan && <Insignia tono="acento">Capitán</Insignia>}
                {miembro.es_suplente && <Insignia tono="neutra">Suplente</Insignia>}
              </Link>
            ))}
          </div>
        </section>
      </div>

      {torneos.length > 0 && (
        <section>
          <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
            Torneos
          </h2>
          <div className="rejilla">
            {torneos.map((torneo) => (
              <TarjetaDeTorneo key={torneo.id} torneo={torneo} />
            ))}
          </div>
        </section>
      )}
    </div>
  )
}

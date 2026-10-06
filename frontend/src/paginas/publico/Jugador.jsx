import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { esNoEncontrado, mensajeDeError, obtenerJugador } from '../../lib/api'
import { fechaCorta, iniciales } from '../../lib/formato'
import { colorDeJuego } from '../../lib/juegos'
import { EquipoEnLinea } from '../../componentes/equipo/LogoDeEquipo'
import { EtiquetaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { FilaDePartido } from '../../componentes/torneo/FilaDePartido'
import { Aviso, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import NoEncontrada from './NoEncontrada'

export default function Jugador() {
  const { apodo } = useParams()
  const consulta = useQuery({ queryKey: ['jugador', apodo], queryFn: () => obtenerJugador(apodo) })

  if (consulta.isPending) return <Cargando />
  if (esNoEncontrado(consulta.error)) return <NoEncontrada />
  if (consulta.isError) return <Aviso tono="error">{mensajeDeError(consulta.error)}</Aviso>

  const { data: jugador, partidos, cifras } = consulta.data

  return (
    <div className="pila">
      <header className="ficha">
        <span className="avatar" style={{ width: '5rem', height: '5rem', fontSize: '1.6rem' }} aria-hidden="true">
          {iniciales(jugador.apodo)}
        </span>
        <div>
          <p className="rotulo">Jugador desde {fechaCorta(jugador.desde)}</p>
          <h1 className="titulo-pagina">{jugador.apodo}</h1>
        </div>
      </header>

      <div className="cifras">
        <div>
          <span className="rotulo">Partidos</span>
          <strong>{cifras.partidos}</strong>
        </div>
        <div>
          <span className="rotulo">Victorias</span>
          <strong>{cifras.victorias}</strong>
        </div>
        <div>
          <span className="rotulo">% de victorias</span>
          <strong>{cifras.porcentaje_de_victorias === null ? '—' : `${cifras.porcentaje_de_victorias}%`}</strong>
        </div>
        <div>
          <span className="rotulo">Equipos</span>
          <strong>{jugador.equipos.length}</strong>
        </div>
      </div>

      <div className="dos-columnas">
        <section>
          <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
            Últimos partidos
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

        <div className="pila pila--chica">
          <section>
            <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
              Equipos
            </h2>
            <div className="miembros">
              {jugador.equipos.map((equipo) => (
                <div className="miembro" key={equipo.id} style={colorDeJuego(equipo.juego.valor)}>
                  <span className="miembro__datos">
                    <EquipoEnLinea equipo={equipo} />
                    <EtiquetaDeJuego juego={equipo.juego.valor} />
                  </span>
                  {equipo.es_capitan && <Insignia tono="acento">Capitán</Insignia>}
                </div>
              ))}
            </div>
          </section>

          <section>
            <h2 className="titulo-bloque" style={{ margin: '1.5rem 0 1rem' }}>
              Cuentas
            </h2>
            <div className="lista-detalle">
              {jugador.cuentas.map((cuenta) => (
                <div key={cuenta.juego}>
                  <span>{cuenta.juego_nombre}</span>
                  <strong>{cuenta.identificador}</strong>
                </div>
              ))}
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}

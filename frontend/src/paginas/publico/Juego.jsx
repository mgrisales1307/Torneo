import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { esNoEncontrado, mensajeDeError, obtenerJuego } from '../../lib/api'
import { colorDeJuego, juegoDe } from '../../lib/juegos'
import { TarjetaDeTorneo } from '../../componentes/torneo/TarjetaDeTorneo'
import { TablaDeRanking } from '../../componentes/torneo/TablaDeFase'
import { Aviso, Cargando, EstadoVacio } from '../../componentes/ui/Basicos'
import NoEncontrada from './NoEncontrada'

export default function Juego() {
  const { juego: valor } = useParams()
  const consulta = useQuery({ queryKey: ['juego', valor], queryFn: () => obtenerJuego(valor) })

  if (consulta.isPending) return <Cargando />
  if (esNoEncontrado(consulta.error)) return <NoEncontrada />
  if (consulta.isError) return <Aviso tono="error">{mensajeDeError(consulta.error)}</Aviso>

  const { juego, torneos, ranking } = consulta.data
  const visual = juegoDe(valor)

  return (
    <div className="pila">
      <header className="encabezado-juego" style={colorDeJuego(valor)}>
        {visual.portada && <img className="encabezado-juego__fondo" src={visual.portada} alt="" />}
        <p className="rotulo rotulo--acento">{juego.modalidad}</p>
        <h1 className="titulo-pagina">{juego.nombre}</h1>

        <div className="datos-clave">
          <div>
            <span className="rotulo">Plataforma</span>
            <strong>{juego.plataforma}</strong>
          </div>
          <div>
            <span className="rotulo">Equipo</span>
            <strong>
              {juego.titulares} + {juego.suplentes_maximos} supl.
            </strong>
          </div>
          <div>
            <span className="rotulo">Cuenta</span>
            <strong>{juego.cuenta.nombre}</strong>
          </div>
          <div>
            <span className="rotulo">Formatos</span>
            <strong>{juego.formatos.length}</strong>
          </div>
        </div>
      </header>

      <section aria-labelledby="titulo-torneos">
        <div className="encabezado-seccion">
          <div>
            <p className="rotulo">{juego.nombre}</p>
            <h2 className="titulo-seccion" id="titulo-torneos">
              Torneos
            </h2>
          </div>
        </div>

        {torneos.length === 0 ? (
          <EstadoVacio titulo="Todavía no hay torneos" />
        ) : (
          <div className="rejilla">
            {torneos.map((torneo) => (
              <TarjetaDeTorneo key={torneo.id} torneo={torneo} />
            ))}
          </div>
        )}
      </section>

      <section aria-labelledby="titulo-ranking">
        <div className="encabezado-seccion">
          <div>
            <p className="rotulo">{juego.es_battle_royale ? 'Puntos acumulados' : 'Elo de todas las series'}</p>
            <h2 className="titulo-seccion" id="titulo-ranking">
              Ranking
            </h2>
          </div>
        </div>
        <TablaDeRanking filas={ranking} esBattleRoyale={juego.es_battle_royale} />
      </section>
    </div>
  )
}

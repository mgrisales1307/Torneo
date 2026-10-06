import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight } from 'lucide-react'
import { mensajeDeError, obtenerInicio } from '../../lib/api'
import { useSesion } from '../../contexto/contextoDeSesion'
import { pluralizar } from '../../lib/formato'
import { PortadaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { Bienvenida } from '../../componentes/portada/Bienvenida'
import { TarjetaDeTorneo } from '../../componentes/torneo/TarjetaDeTorneo'
import { FilaDePartido } from '../../componentes/torneo/FilaDePartido'
import { Aviso, Cargando, EstadoVacio } from '../../componentes/ui/Basicos'

export default function Inicio() {
  const { estaAutenticado } = useSesion()
  const inicio = useQuery({ queryKey: ['inicio'], queryFn: obtenerInicio })

  if (inicio.isPending) return <Cargando />

  if (inicio.isError) {
    return (
      <div className="contenedor">
        <Aviso tono="error">{mensajeDeError(inicio.error)}</Aviso>
      </div>
    )
  }

  const { juegos, torneos, ultimos_resultados: resultados } = inicio.data
  const activos = juegos.reduce((total, juego) => total + juego.torneos_activos, 0)

  return (
    <div className="pila">
      <Bienvenida>
        <p className="rotulo rotulo--acento">Valorant · LoL · Dota 2 · PUBG · Free Fire</p>
        <h1 className="titulo-pagina portada__titulo" id="titulo-portada">
          Torneos de <em>esports</em>
        </h1>
        <p className="entradilla">Arma tu equipo, inscríbete y compite.</p>
        <div className="fila">
          <Link className="boton boton--solido" to="/torneos">
            Ver torneos <ArrowRight size={16} aria-hidden="true" />
          </Link>
          <Link className="boton boton--contorno" to={estaAutenticado ? '/panel/equipos' : '/registro'}>
            {estaAutenticado ? 'Mis equipos' : 'Crear cuenta'}
          </Link>
        </div>
        <p className="cifras-portada">
          <span>
            <strong>{activos}</strong> {activos === 1 ? 'torneo activo' : 'torneos activos'}
          </span>
          <span>
            <strong>5</strong> juegos
          </span>
        </p>
      </Bienvenida>

      <section className="contenedor" aria-labelledby="titulo-juegos">
        <div className="encabezado-seccion">
          <div>
            <p className="rotulo">Elige</p>
            <h2 className="titulo-seccion" id="titulo-juegos">
              Juegos
            </h2>
          </div>
        </div>
        <div className="portada__juegos">
          {juegos.map((juego) => (
            <PortadaDeJuego
              key={juego.valor}
              juego={juego.valor}
              detalle={juego.modalidad}
              contador={juego.torneos_activos > 0 ? pluralizar(juego.torneos_activos, 'activo', 'activos') : null}
            />
          ))}
        </div>
      </section>

      <section className="contenedor" aria-labelledby="titulo-torneos">
        <div className="encabezado-seccion">
          <div>
            <p className="rotulo">En juego e inscripciones</p>
            <h2 className="titulo-seccion" id="titulo-torneos">
              Torneos
            </h2>
          </div>
          <Link className="boton boton--contorno boton--chico" to="/torneos">
            Todos los torneos
          </Link>
        </div>

        {torneos.length === 0 ? (
          <EstadoVacio titulo="No hay torneos activos" />
        ) : (
          <div className="rejilla">
            {torneos.map((torneo) => (
              <TarjetaDeTorneo key={torneo.id} torneo={torneo} />
            ))}
          </div>
        )}
      </section>

      {resultados.length > 0 && (
        <section className="contenedor" aria-labelledby="titulo-resultados">
          <div className="encabezado-seccion">
            <div>
              <p className="rotulo">Lo último</p>
              <h2 className="titulo-seccion" id="titulo-resultados">
                Resultados
              </h2>
            </div>
          </div>
          <div className="rejilla rejilla--anchas">
            {resultados.map((partido) => (
              <FilaDePartido key={partido.id} partido={partido} mostrarTorneo />
            ))}
          </div>
        </section>
      )}
    </div>
  )
}

import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowDown, ArrowUpRight, CalendarDays, Trophy } from 'lucide-react'
import { obtenerInicio } from '../../lib/api'
import { Aviso, Cargando } from '../../componentes/ui/Basicos'
import { TarjetaDeTorneo } from '../../componentes/torneo/TarjetaDeTorneo'
import { TarjetaDePartido } from '../../componentes/torneo/TarjetaDePartido'
import { FondoDeEstadio } from '../../componentes/portada/FondoDeEstadio'

export default function Inicio() {
  const { data, isPending, isError } = useQuery({
    queryKey: ['inicio'],
    queryFn: obtenerInicio,
  })

  return (
    <>
      <section className="portada">
        <FondoDeEstadio />

        <div className="portada__texto">
          <p className="antetitulo-claro">
            <span aria-hidden="true" /> El fútbol se vive aquí
          </p>
          <h1>
            Cada partido.
            <br />
            <em>Cada historia.</em>
          </h1>
          <p>
            Sigue la tabla, el calendario y los goleadores de cada torneo en tiempo real. Y si tienes
            equipo, inscríbelo, arma tu plantilla y sal a competir.
          </p>

          <div className="portada__acciones">
            <Link className="boton boton--acento" to="/torneos">
              <Trophy size={16} /> Ver torneos
            </Link>
            <Link className="boton boton--claro" to="/registro">
              Inscribir mi equipo <ArrowUpRight size={16} />
            </Link>
          </div>
        </div>

        <button
          className="portada__bajar"
          type="button"
          onClick={() =>
            document.getElementById('lo-que-pasa')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
          }
        >
          Ver la competencia <ArrowDown size={14} aria-hidden="true" />
        </button>
      </section>

      {isError && (
        <div className="seccion">
          <Aviso tono="error">
            No pudimos cargar la información del torneo. Revisa que la API esté encendida y recarga la
            página.
          </Aviso>
        </div>
      )}

      {isPending && <Cargando texto="Cargando el torneo…" />}

      {data && (
        <>
          <div className="cifras" id="lo-que-pasa">
            <div className="cifra">
              <strong>{data.cifras.torneos}</strong>
              <span>Torneos</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.equipos}</strong>
              <span>Equipos</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.jugadores}</strong>
              <span>Jugadores</span>
            </div>
            <div className="cifra">
              <strong>{data.cifras.partidos_jugados}</strong>
              <span>Partidos jugados</span>
            </div>
          </div>

          {data.torneos.length > 0 && (
            <section className="seccion">
              <header className="seccion__encabezado">
                <div>
                  <h2>Torneos en juego</h2>
                  <p>Lo que está pasando ahora mismo en Juegos Zone.</p>
                </div>
                <Link className="enlace-mas" to="/torneos">
                  Ver todos →
                </Link>
              </header>

              <div className="rejilla rejilla--tres">
                {data.torneos.map((torneo) => (
                  <TarjetaDeTorneo key={torneo.id} torneo={torneo} />
                ))}
              </div>
            </section>
          )}

          <div className="rejilla rejilla--dos seccion">
            <section>
              <header className="seccion__encabezado">
                <div>
                  <h2>Próximos partidos</h2>
                </div>
              </header>

              {data.proximos_partidos.length === 0 ? (
                <p className="aviso aviso--info">
                  <CalendarDays size={16} aria-hidden="true" />
                  <span>No hay partidos programados por ahora.</span>
                </p>
              ) : (
                <div className="lista-partidos">
                  {data.proximos_partidos.map((partido) => (
                    <TarjetaDePartido key={partido.id} partido={partido} mostrarTorneo />
                  ))}
                </div>
              )}
            </section>

            <section>
              <header className="seccion__encabezado">
                <div>
                  <h2>Últimos resultados</h2>
                </div>
              </header>

              {data.ultimos_resultados.length === 0 ? (
                <p className="aviso aviso--info">
                  <CalendarDays size={16} aria-hidden="true" />
                  <span>Todavía no se ha jugado ningún partido.</span>
                </p>
              ) : (
                <div className="lista-partidos">
                  {data.ultimos_resultados.map((partido) => (
                    <TarjetaDePartido key={partido.id} partido={partido} mostrarTorneo />
                  ))}
                </div>
              )}
            </section>
          </div>
        </>
      )}
    </>
  )
}

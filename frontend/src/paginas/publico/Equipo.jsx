import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { MapPin, ShieldQuestion, Users } from 'lucide-react'
import { obtenerEquipo } from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import { Cargando, EstadoVacio, Insignia, Tarjeta } from '../../componentes/ui/Basicos'
import { Escudo } from '../../componentes/ui/Escudo'
import { ListaDePartidos } from '../../componentes/torneo/ListaDePartidos'

export default function Equipo() {
  const { slug } = useParams()

  const { data, isPending, isError } = useQuery({
    queryKey: ['equipo', slug],
    queryFn: () => obtenerEquipo(slug),
  })

  if (isPending) return <Cargando texto="Cargando el equipo…" />

  if (isError) {
    return (
      <EstadoVacio
        icono={<ShieldQuestion size={28} />}
        titulo="No encontramos este equipo"
        accion={
          <Link className="boton boton--contorno boton--chico" to="/torneos">
            Ver torneos
          </Link>
        }
      >
        Puede que el enlace esté mal escrito o que el equipo ya no exista.
      </EstadoVacio>
    )
  }

  const equipo = data.data
  const jugadores = equipo.jugadores ?? []

  return (
    <>
      <header
        className="torneo-cabecera"
        style={{ background: `linear-gradient(120deg, ${equipo.colores.primario} 0%, var(--tinta) 78%)` }}
      >
        <div style={{ display: 'flex', gap: '1.35rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <Escudo equipo={{ ...equipo, escudo_url: equipo.imagenes.escudo_url }} tamano="enorme" />
          <div>
            <h1>{equipo.nombre}</h1>
            <div className="torneo-cabecera__datos" style={{ marginTop: '.85rem' }}>
              {equipo.ciudad && (
                <span>
                  <MapPin size={15} aria-hidden="true" /> {equipo.ciudad}
                </span>
              )}
              <span>
                <Users size={15} aria-hidden="true" /> {jugadores.length} jugadores
              </span>
              {equipo.fundado_en && <span>Desde {fechaCorta(equipo.fundado_en)}</span>}
            </div>
          </div>
        </div>
      </header>

      <div className="rejilla rejilla--panel seccion">
        <section>
          <header className="seccion__encabezado">
            <div>
              <h2>Plantilla</h2>
              <p>Los jugadores inscritos por el equipo.</p>
            </div>
          </header>

          {jugadores.length === 0 ? (
            <EstadoVacio icono={<Users size={26} />} titulo="Sin jugadores todavía">
              Este equipo aún no ha cargado su plantilla.
            </EstadoVacio>
          ) : (
            <div className="tabla-envoltura">
              <table className="tabla">
                <thead>
                  <tr>
                    <th scope="col" className="numerico">
                      #
                    </th>
                    <th scope="col">Jugador</th>
                    <th scope="col">Posición</th>
                    <th scope="col">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  {jugadores.map((jugador) => (
                    <tr key={jugador.id}>
                      <td className="numerico">{jugador.numero_camiseta ?? '—'}</td>
                      <td>
                        <strong>{jugador.nombre_completo}</strong>
                        {jugador.es_capitan && (
                          <span style={{ marginLeft: '.5rem' }}>
                            <Insignia tono="acento">Capitán</Insignia>
                          </span>
                        )}
                      </td>
                      <td>{jugador.posicion.etiqueta}</td>
                      <td>
                        <Insignia estado={jugador.estado} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <aside style={{ display: 'grid', gap: '1.1rem' }}>
          <Tarjeta titulo="Torneos">
            {(equipo.inscripciones ?? []).length === 0 ? (
              <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
                El equipo todavía no compite en ningún torneo.
              </p>
            ) : (
              <div className="detalle-lista">
                {equipo.inscripciones.map((inscripcion) => (
                  <div key={inscripcion.id}>
                    <Link to={`/torneos/${inscripcion.torneo.slug}`}>{inscripcion.torneo.nombre}</Link>
                    <Insignia estado={inscripcion.estado} />
                  </div>
                ))}
              </div>
            )}
          </Tarjeta>

          <Tarjeta titulo="Uniformes">
            <div className="uniformes">
              <ImagenDeUniforme titulo="Local" url={equipo.imagenes.uniforme_local_url} />
              <ImagenDeUniforme titulo="Visitante" url={equipo.imagenes.uniforme_visitante_url} />
            </div>
          </Tarjeta>
        </aside>
      </div>

      <section className="seccion">
        <header className="seccion__encabezado">
          <div>
            <h2>Partidos</h2>
          </div>
        </header>
        <ListaDePartidos partidos={data.partidos ?? []} agruparPorJornada={false} />
      </section>
    </>
  )
}

function ImagenDeUniforme({ titulo, url }) {
  return (
    <figure style={{ margin: 0, display: 'grid', gap: '.5rem', justifyItems: 'center' }}>
      <div className="subida-imagen__previa">
        {url ? <img src={url} alt={`Uniforme ${titulo.toLowerCase()}`} loading="lazy" /> : <span>—</span>}
      </div>
      <figcaption style={{ color: 'var(--apagado)', fontSize: '.72rem' }}>{titulo}</figcaption>
    </figure>
  )
}

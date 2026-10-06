import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { listarPartidosAdmin, mensajeDeError, resolverPartido } from '../../lib/api'
import { fechaYHora } from '../../lib/formato'
import { Aviso, Boton, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

/**
 * Partidos que esperan a la organización: las disputas primero, y luego los
 * reportados que el rival no ha confirmado. Cada reporte se valida con un clic.
 */
export default function DisputasAdmin() {
  const cliente = useQueryClient()
  const [aviso, setAviso] = useState(null)

  const partidos = useQuery({ queryKey: ['disputas'], queryFn: () => listarPartidosAdmin() })

  const validar = useMutation({
    mutationFn: ({ id, victorias_a, victorias_b }) => resolverPartido(id, { victorias_a, victorias_b }),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['disputas'] })
      cliente.invalidateQueries({ queryKey: ['resumen-admin'] })
      setAviso({ tono: 'exito', texto: respuesta.message })
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Disputas</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      {aviso && (
        <div style={{ marginBottom: '1.25rem' }}>
          <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>
        </div>
      )}

      {partidos.isPending ? (
        <Cargando />
      ) : partidos.isError ? (
        <Aviso tono="error">{mensajeDeError(partidos.error)}</Aviso>
      ) : partidos.data.data.length === 0 ? (
        <EstadoVacio titulo="Nada por resolver" />
      ) : (
        <div className="pila pila--chica">
          {partidos.data.data.map((partido) => (
            <section className="tarjeta" key={partido.id}>
              <div className="tarjeta__titulo">
                <div>
                  <p className="rotulo">{partido.torneo.nombre}</p>
                  <h2>
                    <Link to={`/partidos/${partido.id}`}>
                      {partido.equipo_a.nombre} vs {partido.equipo_b.nombre}
                    </Link>
                  </h2>
                </div>
                <Insignia estado={partido.estado} />
              </div>

              <div className="reportes">
                {partido.reportes.map((reporte) => (
                  <article className="reporte" key={reporte.equipo_id}>
                    <div className="fila fila--entre">
                      <strong>{reporte.equipo}</strong>
                      <strong className="cifra" style={{ fontSize: '1.3rem' }}>
                        {reporte.victorias_a}–{reporte.victorias_b}
                      </strong>
                    </div>
                    <span className="apagado" style={{ fontSize: '.82rem' }}>
                      {reporte.reportado_por} · {fechaYHora(reporte.reportado_en)}
                    </span>
                    {reporte.comentario && <p>{reporte.comentario}</p>}
                    {reporte.captura_url ? (
                      <a className="reporte__captura" href={reporte.captura_url} target="_blank" rel="noreferrer">
                        <img src={reporte.captura_url} alt={`Captura de ${reporte.equipo}`} />
                      </a>
                    ) : (
                      <span className="apagado">Sin captura</span>
                    )}
                    <Boton
                      tamano="chico"
                      cargando={validar.isPending}
                      onClick={() =>
                        validar.mutate({ id: partido.id, victorias_a: reporte.victorias_a, victorias_b: reporte.victorias_b })
                      }
                    >
                      Validar {reporte.victorias_a}–{reporte.victorias_b}
                    </Boton>
                  </article>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  )
}

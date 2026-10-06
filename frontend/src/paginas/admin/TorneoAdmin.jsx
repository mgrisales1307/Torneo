import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, Play } from 'lucide-react'
import {
  actualizarTorneo,
  cambiarEstadoDelTorneo,
  eliminarTorneo,
  erroresPorCampo,
  iniciarTorneo,
  listarInscripcionesAdmin,
  mensajeDeError,
  obtenerCuadro,
  obtenerTorneoAdmin,
} from '../../lib/api'
import { useSesion } from '../../contexto/contextoDeSesion'
import { fechaYHora } from '../../lib/formato'
import { FormularioDeTorneo } from '../../componentes/torneo/FormularioDeTorneo'
import { FilaDePartido } from '../../componentes/torneo/FilaDePartido'
import { EtiquetaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { Aviso, Boton, Cargando, EstadoVacio, Insignia, Pestanas } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'
import { ListaDeInscripciones } from './ListaDeInscripciones'

const ACCIONES_DE_ESTADO = {
  inscripciones_abiertas: { texto: 'Abrir inscripciones', variante: 'solido' },
  inscripciones_cerradas: { texto: 'Cerrar inscripciones', variante: 'contorno' },
  borrador: { texto: 'Volver a borrador', variante: 'fantasma' },
  cancelado: { texto: 'Cancelar torneo', variante: 'peligro' },
}

export default function TorneoAdmin() {
  const { slug } = useParams()
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const { esAdministrador } = useSesion()
  const [pestana, setPestana] = useState('inscripciones')
  const [aviso, setAviso] = useState(null)
  const [errores, setErrores] = useState({})

  const torneo = useQuery({ queryKey: ['torneo-admin', slug], queryFn: () => obtenerTorneoAdmin(slug) })

  function refrescar(respuesta) {
    cliente.invalidateQueries({ queryKey: ['torneo-admin', slug] })
    cliente.invalidateQueries({ queryKey: ['torneos-admin'] })
    cliente.invalidateQueries({ queryKey: ['cuadro', slug] })
    cliente.invalidateQueries({ queryKey: ['torneo', slug] })
    setAviso({ tono: 'exito', texto: respuesta.message })
  }

  const alFallar = (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) })

  const estado = useMutation({ mutationFn: (nuevo) => cambiarEstadoDelTorneo(slug, nuevo), onSuccess: refrescar, onError: alFallar })
  const iniciar = useMutation({
    mutationFn: () => iniciarTorneo(slug),
    onSuccess: (respuesta) => {
      refrescar(respuesta)
      setPestana('partidos')
    },
    onError: alFallar,
  })
  const guardar = useMutation({
    mutationFn: (datos) => actualizarTorneo(slug, datos),
    onSuccess: (respuesta) => {
      setErrores({})
      refrescar(respuesta)
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      alFallar(fallo)
    },
  })
  const eliminar = useMutation({
    mutationFn: () => eliminarTorneo(slug),
    onSuccess: () => navegar('/organizacion/torneos'),
    onError: alFallar,
  })

  if (torneo.isPending) return <Cargando />
  if (torneo.isError) return <Aviso tono="error">{mensajeDeError(torneo.error)}</Aviso>

  const { data: datos, transiciones } = torneo.data
  const esperaInicio = ['inscripciones_abiertas', 'inscripciones_cerradas'].includes(datos.estado.valor)
  const empezo = ['en_curso', 'finalizado'].includes(datos.estado.valor)

  return (
    <div>
      <header className="encabezado-pagina">
        <div className="fila">
          <EtiquetaDeJuego juego={datos.juego.valor} />
          <Insignia estado={datos.estado} />
        </div>
        <h1 className="titulo-pagina">{datos.nombre}</h1>
        <p className="apagado">
          {datos.formato.etiqueta} · {datos.inscripcion.equipos_inscritos}/{datos.ajustes.cupo_maximo_equipos} equipos · inicia{' '}
          {fechaYHora(datos.fechas.inicia_en)}
        </p>

        <div className="fila">
          {esperaInicio && (
            <Boton
              iconoIzquierda={<Play size={15} />}
              cargando={iniciar.isPending}
              onClick={() => {
                if (window.confirm('Se arma el cuadro con los equipos aprobados (y con check-in). ¿Iniciar?')) iniciar.mutate()
              }}
            >
              Iniciar torneo
            </Boton>
          )}
          {transiciones.map((destino) => (
            <Boton
              key={destino}
              variante={ACCIONES_DE_ESTADO[destino]?.variante ?? 'contorno'}
              cargando={estado.isPending && estado.variables === destino}
              onClick={() => {
                if (destino !== 'cancelado' || window.confirm('¿Cancelar el torneo? No se puede deshacer.')) estado.mutate(destino)
              }}
            >
              {destino === 'inscripciones_abiertas' && datos.estado.valor === 'inscripciones_cerradas'
                ? 'Reabrir inscripciones'
                : (ACCIONES_DE_ESTADO[destino]?.texto ?? destino)}
            </Boton>
          ))}
          <Link className="boton boton--fantasma" to={`/torneos/${slug}`}>
            <ExternalLink size={15} aria-hidden="true" /> Página pública
          </Link>
        </div>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      {aviso && (
        <div style={{ marginBottom: '1.25rem' }}>
          <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>
        </div>
      )}

      <Pestanas
        opciones={[
          { valor: 'inscripciones', etiqueta: 'Inscripciones' },
          ...(empezo ? [{ valor: 'partidos', etiqueta: 'Partidos' }] : []),
          { valor: 'editar', etiqueta: 'Editar' },
        ]}
        activa={pestana}
        alCambiar={setPestana}
      />

      {pestana === 'inscripciones' && <InscripcionesDelTorneo torneoId={datos.id} />}
      {pestana === 'partidos' && <PartidosDelTorneo slug={slug} />}
      {pestana === 'editar' && (
        <div className="pila pila--chica" style={{ maxWidth: '48rem' }}>
          <FormularioDeTorneo
            key={datos.id}
            torneo={datos}
            errores={errores}
            cargando={guardar.isPending}
            alGuardar={(cambios) => guardar.mutate(cambios)}
          />
          {esAdministrador && !empezo && (
            <div>
              <Boton
                variante="peligro"
                tamano="chico"
                cargando={eliminar.isPending}
                onClick={() => {
                  if (window.confirm(`¿Eliminar ${datos.nombre}?`)) eliminar.mutate()
                }}
              >
                Eliminar torneo
              </Boton>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function InscripcionesDelTorneo({ torneoId }) {
  const inscripciones = useQuery({
    queryKey: ['inscripciones-admin', 'torneo', torneoId],
    queryFn: () => listarInscripcionesAdmin({ torneo: torneoId }),
  })

  if (inscripciones.isPending) return <Cargando />
  if (inscripciones.isError) return <Aviso tono="error">{mensajeDeError(inscripciones.error)}</Aviso>

  return <ListaDeInscripciones inscripciones={inscripciones.data.data} mostrarTorneo={false} />
}

const PRIORIDAD = { en_disputa: 0, reportado: 1, por_jugar: 2, pendiente: 3, finalizado: 4 }

/**
 * Todos los partidos del torneo, con lo que espera acción primero. Cada uno
 * se abre en su página, donde la organización carga o corrige el resultado.
 */
function PartidosDelTorneo({ slug }) {
  const cuadro = useQuery({ queryKey: ['cuadro', slug], queryFn: () => obtenerCuadro(slug) })

  if (cuadro.isPending) return <Cargando />
  if (cuadro.isError) return <Aviso tono="error">{mensajeDeError(cuadro.error)}</Aviso>

  const partidos = cuadro.data.data
    .flatMap((fase) => fase.partidos)
    .filter((partido) => !partido.es_descanso)
    .sort((uno, otro) => PRIORIDAD[uno.estado.valor] - PRIORIDAD[otro.estado.valor] || uno.id - otro.id)

  if (partidos.length === 0) return <EstadoVacio titulo="Sin partidos" />

  return (
    <div className="rejilla rejilla--anchas">
      {partidos.map((partido) =>
        partido.equipo_a || partido.equipo_b ? (
          <FilaDePartido key={partido.id} partido={partido} />
        ) : (
          <Link className="accion-pendiente" key={partido.id} to={`/partidos/${partido.id}`}>
            <div>
              <strong>Partida {partido.ronda}</strong>
              <span className="apagado">{fechaYHora(partido.programado_para)}</span>
            </div>
            <Insignia estado={partido.estado} />
          </Link>
        ),
      )}
    </div>
  )
}

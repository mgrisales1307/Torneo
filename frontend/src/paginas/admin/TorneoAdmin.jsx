import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CalendarCog, ClipboardList } from 'lucide-react'
import {
  actualizarTorneo,
  erroresPorCampo,
  generarCalendario,
  mensajeDeError,
  obtenerPartidosDelTorneo,
  obtenerTorneoAdmin,
} from '../../lib/api'
import { aCentavos, aPesos, paraInputDeFecha } from '../../lib/formato'
import {
  Aviso,
  Boton,
  Campo,
  CampoDeSeleccion,
  CampoDeTexto,
  Cargando,
  Insignia,
  Tarjeta,
} from '../../componentes/ui/Basicos'
import { ListaDePartidos } from '../../componentes/torneo/ListaDePartidos'
import { DialogoDeResultado } from './DialogoDeResultado'

const ESTADOS = [
  { valor: 'borrador', etiqueta: 'Borrador (no visible al público)' },
  { valor: 'inscripciones_abiertas', etiqueta: 'Inscripciones abiertas' },
  { valor: 'inscripciones_cerradas', etiqueta: 'Inscripciones cerradas' },
  { valor: 'en_curso', etiqueta: 'En curso' },
  { valor: 'finalizado', etiqueta: 'Finalizado' },
  { valor: 'cancelado', etiqueta: 'Cancelado' },
]

const VISTAS = [
  { clave: 'ajustes', etiqueta: 'Ajustes' },
  { clave: 'calendario', etiqueta: 'Calendario y resultados' },
]

export default function TorneoAdmin() {
  const { slug } = useParams()
  const [parametros, setParametros] = useSearchParams()
  const vista = VISTAS.some((v) => v.clave === parametros.get('vista')) ? parametros.get('vista') : 'ajustes'

  const torneo = useQuery({ queryKey: ['admin', 'torneo', slug], queryFn: () => obtenerTorneoAdmin(slug) })

  if (torneo.isPending) return <Cargando />
  if (torneo.isError) return <Aviso tono="error">{mensajeDeError(torneo.error)}</Aviso>

  return (
    <>
      <Link
        className="enlace-mas"
        to="/organizacion/torneos"
        style={{ marginBottom: '1rem', display: 'inline-flex' }}
      >
        <ArrowLeft size={15} /> Volver a los torneos
      </Link>

      <header className="panel-cabecera">
        <div>
          <h1>{torneo.data.nombre}</h1>
          <p>
            {torneo.data.inscripcion.equipos_inscritos} de {torneo.data.reglas.cupo_maximo_equipos} equipos ·{' '}
            <Link to={`/torneos/${torneo.data.slug}`}>ver página pública</Link>
          </p>
        </div>
        <Insignia estado={torneo.data.estado} />
      </header>

      <nav className="pestanas" aria-label="Secciones del torneo">
        {VISTAS.map((opcion) => (
          <button
            key={opcion.clave}
            className={`pestana ${vista === opcion.clave ? 'pestana--activa' : ''}`}
            type="button"
            onClick={() => setParametros({ vista: opcion.clave })}
          >
            {opcion.etiqueta}
          </button>
        ))}
      </nav>

      <div style={{ marginTop: '1.75rem' }}>
        {vista === 'ajustes' ? (
          <AjustesDelTorneo torneo={torneo.data} key={torneo.data.id} />
        ) : (
          <CalendarioDelTorneo torneo={torneo.data} />
        )}
      </div>
    </>
  )
}

/**
 * Construye el formulario a partir del torneo. Es una función aparte para que
 * el estado pueda nacer ya lleno, sin un efecto que lo rellene después.
 */
function formularioDesde(torneo) {
  return {
    nombre: torneo.nombre,
    temporada: torneo.temporada ?? '',
    categoria: torneo.categoria ?? '',
    descripcion: torneo.descripcion ?? '',
    estado: torneo.estado.valor,
    ciudad: torneo.ciudad ?? '',
    sede: torneo.sede ?? '',
    cupo_maximo_equipos: torneo.reglas.cupo_maximo_equipos,
    minimo_jugadores_por_equipo: torneo.reglas.minimo_jugadores_por_equipo,
    maximo_jugadores_por_equipo: torneo.reglas.maximo_jugadores_por_equipo,
    puntos_por_victoria: torneo.reglas.puntos_por_victoria,
    puntos_por_empate: torneo.reglas.puntos_por_empate,
    costo: aPesos(torneo.inscripcion.costo_centavos),
    inscripciones_cierran_en: paraInputDeFecha(torneo.inscripcion.cierran_en),
    inicia_en: paraInputDeFecha(torneo.fechas.inicia_en),
  }
}

function AjustesDelTorneo({ torneo }) {
  const cliente = useQueryClient()
  const [formulario, setFormulario] = useState(() => formularioDesde(torneo))
  const [errores, setErrores] = useState({})
  const [aviso, setAviso] = useState('')
  const [error, setError] = useState('')

  const guardar = useMutation({
    mutationFn: (datos) => actualizarTorneo(torneo.slug, datos),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['admin'] })
      cliente.invalidateQueries({ queryKey: ['torneo', torneo.slug] })
      setAviso('Torneo actualizado.')
      setErrores({})
      setError('')
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo))
    },
  })

  function alCambiar(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: value }))
    setErrores((actuales) => ({ ...actuales, [name]: undefined }))
    setAviso('')
  }

  return (
    <Tarjeta titulo="Ajustes del torneo">
      {aviso && <Aviso tono="exito">{aviso}</Aviso>}
      {error && <Aviso tono="error">{error}</Aviso>}

      <form
        style={{ display: 'grid', gap: '1.1rem', marginTop: '1rem' }}
        onSubmit={(evento) => {
          evento.preventDefault()

          const { costo, inscripciones_cierran_en, inicia_en, ...resto } = formulario

          guardar.mutate({
            ...resto,
            cupo_maximo_equipos: Number(resto.cupo_maximo_equipos),
            minimo_jugadores_por_equipo: Number(resto.minimo_jugadores_por_equipo),
            maximo_jugadores_por_equipo: Number(resto.maximo_jugadores_por_equipo),
            puntos_por_victoria: Number(resto.puntos_por_victoria),
            puntos_por_empate: Number(resto.puntos_por_empate),
            costo_inscripcion_centavos: aCentavos(costo),
            inscripciones_cierran_en: inscripciones_cierran_en || null,
            inicia_en: inicia_en || null,
          })
        }}
      >
        <div className="rejilla-campos">
          <Campo
            etiqueta="Nombre"
            name="nombre"
            value={formulario.nombre}
            onChange={alCambiar}
            error={errores.nombre}
          />
          <CampoDeSeleccion
            etiqueta="Estado"
            name="estado"
            opciones={ESTADOS}
            value={formulario.estado}
            onChange={alCambiar}
            error={errores.estado}
          />
        </div>

        <div className="rejilla-campos">
          <Campo etiqueta="Temporada" name="temporada" value={formulario.temporada} onChange={alCambiar} />
          <Campo etiqueta="Categoría" name="categoria" value={formulario.categoria} onChange={alCambiar} />
          <Campo etiqueta="Ciudad" name="ciudad" value={formulario.ciudad} onChange={alCambiar} />
          <Campo etiqueta="Sede" name="sede" value={formulario.sede} onChange={alCambiar} />
        </div>

        <CampoDeTexto
          etiqueta="Descripción"
          name="descripcion"
          value={formulario.descripcion}
          onChange={alCambiar}
          error={errores.descripcion}
        />

        <div className="rejilla-campos">
          <Campo
            etiqueta="Cupo de equipos"
            name="cupo_maximo_equipos"
            type="number"
            value={formulario.cupo_maximo_equipos}
            onChange={alCambiar}
            error={errores.cupo_maximo_equipos}
          />
          <Campo
            etiqueta="Mínimo de jugadores"
            name="minimo_jugadores_por_equipo"
            type="number"
            value={formulario.minimo_jugadores_por_equipo}
            onChange={alCambiar}
            error={errores.minimo_jugadores_por_equipo}
          />
          <Campo
            etiqueta="Máximo de jugadores"
            name="maximo_jugadores_por_equipo"
            type="number"
            value={formulario.maximo_jugadores_por_equipo}
            onChange={alCambiar}
            error={errores.maximo_jugadores_por_equipo}
          />
        </div>

        <div className="rejilla-campos">
          <Campo
            etiqueta="Puntos por victoria"
            name="puntos_por_victoria"
            type="number"
            min={0}
            max={10}
            value={formulario.puntos_por_victoria}
            onChange={alCambiar}
            error={errores.puntos_por_victoria}
          />
          <Campo
            etiqueta="Puntos por empate"
            name="puntos_por_empate"
            type="number"
            min={0}
            max={10}
            value={formulario.puntos_por_empate}
            onChange={alCambiar}
            error={errores.puntos_por_empate}
          />
          <Campo
            etiqueta="Costo de inscripción"
            name="costo"
            type="number"
            min={0}
            step={1000}
            value={formulario.costo}
            onChange={alCambiar}
            error={errores.costo_inscripcion_centavos}
            pista="En pesos."
          />
        </div>

        <div className="rejilla-campos">
          <Campo
            etiqueta="Cierre de inscripciones"
            name="inscripciones_cierran_en"
            type="datetime-local"
            value={formulario.inscripciones_cierran_en}
            onChange={alCambiar}
            error={errores.inscripciones_cierran_en}
          />
          <Campo
            etiqueta="Primera fecha"
            name="inicia_en"
            type="datetime-local"
            value={formulario.inicia_en}
            onChange={alCambiar}
            error={errores.inicia_en}
          />
        </div>

        <div className="barra-acciones">
          <Boton type="submit" cargando={guardar.isPending}>
            Guardar cambios
          </Boton>
        </div>
      </form>
    </Tarjeta>
  )
}

function CalendarioDelTorneo({ torneo }) {
  const cliente = useQueryClient()
  const [partidoEnCarga, setPartidoEnCarga] = useState(null)
  const [aviso, setAviso] = useState('')
  const [error, setError] = useState('')

  const partidos = useQuery({
    queryKey: ['torneo', torneo.slug, 'partidos'],
    queryFn: () => obtenerPartidosDelTorneo(torneo.slug),
  })

  const generar = useMutation({
    mutationFn: (reiniciar) => generarCalendario(torneo.slug, reiniciar),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['torneo', torneo.slug] })
      cliente.invalidateQueries({ queryKey: ['admin'] })
      setAviso(respuesta.message)
      setError('')
    },
    onError: (fallo) => {
      setAviso('')
      setError(mensajeDeError(fallo))
    },
  })

  const hayCalendario = (partidos.data?.data.length ?? 0) > 0

  return (
    <>
      <Tarjeta
        titulo="Calendario"
        accion={
          <div className="barra-acciones">
            <Boton
              iconoIzquierda={<CalendarCog size={15} />}
              cargando={generar.isPending}
              onClick={() => {
                if (
                  !hayCalendario ||
                  window.confirm('Esto reemplaza el calendario actual. ¿Seguro que quieres seguir?')
                ) {
                  generar.mutate(hayCalendario)
                }
              }}
            >
              {hayCalendario ? 'Regenerar calendario' : 'Generar calendario'}
            </Boton>
          </div>
        }
      >
        <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
          El calendario enfrenta a todos los equipos con la inscripción aprobada
          {torneo.formato.valor === 'liga_ida_vuelta' ? ', ida y vuelta' : ''}. La primera jornada se
          programa en la fecha de inicio del torneo y las siguientes, una por semana.
        </p>

        {aviso && (
          <div style={{ marginTop: '1rem' }}>
            <Aviso tono="exito">{aviso}</Aviso>
          </div>
        )}
        {error && (
          <div style={{ marginTop: '1rem' }}>
            <Aviso tono="error">{error}</Aviso>
          </div>
        )}
      </Tarjeta>

      <div style={{ marginTop: '1.5rem' }}>
        {partidos.isPending && <Cargando />}

        {partidos.data && (
          <ListaDePartidos
            partidos={partidos.data.data}
            accionPorPartido={(partido) => (
              <Boton
                variante="fantasma"
                tamano="chico"
                iconoIzquierda={<ClipboardList size={14} />}
                onClick={() => setPartidoEnCarga(partido)}
              >
                {partido.marcador ? 'Corregir resultado' : 'Cargar resultado'}
              </Boton>
            )}
          />
        )}
      </div>

      {partidoEnCarga && (
        <DialogoDeResultado partido={partidoEnCarga} alCerrar={() => setPartidoEnCarga(null)} />
      )}
    </>
  )
}

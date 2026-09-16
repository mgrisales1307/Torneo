import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CalendarDays, Coins, MapPin, ShieldCheck, Users } from 'lucide-react'
import {
  obtenerEquiposDelTorneo,
  obtenerGoleadores,
  obtenerPartidosDelTorneo,
  obtenerTabla,
  obtenerTorneo,
} from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import { Aviso, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { Escudo } from '../../componentes/ui/Escudo'
import { TablaDePosiciones } from '../../componentes/torneo/TablaDePosiciones'
import { ListaDePartidos } from '../../componentes/torneo/ListaDePartidos'
import { ListaDeGoleadores } from '../../componentes/torneo/ListaDeGoleadores'

const VISTAS = [
  { clave: 'tabla', etiqueta: 'Tabla' },
  { clave: 'calendario', etiqueta: 'Calendario' },
  { clave: 'goleadores', etiqueta: 'Goleadores' },
  { clave: 'equipos', etiqueta: 'Equipos' },
]

export default function Torneo() {
  const { slug } = useParams()
  const [parametros, setParametros] = useSearchParams()
  const vista = VISTAS.some((v) => v.clave === parametros.get('vista')) ? parametros.get('vista') : 'tabla'

  const torneo = useQuery({ queryKey: ['torneo', slug], queryFn: () => obtenerTorneo(slug) })

  if (torneo.isPending) return <Cargando texto="Cargando el torneo…" />

  if (torneo.isError) {
    return (
      <EstadoVacio
        icono={<ShieldCheck size={28} />}
        titulo="No encontramos este torneo"
        accion={
          <Link className="boton boton--contorno boton--chico" to="/torneos">
            Ver todos los torneos
          </Link>
        }
      >
        Puede que el enlace esté mal, o que el torneo todavía no sea público.
      </EstadoVacio>
    )
  }

  const datos = torneo.data

  return (
    <>
      <header className="torneo-cabecera">
        <div style={{ display: 'flex', gap: '1rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <Insignia estado={datos.estado} />
          {datos.categoria && <Insignia tono="neutra">{datos.categoria}</Insignia>}
          <Insignia tono="neutra">{datos.formato.etiqueta}</Insignia>
        </div>

        <h1 style={{ marginTop: '1rem' }}>{datos.nombre}</h1>

        {datos.descripcion && (
          <p style={{ maxWidth: '58ch', marginTop: '1rem', color: 'rgba(246,243,233,.76)', fontSize: '.88rem' }}>
            {datos.descripcion}
          </p>
        )}

        <div className="torneo-cabecera__datos">
          {datos.ciudad && (
            <span>
              <MapPin size={15} aria-hidden="true" /> {datos.ciudad}
              {datos.sede ? ` · ${datos.sede}` : ''}
            </span>
          )}
          <span>
            <CalendarDays size={15} aria-hidden="true" /> Arranca el {fechaCorta(datos.fechas.inicia_en)}
          </span>
          <span>
            <Users size={15} aria-hidden="true" /> {datos.inscripcion.equipos_inscritos} de{' '}
            {datos.reglas.cupo_maximo_equipos} equipos
          </span>
          <span>
            <Coins size={15} aria-hidden="true" /> Inscripción {datos.inscripcion.costo_formateado}
          </span>
        </div>

        {datos.inscripcion.abierta && (
          <div className="portada__acciones">
            <Link className="boton boton--acento" to="/panel/inscripciones">
              Inscribir mi equipo
            </Link>
          </div>
        )}
      </header>

      <nav className="pestanas" style={{ marginTop: '1.75rem' }} aria-label="Secciones del torneo">
        {VISTAS.map((opcion) => (
          <button
            key={opcion.clave}
            className={`pestana ${vista === opcion.clave ? 'pestana--activa' : ''}`}
            type="button"
            aria-current={vista === opcion.clave ? 'page' : undefined}
            onClick={() => setParametros({ vista: opcion.clave })}
          >
            {opcion.etiqueta}
          </button>
        ))}
      </nav>

      <div style={{ marginTop: '1.75rem' }}>
        {vista === 'tabla' && <PestanaTabla slug={slug} />}
        {vista === 'calendario' && <PestanaCalendario slug={slug} />}
        {vista === 'goleadores' && <PestanaGoleadores slug={slug} />}
        {vista === 'equipos' && <PestanaEquipos slug={slug} />}
      </div>
    </>
  )
}

function PestanaTabla({ slug }) {
  const { data, isPending, isError } = useQuery({
    queryKey: ['torneo', slug, 'tabla'],
    queryFn: () => obtenerTabla(slug),
  })

  if (isPending) return <Cargando />
  if (isError) return <Aviso tono="error">No pudimos cargar la tabla de posiciones.</Aviso>

  return <TablaDePosiciones posiciones={data} />
}

function PestanaCalendario({ slug }) {
  const { data, isPending, isError } = useQuery({
    queryKey: ['torneo', slug, 'partidos'],
    queryFn: () => obtenerPartidosDelTorneo(slug),
  })

  if (isPending) return <Cargando />
  if (isError) return <Aviso tono="error">No pudimos cargar el calendario.</Aviso>

  return <ListaDePartidos partidos={data.data} />
}

function PestanaGoleadores({ slug }) {
  const { data, isPending, isError } = useQuery({
    queryKey: ['torneo', slug, 'goleadores'],
    queryFn: () => obtenerGoleadores(slug),
  })

  if (isPending) return <Cargando />
  if (isError) return <Aviso tono="error">No pudimos cargar la tabla de goleadores.</Aviso>

  return <ListaDeGoleadores goleadores={data} />
}

function PestanaEquipos({ slug }) {
  const { data, isPending, isError } = useQuery({
    queryKey: ['torneo', slug, 'equipos'],
    queryFn: () => obtenerEquiposDelTorneo(slug),
  })

  if (isPending) return <Cargando />
  if (isError) return <Aviso tono="error">No pudimos cargar los equipos.</Aviso>

  if (data.length === 0) {
    return (
      <EstadoVacio icono={<Users size={28} />} titulo="Todavía no hay equipos confirmados">
        Los equipos aparecen aquí cuando la organización aprueba su inscripción.
      </EstadoVacio>
    )
  }

  return (
    <div className="rejilla rejilla--tres">
      {data.map((equipo) => (
        <Link className="tarjeta" key={equipo.id} to={`/equipos/${equipo.slug}`}>
          <div style={{ display: 'flex', gap: '.9rem', alignItems: 'center' }}>
            <Escudo equipo={{ ...equipo, escudo_url: equipo.imagenes?.escudo_url }} tamano="grande" />
            <div style={{ minWidth: 0 }}>
              <h3 style={{ fontSize: '.95rem' }}>{equipo.nombre}</h3>
              <p style={{ color: 'var(--apagado)', fontSize: '.78rem', marginTop: '.2rem' }}>
                {equipo.ciudad ?? 'Sin ciudad'} · {equipo.jugadores_count ?? 0} jugadores
              </p>
            </div>
          </div>
        </Link>
      ))}
    </div>
  )
}

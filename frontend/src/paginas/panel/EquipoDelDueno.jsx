import { useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ImageUp, Trash2 } from 'lucide-react'
import {
  actualizarEquipo,
  eliminarEquipo,
  erroresPorCampo,
  mensajeDeError,
  obtenerMiEquipo,
  subirImagenDeEquipo,
} from '../../lib/api'
import {
  Aviso,
  Boton,
  Campo,
  CampoDeColor,
  CampoDeTexto,
  Cargando,
  Insignia,
  Interruptor,
  Tarjeta,
} from '../../componentes/ui/Basicos'
import { Escudo } from '../../componentes/ui/Escudo'
import { PlantillaDelEquipo } from './PlantillaDelEquipo'

export default function EquipoDelDueno() {
  const { slug } = useParams()
  const equipo = useQuery({ queryKey: ['mi-equipo', slug], queryFn: () => obtenerMiEquipo(slug) })

  if (equipo.isPending) return <Cargando />
  if (equipo.isError) return <Aviso tono="error">{mensajeDeError(equipo.error)}</Aviso>

  // La vista recibe el equipo ya cargado: así su formulario nace lleno, sin un
  // efecto que lo rellene después. La `key` lo reinicia si se cambia de equipo.
  return <VistaDelEquipo equipo={equipo.data} slug={slug} key={equipo.data.id} />
}

function formularioDesde(equipo) {
  return {
    nombre: equipo.nombre,
    abreviatura: equipo.abreviatura,
    ciudad: equipo.ciudad ?? '',
    estadio: equipo.estadio ?? '',
    fundado_en: equipo.fundado_en ?? '',
    descripcion: equipo.descripcion ?? '',
    color_primario: equipo.colores.primario,
    color_secundario: equipo.colores.secundario,
    activo: equipo.activo,
  }
}

function VistaDelEquipo({ equipo, slug }) {
  const cliente = useQueryClient()
  const navegar = useNavigate()

  const [formulario, setFormulario] = useState(() => formularioDesde(equipo))
  const [errores, setErrores] = useState({})
  const [aviso, setAviso] = useState('')
  const [error, setError] = useState('')

  const guardar = useMutation({
    mutationFn: (datos) => actualizarEquipo(slug, datos),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['mi-equipo', slug] })
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      setAviso('Los datos del equipo quedaron guardados.')
      setErrores({})
      setError('')
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo))
    },
  })

  const borrar = useMutation({
    mutationFn: () => eliminarEquipo(slug),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      navegar('/panel')
    },
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  function alCambiar(evento) {
    const { name, value, type, checked } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: type === 'checkbox' ? checked : value }))
    setErrores((actuales) => ({ ...actuales, [name]: undefined }))
    setAviso('')
  }

  return (
    <>
      <Link className="enlace-mas" to="/panel" style={{ marginBottom: '1rem', display: 'inline-flex' }}>
        <ArrowLeft size={15} /> Volver a mis equipos
      </Link>

      <header className="panel-cabecera">
        <div style={{ display: 'flex', gap: '1rem', alignItems: 'center' }}>
          <Escudo equipo={{ ...equipo, escudo_url: equipo.imagenes.escudo_url }} tamano="grande" />
          <div>
            <h1>{equipo.nombre}</h1>
            <p>
              {equipo.jugadores_count ?? 0} jugadores ·{' '}
              <Link to={`/equipos/${equipo.slug}`}>ver ficha pública</Link>
            </p>
          </div>
        </div>

        <div className="barra-acciones">
          {(equipo.inscripciones ?? []).map((inscripcion) => (
            <Insignia key={inscripcion.id} estado={inscripcion.estado} />
          ))}
        </div>
      </header>

      {aviso && <Aviso tono="exito">{aviso}</Aviso>}
      {error && <Aviso tono="error">{error}</Aviso>}

      <div className="rejilla rejilla--panel" style={{ marginTop: '1.5rem' }}>
        <div style={{ display: 'grid', gap: '1.25rem' }}>
          <Tarjeta titulo="Datos del equipo">
            <form
              style={{ display: 'grid', gap: '1.1rem' }}
              onSubmit={(evento) => {
                evento.preventDefault()
                guardar.mutate({
                  ...formulario,
                  fundado_en: formulario.fundado_en || null,
                  ciudad: formulario.ciudad || null,
                  estadio: formulario.estadio || null,
                  descripcion: formulario.descripcion || null,
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
                  required
                />
                <Campo
                  etiqueta="Abreviatura"
                  name="abreviatura"
                  value={formulario.abreviatura}
                  onChange={alCambiar}
                  error={errores.abreviatura}
                  maxLength={4}
                  pista="Tres letras, como ATU."
                  required
                />
              </div>

              <div className="rejilla-campos">
                <Campo
                  etiqueta="Ciudad"
                  name="ciudad"
                  value={formulario.ciudad}
                  onChange={alCambiar}
                  error={errores.ciudad}
                />
                <Campo
                  etiqueta="Cancha o estadio"
                  name="estadio"
                  value={formulario.estadio}
                  onChange={alCambiar}
                  error={errores.estadio}
                />
                <Campo
                  etiqueta="Fundado el"
                  name="fundado_en"
                  type="date"
                  value={formulario.fundado_en}
                  onChange={alCambiar}
                  error={errores.fundado_en}
                />
              </div>

              <CampoDeTexto
                etiqueta="Descripción"
                name="descripcion"
                value={formulario.descripcion}
                onChange={alCambiar}
                error={errores.descripcion}
                placeholder="Una línea sobre el equipo, su barrio, su historia."
              />

              <div className="rejilla-campos">
                <CampoDeColor
                  etiqueta="Color principal"
                  name="color_primario"
                  value={formulario.color_primario}
                  onChange={alCambiar}
                  error={errores.color_primario}
                />
                <CampoDeColor
                  etiqueta="Color secundario"
                  name="color_secundario"
                  value={formulario.color_secundario}
                  onChange={alCambiar}
                  error={errores.color_secundario}
                />
              </div>

              <Interruptor
                etiqueta="El equipo está activo"
                name="activo"
                checked={formulario.activo}
                onChange={alCambiar}
              />

              <div className="barra-acciones">
                <Boton type="submit" cargando={guardar.isPending}>
                  Guardar cambios
                </Boton>
              </div>
            </form>
          </Tarjeta>

          <Tarjeta>
            <PlantillaDelEquipo equipo={equipo} />
          </Tarjeta>
        </div>

        <aside style={{ display: 'grid', gap: '1.25rem' }}>
          <Tarjeta titulo="Escudo y uniformes">
            <div className="uniformes">
              <SubidaDeImagen
                slug={slug}
                tipo="escudo"
                titulo="Escudo"
                url={equipo.imagenes.escudo_url}
              />
              <SubidaDeImagen
                slug={slug}
                tipo="uniforme_local"
                titulo="Uniforme local"
                url={equipo.imagenes.uniforme_local_url}
              />
              <SubidaDeImagen
                slug={slug}
                tipo="uniforme_visitante"
                titulo="Uniforme visitante"
                url={equipo.imagenes.uniforme_visitante_url}
              />
            </div>
            <p className="campo__pista" style={{ marginTop: '1rem' }}>
              JPG, PNG o WEBP. El escudo se ve mejor cuadrado.
            </p>
          </Tarjeta>

          <Tarjeta titulo="Zona delicada">
            <p style={{ color: 'var(--apagado)', fontSize: '.8rem', marginBottom: '1rem' }}>
              Eliminar el equipo da de baja también su plantilla. No se puede si el equipo ya pagó la
              inscripción de algún torneo.
            </p>
            <Boton
              variante="peligro"
              iconoIzquierda={<Trash2 size={15} />}
              cargando={borrar.isPending}
              onClick={() => {
                if (window.confirm(`¿Eliminar ${equipo.nombre}? Esta acción no se puede deshacer.`)) {
                  borrar.mutate()
                }
              }}
            >
              Eliminar equipo
            </Boton>
          </Tarjeta>
        </aside>
      </div>
    </>
  )
}

function SubidaDeImagen({ slug, tipo, titulo, url }) {
  const cliente = useQueryClient()
  const campo = useRef(null)
  const [error, setError] = useState('')

  const subir = useMutation({
    mutationFn: (archivo) => subirImagenDeEquipo(slug, tipo, archivo),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['mi-equipo', slug] })
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      setError('')
    },
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  return (
    <div className="subida-imagen">
      <div className="subida-imagen__previa">
        {url ? <img src={url} alt={titulo} /> : <ImageUp size={22} aria-hidden="true" />}
      </div>
      <p>{titulo}</p>
      <Boton
        variante="contorno"
        tamano="chico"
        cargando={subir.isPending}
        onClick={() => campo.current?.click()}
      >
        {url ? 'Cambiar' : 'Subir'}
      </Boton>
      <input
        ref={campo}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        hidden
        onChange={(evento) => {
          const archivo = evento.target.files?.[0]

          if (archivo) subir.mutate(archivo)

          evento.target.value = ''
        }}
      />
      {error && <span className="campo__error">{error}</span>}
    </div>
  )
}

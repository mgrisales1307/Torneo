import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Copy, Crown, LogOut, RefreshCw, Upload, UserMinus } from 'lucide-react'
import {
  actualizarEquipo,
  actualizarMiembro,
  eliminarEquipo,
  erroresPorCampo,
  esProhibido,
  mensajeDeError,
  nuevoCodigo,
  obtenerMiEquipo,
  sacarMiembro,
  subirLogo,
} from '../../lib/api'
import { useSesion } from '../../contexto/contextoDeSesion'
import { iniciales } from '../../lib/formato'
import { colorDeJuego } from '../../lib/juegos'
import { LogoDeEquipo } from '../../componentes/equipo/LogoDeEquipo'
import { EtiquetaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { Aviso, Boton, Campo, CampoDeColor, Cargando, EstadoVacio, Insignia, Tarjeta } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR } from './enlacesDelPanel'

export default function EquipoDelPanel() {
  const { slug } = useParams()
  const { usuario } = useSesion()
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [aviso, setAviso] = useState(null)

  const equipo = useQuery({ queryKey: ['mi-equipo', slug], queryFn: () => obtenerMiEquipo(slug) })

  function avisar(tono, texto) {
    setAviso({ tono, texto })
  }

  function refrescar(respuesta) {
    cliente.invalidateQueries({ queryKey: ['mi-equipo', slug] })
    cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
    if (respuesta?.message) avisar('exito', respuesta.message)
  }

  const alFallar = (fallo) => avisar('error', mensajeDeError(fallo))

  const codigo = useMutation({ mutationFn: () => nuevoCodigo(slug), onSuccess: refrescar, onError: alFallar })
  const miembro = useMutation({
    mutationFn: ({ id, cambios }) => actualizarMiembro(slug, id, cambios),
    onSuccess: refrescar,
    onError: alFallar,
  })
  const sacar = useMutation({ mutationFn: (id) => sacarMiembro(slug, id), onSuccess: refrescar, onError: alFallar })
  const salir = useMutation({
    mutationFn: () => sacarMiembro(slug, usuario.id),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      navegar('/panel/equipos')
    },
    onError: alFallar,
  })
  const eliminar = useMutation({
    mutationFn: () => eliminarEquipo(slug),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      navegar('/panel/equipos')
    },
    onError: alFallar,
  })

  if (equipo.isPending) return <Cargando />
  if (esProhibido(equipo.error)) return <EstadoVacio titulo="No eres parte de este equipo" />
  if (equipo.isError) return <Aviso tono="error">{mensajeDeError(equipo.error)}</Aviso>

  const datos = equipo.data
  const capitan = datos.soy_capitan
  const titulares = datos.miembros.filter((persona) => !persona.es_suplente).length

  return (
    <div style={colorDeJuego(datos.juego.valor)}>
      <header className="ficha encabezado-pagina">
        <LogoDeEquipo equipo={datos} />
        <div>
          <EtiquetaDeJuego juego={datos.juego.valor} />
          <h1 className="titulo-pagina">{datos.nombre}</h1>
          <div className="fila">
            <span className="insignia insignia--neutra">{datos.abreviatura}</span>
            <Link className="boton boton--fantasma boton--chico" to={`/equipos/${datos.slug}`}>
              Ver ficha pública
            </Link>
          </div>
        </div>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_JUGADOR} />

      {aviso && (
        <div style={{ marginBottom: '1.25rem' }}>
          <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>
        </div>
      )}

      <div className="dos-columnas">
        <div className="pila pila--chica">
          <Tarjeta
            titulo={`Plantilla · ${titulares}/${datos.juego.titulares} titulares`}
          >
            <div className="miembros">
              {datos.miembros.map((persona) => (
                <div className="miembro" key={persona.id}>
                  <span className="avatar" aria-hidden="true">
                    {iniciales(persona.apodo)}
                  </span>
                  <span className="miembro__datos">
                    <strong>{persona.apodo}</strong>
                    <span>{persona.cuenta ?? `Sin ${datos.juego.cuenta}`}</span>
                  </span>
                  {persona.es_capitan && <Insignia tono="acento">Capitán</Insignia>}
                  {persona.es_suplente && <Insignia tono="neutra">Suplente</Insignia>}
                  {!persona.cuenta && <Insignia tono="aviso">Falta cuenta</Insignia>}

                  {capitan && !persona.es_capitan && (
                    <span className="miembro__acciones">
                      <Boton
                        variante="fantasma"
                        tamano="chico"
                        onClick={() => miembro.mutate({ id: persona.id, cambios: { es_suplente: !persona.es_suplente } })}
                      >
                        {persona.es_suplente ? 'Titular' : 'Suplente'}
                      </Boton>
                      <Boton
                        variante="fantasma"
                        tamano="chico"
                        aria-label={`Ceder la capitanía a ${persona.apodo}`}
                        title="Ceder la capitanía"
                        onClick={() => {
                          if (window.confirm(`¿Ceder la capitanía a ${persona.apodo}?`)) {
                            miembro.mutate({ id: persona.id, cambios: { capitan: true } })
                          }
                        }}
                      >
                        <Crown size={15} />
                      </Boton>
                      <Boton
                        variante="fantasma"
                        tamano="chico"
                        aria-label={`Sacar a ${persona.apodo}`}
                        title="Sacar del equipo"
                        onClick={() => {
                          if (window.confirm(`¿Sacar a ${persona.apodo} del equipo?`)) sacar.mutate(persona.id)
                        }}
                      >
                        <UserMinus size={15} />
                      </Boton>
                    </span>
                  )}
                </div>
              ))}
            </div>
          </Tarjeta>

          <Tarjeta titulo="Inscripciones">
            {datos.inscripciones.length === 0 ? (
              <p className="apagado">Sin inscripciones.</p>
            ) : (
              <div className="lista-detalle">
                {datos.inscripciones.map((inscripcion) => (
                  <div key={inscripcion.id}>
                    <Link to={`/torneos/${inscripcion.torneo.slug}`}>{inscripcion.torneo.nombre}</Link>
                    <Insignia estado={inscripcion.estado} />
                  </div>
                ))}
              </div>
            )}
          </Tarjeta>
        </div>

        <div className="pila pila--chica">
          <Tarjeta titulo="Código de invitación">
            <div className="codigo-invitacion">
              <code>{datos.codigo_invitacion}</code>
              <Boton
                variante="contorno"
                tamano="chico"
                iconoIzquierda={<Copy size={14} />}
                onClick={() => {
                  navigator.clipboard?.writeText(datos.codigo_invitacion)
                  avisar('exito', 'Código copiado.')
                }}
              >
                Copiar
              </Boton>
              {capitan && (
                <Boton
                  variante="fantasma"
                  tamano="chico"
                  iconoIzquierda={<RefreshCw size={14} />}
                  cargando={codigo.isPending}
                  onClick={() => codigo.mutate()}
                >
                  Cambiar
                </Boton>
              )}
            </div>
          </Tarjeta>

          {capitan && <EditarEquipo equipo={datos} alGuardar={refrescar} alFallar={alFallar} />}

          <div className="fila">
            {capitan ? (
              <Boton
                variante="peligro"
                tamano="chico"
                cargando={eliminar.isPending}
                onClick={() => {
                  if (window.confirm(`¿Eliminar ${datos.nombre}? No se puede deshacer.`)) eliminar.mutate()
                }}
              >
                Eliminar equipo
              </Boton>
            ) : (
              <Boton
                variante="peligro"
                tamano="chico"
                iconoIzquierda={<LogOut size={14} />}
                cargando={salir.isPending}
                onClick={() => {
                  if (window.confirm(`¿Salir de ${datos.nombre}?`)) salir.mutate()
                }}
              >
                Salir del equipo
              </Boton>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

function EditarEquipo({ equipo, alGuardar, alFallar }) {
  const [formulario, setFormulario] = useState({ nombre: equipo.nombre, abreviatura: equipo.abreviatura, color: equipo.color })
  const [errores, setErrores] = useState({})

  const guardar = useMutation({
    mutationFn: () => actualizarEquipo(equipo.slug, formulario),
    onSuccess: alGuardar,
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      alFallar(fallo)
    },
  })

  const logo = useMutation({
    mutationFn: (archivo) => subirLogo(equipo.slug, archivo),
    onSuccess: alGuardar,
    onError: alFallar,
  })

  function cambiar(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: value }))
  }

  return (
    <Tarjeta titulo="Editar">
      <form
        className="formulario"
        onSubmit={(evento) => {
          evento.preventDefault()
          guardar.mutate()
        }}
      >
        <Campo etiqueta="Nombre" name="nombre" value={formulario.nombre} onChange={cambiar} error={errores.nombre} />
        <div className="formulario__fila">
          <Campo etiqueta="Sigla" name="abreviatura" value={formulario.abreviatura} onChange={cambiar} error={errores.abreviatura} maxLength={5} />
          <CampoDeColor etiqueta="Color" name="color" value={formulario.color} onChange={cambiar} error={errores.color} />
        </div>

        <label className="campo">
          <span className="campo__etiqueta">Logo (PNG, JPG o WEBP · hasta 2 MB)</span>
          <span className="boton boton--contorno boton--chico" style={{ justifySelf: 'start' }}>
            <Upload size={14} aria-hidden="true" /> {logo.isPending ? 'Subiendo…' : 'Subir logo'}
          </span>
          <input
            className="solo-lectores"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(evento) => evento.target.files?.[0] && logo.mutate(evento.target.files[0])}
          />
        </label>

        <div className="formulario__acciones">
          <Boton type="submit" cargando={guardar.isPending}>
            Guardar
          </Boton>
        </div>
      </form>
    </Tarjeta>
  )
}

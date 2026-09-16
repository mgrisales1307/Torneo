import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Camera, Pencil, Plus, Trash2, Users } from 'lucide-react'
import {
  actualizarJugador,
  crearJugador,
  eliminarJugador,
  erroresPorCampo,
  listarJugadores,
  mensajeDeError,
  subirFotoDeJugador,
} from '../../lib/api'
import {
  Aviso,
  Boton,
  Campo,
  CampoDeSeleccion,
  Cargando,
  EstadoVacio,
  Insignia,
  Interruptor,
} from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'

const POSICIONES = [
  { valor: 'portero', etiqueta: 'Portero' },
  { valor: 'defensa', etiqueta: 'Defensa' },
  { valor: 'mediocampista', etiqueta: 'Mediocampista' },
  { valor: 'delantero', etiqueta: 'Delantero' },
]

const ESTADOS = [
  { valor: 'activo', etiqueta: 'Activo' },
  { valor: 'lesionado', etiqueta: 'Lesionado' },
  { valor: 'suspendido', etiqueta: 'Suspendido' },
  { valor: 'inactivo', etiqueta: 'Inactivo' },
]

const JUGADOR_VACIO = {
  nombres: '',
  apellidos: '',
  documento_identidad: '',
  fecha_nacimiento: '',
  posicion: 'mediocampista',
  numero_camiseta: '',
  estado: 'activo',
  es_capitan: false,
  telefono: '',
  correo: '',
}

export function PlantillaDelEquipo({ equipo }) {
  const cliente = useQueryClient()
  const [enEdicion, setEnEdicion] = useState(null)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')
  const [aviso, setAviso] = useState('')
  const jugadorDeLaFoto = useRef(null)
  const campoDeFoto = useRef(null)

  const jugadores = useQuery({
    queryKey: ['plantilla', equipo.slug],
    queryFn: () => listarJugadores(equipo.slug),
  })

  function alTerminar(mensaje) {
    cliente.invalidateQueries({ queryKey: ['plantilla', equipo.slug] })
    cliente.invalidateQueries({ queryKey: ['mi-equipo', equipo.slug] })
    cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
    setAviso(mensaje)
    cerrarDialogo()
  }

  function alFallar(fallo) {
    setErrores(erroresPorCampo(fallo))
    setError(mensajeDeError(fallo))
  }

  const guardar = useMutation({
    mutationFn: (datos) =>
      datos.id ? actualizarJugador(datos.id, datos.cuerpo) : crearJugador(equipo.slug, datos.cuerpo),
    onSuccess: (_, variables) =>
      alTerminar(variables.id ? 'Jugador actualizado.' : 'Jugador agregado a la plantilla.'),
    onError: alFallar,
  })

  const borrar = useMutation({
    mutationFn: eliminarJugador,
    onSuccess: () => alTerminar('El jugador salió de la plantilla.'),
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  const subirFoto = useMutation({
    mutationFn: ({ id, archivo }) => subirFotoDeJugador(id, archivo),
    onSuccess: () => alTerminar('Foto actualizada.'),
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  function cerrarDialogo() {
    setEnEdicion(null)
    setErrores({})
    setError('')
  }

  function abrirNuevo() {
    setErrores({})
    setError('')
    setEnEdicion({ ...JUGADOR_VACIO })
  }

  function abrirEdicion(jugador) {
    setErrores({})
    setError('')
    setEnEdicion({
      id: jugador.id,
      nombres: jugador.nombres,
      apellidos: jugador.apellidos,
      documento_identidad: jugador.documento_identidad ?? '',
      fecha_nacimiento: jugador.fecha_nacimiento ?? '',
      posicion: jugador.posicion.valor,
      numero_camiseta: jugador.numero_camiseta ?? '',
      estado: jugador.estado.valor,
      es_capitan: jugador.es_capitan,
      telefono: jugador.telefono ?? '',
      correo: jugador.correo ?? '',
    })
  }

  function alEnviar(evento) {
    evento.preventDefault()
    setError('')

    const { id, ...campos } = enEdicion

    guardar.mutate({
      id,
      cuerpo: {
        ...campos,
        // Los campos vacíos viajan como null, no como cadena vacía: así el
        // backend los entiende como «sin dato» y no como «dato en blanco».
        numero_camiseta: campos.numero_camiseta === '' ? null : Number(campos.numero_camiseta),
        telefono: campos.telefono || null,
        correo: campos.correo || null,
      },
    })
  }

  function alElegirFoto(evento) {
    const archivo = evento.target.files?.[0]

    if (archivo && jugadorDeLaFoto.current) {
      subirFoto.mutate({ id: jugadorDeLaFoto.current, archivo })
    }

    evento.target.value = ''
  }

  return (
    <>
      <div className="tarjeta__titulo">
        <div>
          <h2 style={{ fontSize: '1rem' }}>Plantilla</h2>
          <p style={{ color: 'var(--apagado)', fontSize: '.78rem', marginTop: '.25rem' }}>
            Los jugadores que representan al equipo en los torneos.
          </p>
        </div>
        <Boton onClick={abrirNuevo} iconoIzquierda={<Plus size={16} />} tamano="chico">
          Agregar jugador
        </Boton>
      </div>

      {aviso && <Aviso tono="exito">{aviso}</Aviso>}
      {error && !enEdicion && <Aviso tono="error">{error}</Aviso>}

      {jugadores.isPending && <Cargando />}

      {jugadores.data?.length === 0 && (
        <EstadoVacio icono={<Users size={28} />} titulo="La plantilla está vacía">
          Agrega al menos los jugadores que exige el torneo para poder inscribir al equipo.
        </EstadoVacio>
      )}

      {jugadores.data?.length > 0 && (
        <div className="tabla-envoltura" style={{ marginTop: '1rem' }}>
          <table className="tabla">
            <thead>
              <tr>
                <th scope="col" className="numerico">
                  #
                </th>
                <th scope="col">Jugador</th>
                <th scope="col">Posición</th>
                <th scope="col">Documento</th>
                <th scope="col">Estado</th>
                <th scope="col">
                  <span className="solo-lectores">Acciones</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {jugadores.data.map((jugador) => (
                <tr key={jugador.id}>
                  <td className="numerico">{jugador.numero_camiseta ?? '—'}</td>
                  <td>
                    <strong>{jugador.nombre_completo}</strong>
                    {jugador.es_capitan && (
                      <span style={{ marginLeft: '.45rem' }}>
                        <Insignia tono="acento">Capitán</Insignia>
                      </span>
                    )}
                  </td>
                  <td>{jugador.posicion.etiqueta}</td>
                  <td>{jugador.documento_identidad}</td>
                  <td>
                    <Insignia estado={jugador.estado} />
                  </td>
                  <td>
                    <div className="barra-acciones">
                      <Boton
                        variante="fantasma"
                        tamano="chico"
                        onClick={() => {
                          jugadorDeLaFoto.current = jugador.id
                          campoDeFoto.current?.click()
                        }}
                        aria-label={`Cambiar la foto de ${jugador.nombre_completo}`}
                      >
                        <Camera size={15} />
                      </Boton>
                      <Boton
                        variante="fantasma"
                        tamano="chico"
                        onClick={() => abrirEdicion(jugador)}
                        aria-label={`Editar a ${jugador.nombre_completo}`}
                      >
                        <Pencil size={15} />
                      </Boton>
                      <Boton
                        variante="peligro"
                        tamano="chico"
                        onClick={() => {
                          if (window.confirm(`¿Dar de baja a ${jugador.nombre_completo}?`)) {
                            borrar.mutate(jugador.id)
                          }
                        }}
                        aria-label={`Dar de baja a ${jugador.nombre_completo}`}
                      >
                        <Trash2 size={15} />
                      </Boton>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <input
        ref={campoDeFoto}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={alElegirFoto}
        hidden
      />

      <Dialogo
        abierto={enEdicion !== null}
        ancho
        titulo={enEdicion?.id ? 'Editar jugador' : 'Nuevo jugador'}
        descripcion="Los datos personales solo los ve tu equipo y la organización."
        alCerrar={cerrarDialogo}
      >
        {enEdicion && (
          <form style={{ display: 'grid', gap: '1.1rem' }} onSubmit={alEnviar}>
            <div className="rejilla-campos">
              <Campo
                etiqueta="Nombres"
                name="nombres"
                value={enEdicion.nombres}
                onChange={(evento) => setEnEdicion({ ...enEdicion, nombres: evento.target.value })}
                error={errores.nombres}
                required
              />
              <Campo
                etiqueta="Apellidos"
                name="apellidos"
                value={enEdicion.apellidos}
                onChange={(evento) => setEnEdicion({ ...enEdicion, apellidos: evento.target.value })}
                error={errores.apellidos}
                required
              />
            </div>

            <div className="rejilla-campos">
              <Campo
                etiqueta="Documento de identidad"
                name="documento_identidad"
                value={enEdicion.documento_identidad}
                onChange={(evento) =>
                  setEnEdicion({ ...enEdicion, documento_identidad: evento.target.value })
                }
                error={errores.documento_identidad}
                required
              />
              <Campo
                etiqueta="Fecha de nacimiento"
                name="fecha_nacimiento"
                type="date"
                value={enEdicion.fecha_nacimiento}
                onChange={(evento) => setEnEdicion({ ...enEdicion, fecha_nacimiento: evento.target.value })}
                error={errores.fecha_nacimiento}
                required
              />
            </div>

            <div className="rejilla-campos">
              <CampoDeSeleccion
                etiqueta="Posición"
                name="posicion"
                opciones={POSICIONES}
                value={enEdicion.posicion}
                onChange={(evento) => setEnEdicion({ ...enEdicion, posicion: evento.target.value })}
                error={errores.posicion}
              />
              <Campo
                etiqueta="Número de camiseta"
                name="numero_camiseta"
                type="number"
                min={1}
                max={99}
                value={enEdicion.numero_camiseta}
                onChange={(evento) => setEnEdicion({ ...enEdicion, numero_camiseta: evento.target.value })}
                error={errores.numero_camiseta}
                pista="Único dentro del equipo."
              />
              <CampoDeSeleccion
                etiqueta="Estado"
                name="estado"
                opciones={ESTADOS}
                value={enEdicion.estado}
                onChange={(evento) => setEnEdicion({ ...enEdicion, estado: evento.target.value })}
                error={errores.estado}
              />
            </div>

            <div className="rejilla-campos">
              <Campo
                etiqueta="Teléfono (opcional)"
                name="telefono"
                value={enEdicion.telefono}
                onChange={(evento) => setEnEdicion({ ...enEdicion, telefono: evento.target.value })}
                error={errores.telefono}
              />
              <Campo
                etiqueta="Correo (opcional)"
                name="correo"
                type="email"
                value={enEdicion.correo}
                onChange={(evento) => setEnEdicion({ ...enEdicion, correo: evento.target.value })}
                error={errores.correo}
              />
            </div>

            <Interruptor
              etiqueta="Es el capitán del equipo"
              checked={enEdicion.es_capitan}
              onChange={(evento) => setEnEdicion({ ...enEdicion, es_capitan: evento.target.checked })}
            />

            {error && <Aviso tono="error">{error}</Aviso>}

            <div className="dialogo__acciones">
              <Boton variante="contorno" type="button" onClick={cerrarDialogo}>
                Cancelar
              </Boton>
              <Boton type="submit" cargando={guardar.isPending}>
                {enEdicion.id ? 'Guardar cambios' : 'Agregar jugador'}
              </Boton>
            </div>
          </form>
        )}
      </Dialogo>
    </>
  )
}

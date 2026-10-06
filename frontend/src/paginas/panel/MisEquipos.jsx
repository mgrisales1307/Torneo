import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, UserPlus } from 'lucide-react'
import { crearEquipo, erroresPorCampo, listarMisEquipos, mensajeDeError, unirseAEquipo } from '../../lib/api'
import { colorDeJuego, JUEGOS } from '../../lib/juegos'
import { LogoDeEquipo } from '../../componentes/equipo/LogoDeEquipo'
import { EtiquetaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { Aviso, Boton, Campo, CampoDeColor, CampoDeSeleccion, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR } from './enlacesDelPanel'

export default function MisEquipos() {
  const equipos = useQuery({ queryKey: ['mis-equipos'], queryFn: listarMisEquipos })
  const [dialogo, setDialogo] = useState(null)

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Equipos</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_JUGADOR} />

      <div className="fila" style={{ marginBottom: '1.5rem' }}>
        <Boton iconoIzquierda={<Plus size={16} />} onClick={() => setDialogo('crear')}>
          Crear equipo
        </Boton>
        <Boton variante="contorno" iconoIzquierda={<UserPlus size={16} />} onClick={() => setDialogo('unirse')}>
          Unirme con código
        </Boton>
      </div>

      {equipos.isPending ? (
        <Cargando />
      ) : equipos.isError ? (
        <Aviso tono="error">{mensajeDeError(equipos.error)}</Aviso>
      ) : equipos.data.length === 0 ? (
        <EstadoVacio titulo="Todavía no tienes equipo" />
      ) : (
        <div className="rejilla">
          {equipos.data.map((equipo) => (
            <Link className="tarjeta-equipo" key={equipo.id} to={`/panel/equipos/${equipo.slug}`} style={colorDeJuego(equipo.juego.valor)}>
              <div className="tarjeta-equipo__cabeza">
                <LogoDeEquipo equipo={equipo} />
                <div>
                  <strong>{equipo.nombre}</strong>
                  <EtiquetaDeJuego juego={equipo.juego.valor} />
                </div>
              </div>
              <div className="fila fila--entre">
                <span className="apagado">
                  {equipo.miembros_count} de {equipo.juego.titulares + equipo.juego.suplentes_maximos} jugadores
                </span>
                {equipo.soy_capitan && <Insignia tono="acento">Capitán</Insignia>}
              </div>
            </Link>
          ))}
        </div>
      )}

      <DialogoCrear abierto={dialogo === 'crear'} alCerrar={() => setDialogo(null)} />
      <DialogoUnirse abierto={dialogo === 'unirse'} alCerrar={() => setDialogo(null)} />
    </div>
  )
}

const EQUIPO_VACIO = { juego: 'valorant', nombre: '', abreviatura: '', color: '#FF4655' }

function DialogoCrear({ abierto, alCerrar }) {
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [formulario, setFormulario] = useState(EQUIPO_VACIO)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')

  const crear = useMutation({
    mutationFn: () => crearEquipo(formulario),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      setFormulario(EQUIPO_VACIO)
      navegar(`/panel/equipos/${respuesta.data.slug}`)
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo))
    },
  })

  function cambiar(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: value }))
    setErrores((actuales) => ({ ...actuales, [name]: undefined }))
  }

  return (
    <Dialogo abierto={abierto} titulo="Crear equipo" alCerrar={alCerrar}>
      <form
        className="formulario"
        onSubmit={(evento) => {
          evento.preventDefault()
          crear.mutate()
        }}
      >
        <CampoDeSeleccion
          etiqueta="Juego"
          name="juego"
          value={formulario.juego}
          onChange={cambiar}
          error={errores.juego}
          opciones={JUEGOS.map((juego) => ({ valor: juego.valor, etiqueta: juego.nombre }))}
        />
        <Campo etiqueta="Nombre" name="nombre" value={formulario.nombre} onChange={cambiar} error={errores.nombre} maxLength={60} />
        <div className="formulario__fila">
          <Campo
            etiqueta="Sigla"
            name="abreviatura"
            value={formulario.abreviatura}
            onChange={cambiar}
            error={errores.abreviatura}
            maxLength={5}
            placeholder="NZ"
          />
          <CampoDeColor etiqueta="Color" name="color" value={formulario.color} onChange={cambiar} error={errores.color} />
        </div>

        {error && !Object.values(errores).some(Boolean) && <Aviso tono="error">{error}</Aviso>}

        <div className="formulario__acciones">
          <Boton variante="fantasma" type="button" onClick={alCerrar}>
            Cancelar
          </Boton>
          <Boton type="submit" cargando={crear.isPending}>
            Crear
          </Boton>
        </div>
      </form>
    </Dialogo>
  )
}

function DialogoUnirse({ abierto, alCerrar }) {
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [codigo, setCodigo] = useState('')
  const [error, setError] = useState('')

  const unirse = useMutation({
    mutationFn: () => unirseAEquipo(codigo.trim()),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      setCodigo('')
      navegar(`/panel/equipos/${respuesta.data.slug}`)
    },
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  return (
    <Dialogo abierto={abierto} titulo="Unirme con código" alCerrar={alCerrar}>
      <form
        className="formulario"
        onSubmit={(evento) => {
          evento.preventDefault()
          unirse.mutate()
        }}
      >
        <Campo
          etiqueta="Código de invitación"
          name="codigo"
          value={codigo}
          maxLength={8}
          autoComplete="off"
          style={{ fontSize: '1.4rem', fontWeight: 800, letterSpacing: '.18em', textTransform: 'uppercase' }}
          onChange={(evento) => {
            setCodigo(evento.target.value)
            setError('')
          }}
          error={error}
        />
        <div className="formulario__acciones">
          <Boton variante="fantasma" type="button" onClick={alCerrar}>
            Cancelar
          </Boton>
          <Boton type="submit" disabled={codigo.trim().length !== 8} cargando={unirse.isPending}>
            Unirme
          </Boton>
        </div>
      </form>
    </Dialogo>
  )
}

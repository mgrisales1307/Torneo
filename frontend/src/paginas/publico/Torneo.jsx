import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Trophy } from 'lucide-react'
import {
  crearOrdenDePago,
  esNoEncontrado,
  esProhibido,
  hacerCheckIn,
  inscribirEquipo,
  listarMisEquipos,
  listarMisInscripciones,
  mensajeDeError,
  obtenerCuadro,
  obtenerEquiposDelTorneo,
  obtenerTorneo,
} from '../../lib/api'
import { useSesion } from '../../contexto/contextoDeSesion'
import { colorDeJuego, juegoDe } from '../../lib/juegos'
import { fechaCompacta, relativo } from '../../lib/formato'
import { Cuadro } from '../../componentes/torneo/Cuadro'
import { EquipoEnLinea, LogoDeEquipo } from '../../componentes/equipo/LogoDeEquipo'
import { Aviso, Boton, Cargando, EstadoVacio, Insignia, Pestanas } from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'
import NoEncontrada from './NoEncontrada'

export default function Torneo() {
  const { slug } = useParams()
  const torneo = useQuery({ queryKey: ['torneo', slug], queryFn: () => obtenerTorneo(slug) })
  const [pestana, setPestana] = useState(null)

  const empezo = ['en_curso', 'finalizado'].includes(torneo.data?.estado.valor)

  const cuadro = useQuery({
    queryKey: ['cuadro', slug],
    queryFn: () => obtenerCuadro(slug),
    enabled: empezo,
  })

  if (torneo.isPending) return <Cargando />
  if (esNoEncontrado(torneo.error) || esProhibido(torneo.error)) return <NoEncontrada />
  if (torneo.isError) return <Aviso tono="error">{mensajeDeError(torneo.error)}</Aviso>

  const datos = torneo.data
  const visual = juegoDe(datos.juego.valor)
  const activa = pestana ?? (empezo ? 'cuadro' : 'equipos')

  const opciones = [
    ...(empezo ? [{ valor: 'cuadro', etiqueta: 'Cuadro' }] : []),
    { valor: 'equipos', etiqueta: `Equipos (${datos.inscripcion.equipos_inscritos})` },
    { valor: 'reglas', etiqueta: 'Reglas y premios' },
  ]

  return (
    <div>
      <header className="encabezado-juego" style={colorDeJuego(datos.juego.valor)}>
        {visual.portada && <img className="encabezado-juego__fondo" src={visual.portada} alt="" />}

        <div className="fila">
          <Link className="etiqueta-juego" to={`/juegos/${datos.juego.valor}`}>
            {datos.juego.nombre}
          </Link>
          <Insignia estado={datos.estado} />
        </div>

        <h1 className="titulo-pagina">{datos.nombre}</h1>

        <div className="datos-clave">
          <div>
            <span className="rotulo">Formato</span>
            <strong>{datos.formato.etiqueta}</strong>
          </div>
          <div>
            <span className="rotulo">Equipos</span>
            <strong>
              {datos.inscripcion.equipos_inscritos}/{datos.ajustes.cupo_maximo_equipos}
            </strong>
          </div>
          <div>
            <span className="rotulo">Inicio</span>
            <strong>{fechaCompacta(datos.fechas.inicia_en)}</strong>
          </div>
          <div>
            <span className="rotulo">Inscripción</span>
            <strong>{datos.inscripcion.costo_formateado}</strong>
          </div>
          {datos.premios.total_formateado && (
            <div>
              <span className="rotulo">Premios</span>
              <strong>{datos.premios.total_formateado}</strong>
            </div>
          )}
          {!datos.juego.es_battle_royale && (
            <div>
              <span className="rotulo">Series</span>
              <strong>
                BO{datos.ajustes.mejor_de} · final BO{datos.ajustes.mejor_de_final}
              </strong>
            </div>
          )}
        </div>

        <MiParticipacion torneo={datos} />
      </header>

      {datos.campeon && (
        <div className="campeon" style={{ marginBottom: '2rem' }}>
          <Trophy size={28} aria-hidden="true" />
          <LogoDeEquipo equipo={datos.campeon} tamano="2.75rem" />
          <div>
            <p className="rotulo">Campeón</p>
            <strong>{datos.campeon.nombre}</strong>
          </div>
        </div>
      )}

      <Pestanas opciones={opciones} activa={activa} alCambiar={setPestana} />

      {activa === 'cuadro' &&
        (cuadro.isPending ? (
          <Cargando />
        ) : cuadro.isError ? (
          <Aviso tono="error">{mensajeDeError(cuadro.error)}</Aviso>
        ) : (
          <Cuadro fases={cuadro.data.data} tablas={cuadro.data.tablas} torneo={datos} />
        ))}

      {activa === 'equipos' && <EquiposDelTorneo slug={slug} empezo={empezo} />}

      {activa === 'reglas' && <ReglasYPremios torneo={datos} />}
    </div>
  )
}

/**
 * Lo que puede hacer quien mira el torneo: inscribir un equipo que capitanea,
 * pagar, hacer el check-in, o ver en qué quedó su inscripción.
 */
function MiParticipacion({ torneo }) {
  const { estaAutenticado } = useSesion()
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [dialogo, setDialogo] = useState(false)
  const [aviso, setAviso] = useState(null)

  const inscripciones = useQuery({
    queryKey: ['mis-inscripciones'],
    queryFn: listarMisInscripciones,
    enabled: estaAutenticado,
  })

  const mia = inscripciones.data?.find(
    (inscripcion) => inscripcion.torneo.id === torneo.id && !['rechazada', 'retirada'].includes(inscripcion.estado.valor),
  )

  const refrescar = () => {
    cliente.invalidateQueries({ queryKey: ['mis-inscripciones'] })
    cliente.invalidateQueries({ queryKey: ['torneo', torneo.slug] })
    cliente.invalidateQueries({ queryKey: ['equipos-del-torneo', torneo.slug] })
  }

  const checkIn = useMutation({
    mutationFn: () => hacerCheckIn(mia.id),
    onSuccess: (respuesta) => {
      setAviso({ tono: 'exito', texto: respuesta.message })
      refrescar()
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  const pagar = useMutation({
    mutationFn: () => crearOrdenDePago(mia.id),
    onSuccess: (respuesta) => navegar(`/panel/pagos/${respuesta.data.referencia}`),
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  if (!estaAutenticado) {
    return torneo.inscripcion.abierta ? (
      <div className="encabezado-juego__acciones">
        <Link className="boton boton--solido" to="/ingreso" state={{ destino: `/torneos/${torneo.slug}` }}>
          Entrar para inscribirte
        </Link>
      </div>
    ) : null
  }

  const soyCapitan = mia?.equipo?.soy_capitan

  return (
    <div className="pila pila--chica" style={{ marginTop: '0.5rem' }}>
      {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}

      <div className="encabezado-juego__acciones">
        {mia ? (
          <>
            <span className="fila">
              <EquipoEnLinea equipo={mia.equipo} />
              <Insignia estado={mia.estado} />
              {mia.check_in_en && (
                <Insignia tono="exito">
                  <CheckCircle2 size={12} aria-hidden="true" /> Check-in hecho
                </Insignia>
              )}
            </span>

            {soyCapitan && mia.espera_pago && (
              <Boton cargando={pagar.isPending} onClick={() => pagar.mutate()}>
                Pagar {mia.monto_formateado}
              </Boton>
            )}

            {soyCapitan && mia.estado.valor === 'aprobada' && torneo.check_in.minutos > 0 && !mia.check_in_en && (
              torneo.check_in.abierto ? (
                <Boton cargando={checkIn.isPending} onClick={() => checkIn.mutate()}>
                  Hacer check-in
                </Boton>
              ) : (
                torneo.check_in.abre_en && <span className="apagado">Check-in {relativo(torneo.check_in.abre_en)}</span>
              )
            )}
          </>
        ) : (
          torneo.inscripcion.abierta && (
            <Boton onClick={() => setDialogo(true)}>Inscribir equipo</Boton>
          )
        )}
      </div>

      <DialogoDeInscripcion
        abierto={dialogo}
        torneo={torneo}
        alCerrar={() => setDialogo(false)}
        alInscribir={(mensaje) => {
          setDialogo(false)
          setAviso({ tono: 'exito', texto: mensaje })
          refrescar()
        }}
      />
    </div>
  )
}

function DialogoDeInscripcion({ abierto, torneo, alCerrar, alInscribir }) {
  const [elegido, setElegido] = useState(null)
  const [error, setError] = useState('')

  const equipos = useQuery({ queryKey: ['mis-equipos'], queryFn: listarMisEquipos, enabled: abierto })

  const inscribir = useMutation({
    mutationFn: () => inscribirEquipo(torneo.slug, elegido),
    onSuccess: (respuesta) => alInscribir(respuesta.message),
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  const mios = (equipos.data ?? []).filter(
    (equipo) => equipo.soy_capitan && equipo.juego.valor === torneo.juego.valor,
  )

  return (
    <Dialogo abierto={abierto} titulo="Inscribir equipo" descripcion={torneo.nombre} alCerrar={alCerrar}>
      {equipos.isPending ? (
        <Cargando />
      ) : mios.length === 0 ? (
        <EstadoVacio
          titulo={`No capitaneas ningún equipo de ${torneo.juego.nombre}`}
          accion={
            <Link className="boton boton--solido boton--chico" to="/panel/equipos">
              Crear equipo
            </Link>
          }
        />
      ) : (
        <div className="formulario">
          <div className="miembros" role="radiogroup" aria-label="Equipo">
            {mios.map((equipo) => (
              <label className="miembro" key={equipo.id} style={{ cursor: 'pointer' }}>
                <input
                  type="radio"
                  name="equipo"
                  checked={elegido === equipo.id}
                  onChange={() => {
                    setElegido(equipo.id)
                    setError('')
                  }}
                />
                <LogoDeEquipo equipo={equipo} />
                <span className="miembro__datos">
                  <strong>{equipo.nombre}</strong>
                  <span>
                    {equipo.miembros_count} de {equipo.juego.titulares} titulares
                  </span>
                </span>
              </label>
            ))}
          </div>

          {error && <Aviso tono="error">{error}</Aviso>}

          <div className="formulario__acciones">
            <Boton variante="fantasma" onClick={alCerrar}>
              Cancelar
            </Boton>
            <Boton disabled={!elegido} cargando={inscribir.isPending} onClick={() => inscribir.mutate()}>
              Inscribir {torneo.inscripcion.costo_centavos > 0 ? `· ${torneo.inscripcion.costo_formateado}` : ''}
            </Boton>
          </div>
        </div>
      )}
    </Dialogo>
  )
}

function EquiposDelTorneo({ slug, empezo }) {
  const equipos = useQuery({ queryKey: ['equipos-del-torneo', slug], queryFn: () => obtenerEquiposDelTorneo(slug) })

  if (equipos.isPending) return <Cargando />
  if (equipos.isError) return <Aviso tono="error">{mensajeDeError(equipos.error)}</Aviso>
  if (equipos.data.length === 0) return <EstadoVacio titulo="Todavía no hay equipos aprobados" />

  return (
    <div className="tabla-envoltura">
      <table className="tabla">
        <thead>
          <tr>
            {empezo && <th className="numero">Semilla</th>}
            <th>Equipo</th>
            <th className="numero">Jugadores</th>
            {empezo && <th>Grupo</th>}
            {!empezo && <th>Check-in</th>}
          </tr>
        </thead>
        <tbody>
          {equipos.data.map((equipo) => (
            <tr key={equipo.id}>
              {empezo && <td className="numero posicion">{equipo.semilla}</td>}
              <td>
                <EquipoEnLinea equipo={equipo} tamano="1.75rem" />
              </td>
              <td className="numero">{equipo.miembros_count}</td>
              {empezo && <td>{equipo.grupo ?? '—'}</td>}
              {!empezo && <td>{equipo.hizo_check_in ? <Insignia tono="exito">Listo</Insignia> : '—'}</td>}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ReglasYPremios({ torneo }) {
  return (
    <div className="dos-columnas">
      <section className="tarjeta">
        <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
          Reglas
        </h2>
        {torneo.reglas ? (
          <ul className="pila pila--chica">
            {torneo.reglas.split('\n').filter(Boolean).map((regla) => (
              <li key={regla} className="apagado">
                {regla}
              </li>
            ))}
          </ul>
        ) : (
          <p className="apagado">La organización no publicó reglas adicionales.</p>
        )}
      </section>

      <section className="tarjeta">
        <h2 className="titulo-bloque" style={{ marginBottom: '1rem' }}>
          Premios
        </h2>
        {torneo.premios.puestos.length === 0 ? (
          <p className="apagado">Sin premio en dinero.</p>
        ) : (
          <div className="lista-detalle">
            {torneo.premios.puestos.map((puesto) => (
              <div key={puesto.puesto}>
                <span>{puesto.puesto}.º puesto</span>
                <strong>{puesto.formateado}</strong>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

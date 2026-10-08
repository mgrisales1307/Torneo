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
  inscribirmeIndividual,
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
import { EstadoDeInicio } from '../../componentes/torneo/EstadoDeInicio'
import NoEncontrada from './NoEncontrada'

export default function Torneo() {
  const { slug } = useParams()
  const torneo = useQuery({ queryKey: ['torneo', slug], queryFn: () => obtenerTorneo(slug), refetchInterval: 5000 })
  const [pestana, setPestana] = useState(null)

  const empezo = ['en_curso', 'finalizado'].includes(torneo.data?.estado.valor)

  const cuadro = useQuery({
    queryKey: ['cuadro', slug],
    queryFn: () => obtenerCuadro(slug),
    enabled: empezo,
    refetchInterval: torneo.data?.estado.valor === 'en_curso' ? 5000 : false,
  })

  if (torneo.isPending) return <Cargando />
  if (esNoEncontrado(torneo.error) || esProhibido(torneo.error)) return <NoEncontrada />
  if (torneo.isError) return <Aviso tono="error">{mensajeDeError(torneo.error)}</Aviso>

  const datos = torneo.data
  const visual = juegoDe(datos.juego.valor)
  const activa = pestana ?? (empezo ? 'cuadro' : 'equipos')

  const opciones = [
    ...(empezo ? [{ valor: 'cuadro', etiqueta: 'Cuadro' }] : []),
    { valor: 'equipos', etiqueta: `${datos.modalidad === 'individual' ? 'Jugadores' : 'Equipos'} (${datos.inscripcion.equipos_inscritos})` },
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
            <span className="rotulo">{datos.modalidad === 'individual' ? 'Jugadores' : 'Equipos'}</span>
            <strong>
              {datos.inscripcion.equipos_inscritos}/{datos.ajustes.cupo_maximo_equipos}
            </strong>
          </div>
          <div>
            <span className="rotulo">Inicio</span>
            <strong>{datos.inicio?.por_cupo ? 'Al completar los cupos' : fechaCompacta(datos.fechas.inicia_en)}</strong>
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
        <EstadoDeInicio torneo={datos} />
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
      {datos.clasificacion_final?.length > 0 && <section className="tarjeta" style={{ marginBottom: '2rem' }}><h2 className="titulo-bloque">Clasificación final</h2><p className="apagado">Los premios virtuales se acreditan automáticamente a quien hizo cada inscripción.</p><div className="tabla-envoltura"><table className="tabla"><thead><tr><th>Puesto</th><th>{datos.modalidad === 'individual' ? 'Jugador' : 'Equipo'}</th><th>Premio</th></tr></thead><tbody>{datos.clasificacion_final.map((fila) => <tr key={fila.equipo.id}><td>{fila.puesto}</td><td><EquipoEnLinea equipo={fila.equipo} /></td><td>{datos.premios.puestos.find((premio) => premio.puesto === fila.puesto)?.formateado ?? '—'}</td></tr>)}</tbody></table></div></section>}

      <Pestanas opciones={opciones} activa={activa} alCambiar={setPestana} />

      {activa === 'cuadro' &&
        (cuadro.isPending ? (
          <Cargando />
        ) : cuadro.isError ? (
          <Aviso tono="error">{mensajeDeError(cuadro.error)}</Aviso>
        ) : (
          <Cuadro fases={cuadro.data.data} tablas={cuadro.data.tablas} torneo={datos} />
        ))}

      {activa === 'equipos' && <EquiposDelTorneo slug={slug} empezo={empezo} individual={datos.modalidad === 'individual'} />}

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

            {mia.puedo_pagar && mia.espera_pago && (
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
            <Boton onClick={() => setDialogo(true)}>{torneo.modalidad === 'individual' ? 'Inscribirme' : 'Inscribir equipo'}</Boton>
          )
        )}
      </div>

      <DialogoDeInscripcion
        abierto={dialogo}
        torneo={torneo}
        alCerrar={() => setDialogo(false)}
        alInscribir={(mensaje, referencia) => {
          setDialogo(false)
          setAviso({ tono: 'exito', texto: mensaje })
          refrescar()
          if (referencia) navegar(`/panel/pagos/${referencia}`)
        }}
      />
    </div>
  )
}

function DialogoDeInscripcion({ abierto, torneo, alCerrar, alInscribir }) {
  const [elegido, setElegido] = useState(null)
  const [error, setError] = useState('')

  const individual = torneo.modalidad === 'individual'
  const equipos = useQuery({ queryKey: ['mis-equipos'], queryFn: listarMisEquipos, enabled: abierto && !individual })

  const inscribir = useMutation({
    mutationFn: async () => {
      const respuesta = individual ? await inscribirmeIndividual(torneo.slug) : await inscribirEquipo(torneo.slug, elegido)
      const orden = respuesta.data.espera_pago ? await crearOrdenDePago(respuesta.data.id) : null
      return { respuesta, referencia: orden?.data.referencia }
    },
    onSuccess: ({ respuesta, referencia }) => alInscribir(respuesta.message, referencia),
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  const mios = (equipos.data ?? []).filter(
    (equipo) => equipo.soy_capitan && equipo.juego.valor === torneo.juego.valor,
  )

  return (
    <Dialogo abierto={abierto} titulo={individual ? 'Inscripción individual' : 'Inscribir equipo'} descripcion={torneo.nombre} alCerrar={alCerrar}>
      {individual ? <div className="formulario"><p>Participarás con tu apodo. Vincula tu cuenta de {torneo.juego.nombre} antes de inscribirte.</p><p>Inscripción: {torneo.inscripcion.costo_formateado}. El pago es simulado, sin dinero real.</p><Link to="/panel/cuentas">Ver mis cuentas de juego</Link>{error && <Aviso tono="error">{error}</Aviso>}<Boton cargando={inscribir.isPending} onClick={() => inscribir.mutate()}>Confirmar inscripción</Boton></div> : equipos.isError ? <Aviso tono="error">{mensajeDeError(equipos.error)}</Aviso> : equipos.isPending ? (
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

function EquiposDelTorneo({ slug, empezo, individual }) {
  const equipos = useQuery({ queryKey: ['equipos-del-torneo', slug], queryFn: () => obtenerEquiposDelTorneo(slug), refetchInterval: empezo ? false : 5000 })

  if (equipos.isPending) return <Cargando />
  if (equipos.isError) return <Aviso tono="error">{mensajeDeError(equipos.error)}</Aviso>
  if (equipos.data.length === 0) return <EstadoVacio titulo={`Todavía no hay ${individual ? 'jugadores' : 'equipos'} confirmados`} />

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
        <p>{torneo.modalidad === 'individual' ? 'Participación individual.' : 'Participación por equipos.'} {torneo.puntuacion?.formula}.</p>
        <p>{torneo.formato.valor === 'suizo' ? torneo.puntuacion?.suizo : torneo.juego.es_battle_royale ? torneo.puntuacion?.battle_royale : torneo.formato.valor === 'grupos_y_eliminacion' ? torneo.puntuacion?.grupos : 'El ganador avanza automáticamente; perder elimina al participante de esa llave.'}</p>
        {torneo.formato.valor === 'eliminacion_doble' && torneo.flujo_automatico && <p>Si gana el finalista de la llave inferior, se juega una revancha de la gran final.</p>}
        {torneo.puntuacion?.puestos.length > 0 && <div className="tabla-envoltura"><table className="tabla"><thead><tr><th>Posición</th><th>Puntos</th></tr></thead><tbody>{torneo.puntuacion.puestos.map((fila) => <tr key={fila.puesto}><td>{fila.puesto}</td><td>{fila.puntos}</td></tr>)}</tbody></table></div>}
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
        <p className="apagado">Saldo virtual para quien realiza la inscripción. Puedes reutilizarlo en otros torneos o simular su retiro desde tu cartera.</p>
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

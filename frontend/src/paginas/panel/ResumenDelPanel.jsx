import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { listarMisCuentas, listarMisEquipos, listarMisInscripciones, listarMisPartidos, mensajeDeError } from '../../lib/api'
import { useSesion } from '../../contexto/contextoDeSesion'
import { fechaYHora } from '../../lib/formato'
import { juegoDe } from '../../lib/juegos'
import { Aviso, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR } from './enlacesDelPanel'

/**
 * Lo primero que ve el jugador: lo que espera algo de él. Partidos por
 * reportar o confirmar, pagos, check-ins y cuentas que le faltan.
 */
export default function ResumenDelPanel() {
  const { usuario } = useSesion()
  const partidos = useQuery({ queryKey: ['mis-partidos'], queryFn: listarMisPartidos })
  const inscripciones = useQuery({ queryKey: ['mis-inscripciones'], queryFn: listarMisInscripciones })
  const equipos = useQuery({ queryKey: ['mis-equipos'], queryFn: listarMisEquipos })
  const cuentas = useQuery({ queryKey: ['mis-cuentas'], queryFn: listarMisCuentas })

  const consultas = [partidos, inscripciones, equipos, cuentas]

  if (consultas.some((consulta) => consulta.isPending)) return <Cargando />

  const conError = consultas.find((consulta) => consulta.isError)
  if (conError) return <Aviso tono="error">{mensajeDeError(conError.error)}</Aviso>

  const pendientes = construirPendientes({
    usuario,
    partidos: partidos.data,
    inscripciones: inscripciones.data,
    equipos: equipos.data,
    cuentas: cuentas.data,
  })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">{usuario.apodo}</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_JUGADOR} contadores={{ '/panel': pendientes.length }} />

      {pendientes.length === 0 ? (
        <EstadoVacio
          titulo="Nada pendiente"
          accion={
            <Link className="boton boton--solido" to="/torneos">
              Buscar torneo
            </Link>
          }
        />
      ) : (
        <div className="pila pila--chica">
          {pendientes.map((pendiente) => (
            <div className="accion-pendiente" key={pendiente.clave}>
              <div>
                <span className="rotulo">{pendiente.rotulo}</span>
                <strong>{pendiente.titulo}</strong>
                {pendiente.detalle && <span className="apagado">{pendiente.detalle}</span>}
              </div>
              <span className="fila">
                {pendiente.insignia}
                <Link className={`boton ${pendiente.urgente ? 'boton--solido' : 'boton--contorno'} boton--chico`} to={pendiente.a}>
                  {pendiente.accion}
                </Link>
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function construirPendientes({ usuario, partidos, inscripciones, equipos, cuentas }) {
  const pendientes = []

  for (const partido of partidos) {
    const mio = [partido.equipo_a, partido.equipo_b].find((equipo) => equipos.some((propio) => propio.id === equipo?.id))
    const reportes = partido.reportes ?? []
    const yaReporte = reportes.some((reporte) => reporte.equipo_id === mio?.id)
    const rival = mio?.id === partido.equipo_a?.id ? partido.equipo_b : partido.equipo_a
    const capitaneo = equipos.find((equipo) => equipo.id === mio?.id)?.soy_capitan

    let accion = 'Ver partido'
    let urgente = false

    if (capitaneo && partido.estado.valor === 'reportado' && !yaReporte) {
      accion = 'Confirmar resultado'
      urgente = true
    } else if (capitaneo && partido.estado.valor === 'por_jugar') {
      accion = 'Reportar'
      urgente = true
    }

    pendientes.push({
      clave: `partido-${partido.id}`,
      rotulo: partido.torneo?.nombre,
      titulo: `${mio?.nombre ?? ''} vs ${rival?.nombre ?? 'Por definir'}`,
      detalle: partido.programado_para ? fechaYHora(partido.programado_para) : null,
      insignia: <Insignia estado={partido.estado} />,
      accion,
      urgente,
      a: `/partidos/${partido.id}`,
    })
  }

  for (const inscripcion of inscripciones) {
    if (!inscripcion.equipo?.soy_capitan) continue

    const torneo = inscripcion.torneo

    if (inscripcion.espera_pago) {
      pendientes.push({
        clave: `pago-${inscripcion.id}`,
        rotulo: torneo.nombre,
        titulo: `Pagar la inscripción de ${inscripcion.equipo.nombre}`,
        detalle: inscripcion.monto_formateado,
        accion: 'Pagar',
        urgente: true,
        a: '/panel/inscripciones',
      })
    }

    if (inscripcion.estado.valor === 'aprobada' && torneo.check_in.abierto && !inscripcion.check_in_en) {
      pendientes.push({
        clave: `check-in-${inscripcion.id}`,
        rotulo: torneo.nombre,
        titulo: `Check-in de ${inscripcion.equipo.nombre}`,
        detalle: `Empieza ${fechaYHora(torneo.fechas.inicia_en)}`,
        accion: 'Hacer check-in',
        urgente: true,
        a: `/torneos/${torneo.slug}`,
      })
    }
  }

  // Juegos de mis equipos para los que no tengo la cuenta vinculada.
  const vinculados = new Set(cuentas.map((cuenta) => cuenta.juego))
  const faltan = [...new Set(equipos.map((equipo) => equipo.juego.valor))].filter((juego) => !vinculados.has(juego))

  for (const juego of faltan) {
    const equipo = equipos.find((propio) => propio.juego.valor === juego)

    pendientes.push({
      clave: `cuenta-${juego}`,
      rotulo: juegoDe(juego).nombre,
      titulo: `Vincula tu ${equipo.juego.cuenta}`,
      detalle: `Sin ella, ${equipo.nombre} no se puede inscribir.`,
      accion: 'Vincular',
      urgente: false,
      a: '/panel/cuentas',
    })
  }

  if (usuario && equipos.length === 0) {
    pendientes.push({
      clave: 'sin-equipo',
      rotulo: 'Equipos',
      titulo: 'Crea un equipo o únete con un código',
      accion: 'Ir a equipos',
      urgente: true,
      a: '/panel/equipos',
    })
  }

  return pendientes
}


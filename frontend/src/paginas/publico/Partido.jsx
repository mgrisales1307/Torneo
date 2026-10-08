import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Upload } from 'lucide-react'
import {
  cargarBattleRoyale,
  esNoEncontrado,
  esProhibido,
  mensajeDeError,
  obtenerEquiposDelTorneo,
  obtenerPartido,
  reportarResultado,
  resolverPartido,
  programarPartido,
} from '../../lib/api'
import { fechaYHora, paraInputDeFecha } from '../../lib/formato'
import { colorDeJuego } from '../../lib/juegos'
import { EquipoEnLinea, LogoDeEquipo } from '../../componentes/equipo/LogoDeEquipo'
import { AtajosDeMarcador, EntradaDeMarcador, FormularioDeSala } from '../../componentes/torneo/Resultados'
import { Aviso, Boton, Cargando, Campo, CampoDeTexto, Insignia, Tarjeta } from '../../componentes/ui/Basicos'
import NoEncontrada from './NoEncontrada'

export default function Partido() {
  const { id } = useParams()
  const consulta = useQuery({ queryKey: ['partido', id], queryFn: () => obtenerPartido(id), refetchInterval: 10000 })

  if (consulta.isPending) return <Cargando />
  if (esNoEncontrado(consulta.error) || esProhibido(consulta.error)) return <NoEncontrada />
  if (consulta.isError) return <Aviso tono="error">{mensajeDeError(consulta.error)}</Aviso>

  const { data: partido, puedo_reportar: puedoReportar, mi_equipo_id: miEquipo, puedo_resolver: puedoResolver } = consulta.data
  const esBattleRoyale = partido.fase?.tipo === 'battle_royale'
  const admiteReporte = ['por_jugar', 'reportado', 'en_disputa'].includes(partido.estado.valor)

  return (
    <div className="pila pila--media" style={colorDeJuego(partido.torneo.juego)}>
      <header className="pila pila--chica">
        <Link className="etiqueta-juego" to={`/torneos/${partido.torneo.slug}`}>
          {partido.torneo.juego_nombre} · {partido.torneo.nombre}
        </Link>
        <div className="fila">
          <span className="rotulo">
            {partido.llave && partido.llave.valor !== 'superior' ? partido.llave.etiqueta : partido.fase?.nombre}
            {partido.grupo ? ` · Grupo ${partido.grupo}` : ''}
            {partido.llave?.valor !== 'gran_final' && ` · ${esBattleRoyale ? 'Partida' : 'Ronda'} ${partido.ronda}`}
            {!esBattleRoyale && ` · Al mejor de ${partido.mejor_de}`}
          </span>
          <Insignia estado={partido.estado} />
        </div>
      </header>

      {esBattleRoyale ? <SalaDeBattleRoyale partido={partido} puedoResolver={puedoResolver} /> : <Marcador partido={partido} />}

      {!esBattleRoyale && puedoReportar && admiteReporte && <ReporteDelCapitan partido={partido} miEquipo={miEquipo} />}

      {!esBattleRoyale && partido.reportes?.length > 0 && <Reportes partido={partido} />}

      {!esBattleRoyale && puedoResolver && partido.equipo_a && partido.equipo_b && <Resolucion partido={partido} />}
      {puedoResolver && partido.torneo.estado === 'en_curso' && <Programacion key={`fecha-${partido.id}`} partido={partido} />}
    </div>
  )
}

function Marcador({ partido }) {
  const finalizado = partido.estado.valor === 'finalizado'
  const perdedor = (equipo) => finalizado && equipo && partido.ganador_id !== equipo.id

  return (
    <section className="tarjeta">
      <div className="marcador-grande">
        <LadoDelMarcador equipo={partido.equipo_a} perdedor={perdedor(partido.equipo_a)} />

        <div className="marcador-grande__centro">
          {finalizado ? (
            <span className="marcador-grande__cifras">
              {partido.victorias_a}–{partido.victorias_b}
            </span>
          ) : (
            <span className="marcador-grande__cifras apagado">vs</span>
          )}
          {(finalizado || partido.programado_para) && (
            <span className="apagado">{fechaYHora(finalizado ? partido.finalizado_en : partido.programado_para)}</span>
          )}
        </div>

        <LadoDelMarcador equipo={partido.equipo_b} perdedor={perdedor(partido.equipo_b)} />
      </div>

      {partido.detalle?.length > 0 && (
        <div className="mapas" style={{ marginTop: '1.5rem' }}>
          {partido.detalle.map((mapa, indice) => (
            <span className="mapa" key={indice}>
              {mapa.mapa ?? `Mapa ${indice + 1}`}
              <strong>{(mapa.ganador === 'a' ? partido.equipo_a : partido.equipo_b)?.abreviatura}</strong>
            </span>
          ))}
        </div>
      )}
    </section>
  )
}

function LadoDelMarcador({ equipo, perdedor }) {
  if (!equipo) {
    return (
      <div className="marcador-grande__equipo apagado">
        <LogoDeEquipo equipo={null} />
        <strong>Por definir</strong>
      </div>
    )
  }

  return (
    <Link className={`marcador-grande__equipo ${perdedor ? 'marcador-grande__equipo--perdedor' : ''}`} to={equipo.es_individual ? `/jugadores/${encodeURIComponent(equipo.nombre)}` : `/equipos/${equipo.slug}`}>
      <LogoDeEquipo equipo={equipo} />
      <strong>{equipo.nombre}</strong>
    </Link>
  )
}

/**
 * El capitán reporta. Si el rival ya reportó, lo primero que ve es su versión
 * con un botón para confirmarla: es el caso más común y queda a un clic.
 */
function ReporteDelCapitan({ partido, miEquipo }) {
  const cliente = useQueryClient()
  const delRival = partido.reportes?.find((reporte) => reporte.equipo_id !== miEquipo)
  const mio = partido.reportes?.find((reporte) => reporte.equipo_id === miEquipo)

  const [marcador, setMarcador] = useState({ a: String(mio?.victorias_a ?? ''), b: String(mio?.victorias_b ?? '') })
  const [captura, setCaptura] = useState(null)
  const [comentario, setComentario] = useState('')
  const [aviso, setAviso] = useState(null)

  const enviar = useMutation({
    mutationFn: (valores) =>
      reportarResultado(partido.id, {
        victorias_a: valores.a,
        victorias_b: valores.b,
        captura: valores.captura,
        comentario: valores.comentario,
      }),
    onSuccess: (respuesta) => {
      setAviso({ tono: respuesta.data.estado.valor === 'en_disputa' ? 'atencion' : 'exito', texto: respuesta.message })
      cliente.invalidateQueries()
      cliente.invalidateQueries({ queryKey: ['mis-partidos'] })
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  return (
    <Tarjeta titulo={mio ? 'Tu reporte' : 'Reportar resultado'}>
      <div className="formulario">
        {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}

        {delRival && !mio && (
          <div className="accion-pendiente">
            <div>
              <span className="rotulo">El rival reportó</span>
              <strong className="cifra" style={{ fontSize: '1.4rem' }}>
                {delRival.victorias_a}–{delRival.victorias_b}
              </strong>
            </div>
            <Boton
              cargando={enviar.isPending}
              onClick={() => enviar.mutate({ a: delRival.victorias_a, b: delRival.victorias_b })}
            >
              Confirmar {delRival.victorias_a}–{delRival.victorias_b}
            </Boton>
          </div>
        )}

        <AtajosDeMarcador partido={partido} alElegir={setMarcador} />
        <EntradaDeMarcador partido={partido} valor={marcador} alCambiar={setMarcador} />

        <label className="campo">
          <span className="campo__etiqueta">Captura de la pantalla final</span>
          <span className="fila">
            <span className="boton boton--contorno boton--chico">
              <Upload size={15} aria-hidden="true" /> {captura ? captura.name : 'Elegir imagen'}
            </span>
          </span>
          <input
            className="solo-lectores"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(evento) => setCaptura(evento.target.files?.[0] ?? null)}
          />
        </label>

        <CampoDeTexto
          etiqueta="Comentario (opcional)"
          name="comentario"
          value={comentario}
          maxLength={500}
          rows={2}
          onChange={(evento) => setComentario(evento.target.value)}
        />

        <div className="formulario__acciones">
          <Boton
            disabled={marcador.a === '' || marcador.b === ''}
            cargando={enviar.isPending}
            onClick={() => enviar.mutate({ ...marcador, captura, comentario })}
          >
            {mio ? 'Actualizar reporte' : delRival ? 'Reportar otro marcador' : 'Reportar'}
          </Boton>
        </div>
      </div>
    </Tarjeta>
  )
}

function Reportes({ partido }) {
  return (
    <Tarjeta titulo="Reportes">
      <div className="reportes">
        {partido.reportes.map((reporte) => (
          <article className="reporte" key={reporte.equipo_id}>
            <div className="fila fila--entre">
              <strong>{reporte.equipo}</strong>
              <strong className="cifra" style={{ fontSize: '1.3rem' }}>
                {reporte.victorias_a}–{reporte.victorias_b}
              </strong>
            </div>
            <span className="apagado" style={{ fontSize: '.82rem' }}>
              {reporte.reportado_por} · {fechaYHora(reporte.reportado_en)}
            </span>
            {reporte.comentario && <p>{reporte.comentario}</p>}
            {reporte.captura_url && (
              <a className="reporte__captura" href={reporte.captura_url} target="_blank" rel="noreferrer">
                <img src={reporte.captura_url} alt={`Captura de ${reporte.equipo}`} />
              </a>
            )}
          </article>
        ))}
      </div>
    </Tarjeta>
  )
}

function Resolucion({ partido }) {
  const cliente = useQueryClient()
  const [marcador, setMarcador] = useState({ a: '', b: '' })
  const [aviso, setAviso] = useState(null)

  const resolver = useMutation({
    mutationFn: () => resolverPartido(partido.id, { victorias_a: Number(marcador.a), victorias_b: Number(marcador.b) }),
    onSuccess: (respuesta) => {
      setAviso({ tono: 'exito', texto: respuesta.message })
      cliente.invalidateQueries()
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  return (
    <Tarjeta titulo={partido.estado.valor === 'finalizado' ? 'Corregir resultado' : 'Resultado de la organización'}>
      <div className="formulario">
        {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}
        <AtajosDeMarcador partido={partido} alElegir={setMarcador} />
        <EntradaDeMarcador partido={partido} valor={marcador} alCambiar={setMarcador} />
        <div className="formulario__acciones">
          <Boton disabled={marcador.a === '' || marcador.b === ''} cargando={resolver.isPending} onClick={() => resolver.mutate()}>
            Guardar resultado
          </Boton>
        </div>
      </div>
    </Tarjeta>
  )
}

function SalaDeBattleRoyale({ partido, puedoResolver }) {
  const cliente = useQueryClient()
  const [aviso, setAviso] = useState(null)

  const equipos = useQuery({
    queryKey: ['equipos-del-torneo', partido.torneo.slug],
    queryFn: () => obtenerEquiposDelTorneo(partido.torneo.slug),
    enabled: puedoResolver,
  })

  const guardar = useMutation({
    mutationFn: (resultados) => cargarBattleRoyale(partido.id, resultados),
    onSuccess: (respuesta) => {
      setAviso({ tono: 'exito', texto: respuesta.message })
      cliente.invalidateQueries({ queryKey: ['partido', String(partido.id)] })
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  return (
    <>
      {partido.resultados?.length > 0 ? (
        <div className="tabla-envoltura">
          <table className="tabla">
            <thead>
              <tr>
                <th className="numero">Puesto</th>
                <th>Equipo</th>
                <th className="numero">Bajas</th>
                <th className="numero">Puntos</th>
              </tr>
            </thead>
            <tbody>
              {partido.resultados.map((fila) => (
                <tr key={fila.equipo.id}>
                  <td className="numero posicion">{fila.posicion}</td>
                  <td>
                    <EquipoEnLinea equipo={fila.equipo} tamano="1.6rem" />
                  </td>
                  <td className="numero">{fila.bajas}</td>
                  <td className="numero destacada">{fila.puntos}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="apagado">Partida programada para {fechaYHora(partido.programado_para)}.</p>
      )}

      {puedoResolver && (
        <Tarjeta titulo={partido.resultados?.length > 0 ? 'Corregir partida' : 'Cargar partida'}>
          {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}
          {equipos.isError ? <Aviso tono="error">{mensajeDeError(equipos.error)}</Aviso> : equipos.isPending ? (
            <Cargando />
          ) : (
            <FormularioDeSala
              equipos={equipos.data ?? []}
              resultadosPrevios={partido.resultados ?? []}
              cargando={guardar.isPending}
              alGuardar={(resultados) => guardar.mutate(resultados)}
            />
          )}
        </Tarjeta>
      )}
    </>
  )
}

function Programacion({ partido }) {
  const cliente = useQueryClient()
  const [fecha, setFecha] = useState(() => paraInputDeFecha(partido.programado_para))
  const [aviso, setAviso] = useState(null)
  const guardar = useMutation({
    mutationFn: () => programarPartido(partido.id, fecha ? new Date(fecha).toISOString() : null),
    onSuccess: (respuesta) => { cliente.invalidateQueries(); setAviso({ tono: 'exito', texto: respuesta.message }) },
    onError: (error) => setAviso({ tono: 'error', texto: mensajeDeError(error) }),
  })
  return <Tarjeta titulo="Programación"><form className="formulario" onSubmit={(evento) => { evento.preventDefault(); guardar.mutate() }}>{aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}<Campo etiqueta="Fecha y hora del partido" name="fecha-partido" type="datetime-local" value={fecha} onChange={(evento) => setFecha(evento.target.value)} /><Boton type="submit" variante="contorno" cargando={guardar.isPending}>Guardar horario</Boton></form></Tarjeta>
}

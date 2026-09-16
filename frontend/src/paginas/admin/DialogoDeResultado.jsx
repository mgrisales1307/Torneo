import { useMemo, useState } from 'react'
import { useMutation, useQueries, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { mensajeDeError, obtenerEquipo, registrarResultado } from '../../lib/api'
import { Aviso, Boton, Campo, CampoDeSeleccion, Cargando } from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'

const TIPOS = [
  { valor: 'gol', etiqueta: 'Gol' },
  { valor: 'gol_de_penal', etiqueta: 'Gol de penal' },
  { valor: 'autogol', etiqueta: 'Autogol' },
  { valor: 'tarjeta_amarilla', etiqueta: 'Tarjeta amarilla' },
  { valor: 'tarjeta_roja', etiqueta: 'Tarjeta roja' },
]

/**
 * Carga del resultado de un partido.
 *
 * El marcador se calcula a partir de los goles que se van agregando —incluidos
 * los autogoles, que le suman al rival— y el formulario no deja enviar hasta
 * que cuadra. Es la misma regla que aplica el backend; aquí solo se adelanta
 * para no hacer ir y volver a la persona.
 */
export function DialogoDeResultado({ partido, alCerrar }) {
  const cliente = useQueryClient()
  const [eventos, setEventos] = useState([])
  const [observaciones, setObservaciones] = useState('')
  const [error, setError] = useState('')

  const equipos = useQueries({
    queries: [partido?.equipo_local, partido?.equipo_visitante].filter(Boolean).map((equipo) => ({
      queryKey: ['equipo', equipo.slug],
      queryFn: () => obtenerEquipo(equipo.slug),
      staleTime: 60 * 1000,
    })),
  })

  const plantillas = useMemo(() => {
    const porEquipo = new Map()

    equipos.forEach((consulta) => {
      const equipo = consulta.data?.data

      if (equipo) porEquipo.set(equipo.id, equipo.jugadores ?? [])
    })

    return porEquipo
  }, [equipos])

  const marcador = useMemo(() => {
    if (!partido) return { local: 0, visitante: 0 }

    return eventos.reduce(
      (cuenta, evento) => {
        const esDelLocal = Number(evento.equipo_id) === partido.equipo_local.id

        if (evento.tipo === 'gol' || evento.tipo === 'gol_de_penal') {
          return esDelLocal
            ? { ...cuenta, local: cuenta.local + 1 }
            : { ...cuenta, visitante: cuenta.visitante + 1 }
        }

        if (evento.tipo === 'autogol') {
          return esDelLocal
            ? { ...cuenta, visitante: cuenta.visitante + 1 }
            : { ...cuenta, local: cuenta.local + 1 }
        }

        return cuenta
      },
      { local: 0, visitante: 0 },
    )
  }, [eventos, partido])

  const guardar = useMutation({
    mutationFn: (datos) => registrarResultado(partido.id, datos),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['admin'] })
      cliente.invalidateQueries({ queryKey: ['torneo'] })
      cliente.invalidateQueries({ queryKey: ['partido'] })
      alCerrar()
    },
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  if (!partido) return null

  const cargandoPlantillas = equipos.some((consulta) => consulta.isPending)
  const eventoIncompleto = eventos.some((evento) => !evento.equipo_id || !evento.jugador_id)

  function agregarEvento() {
    setEventos((actuales) => [
      ...actuales,
      { clave: crypto.randomUUID(), equipo_id: String(partido.equipo_local.id), jugador_id: '', tipo: 'gol', minuto: '' },
    ])
  }

  function cambiarEvento(clave, campo, valor) {
    setEventos((actuales) =>
      actuales.map((evento) =>
        evento.clave === clave
          ? { ...evento, [campo]: valor, ...(campo === 'equipo_id' ? { jugador_id: '' } : {}) }
          : evento,
      ),
    )
  }

  function alEnviar(evento) {
    evento.preventDefault()
    setError('')

    guardar.mutate({
      goles_local: marcador.local,
      goles_visitante: marcador.visitante,
      observaciones: observaciones || null,
      eventos: eventos.map(({ equipo_id, jugador_id, tipo, minuto }) => ({
        equipo_id: Number(equipo_id),
        jugador_id: Number(jugador_id),
        tipo,
        minuto: minuto === '' ? null : Number(minuto),
      })),
    })
  }

  return (
    <Dialogo
      abierto
      ancho
      titulo={`${partido.equipo_local.nombre} vs ${partido.equipo_visitante.nombre}`}
      descripcion="Agrega los goles y las tarjetas: el marcador se arma solo con lo que registres."
      alCerrar={alCerrar}
    >
      {cargandoPlantillas ? (
        <Cargando texto="Cargando las plantillas…" />
      ) : (
        <form style={{ display: 'grid', gap: '1.2rem' }} onSubmit={alEnviar}>
          <div
            style={{
              display: 'grid',
              placeItems: 'center',
              gap: '.35rem',
              borderRadius: 'var(--radio)',
              padding: '1.1rem',
              background: 'var(--papel-hondo)',
            }}
          >
            <strong style={{ fontFamily: 'var(--serif)', fontSize: '2.2rem', fontWeight: 400 }}>
              {marcador.local} – {marcador.visitante}
            </strong>
            <span style={{ color: 'var(--apagado)', fontSize: '.74rem' }}>
              {partido.equipo_local.abreviatura} vs {partido.equipo_visitante.abreviatura}
            </span>
          </div>

          {eventos.length === 0 && (
            <p style={{ color: 'var(--apagado)', fontSize: '.8rem' }}>
              Sin goles todavía: si el partido terminó 0-0, puedes guardarlo así.
            </p>
          )}

          <div style={{ display: 'grid', gap: '.75rem' }}>
            {eventos.map((evento) => (
              <div
                key={evento.clave}
                style={{
                  display: 'grid',
                  gap: '.6rem',
                  gridTemplateColumns: 'repeat(auto-fit, minmax(9rem, 1fr))',
                  alignItems: 'end',
                  borderTop: '1px solid var(--linea-suave)',
                  paddingTop: '.75rem',
                }}
              >
                <CampoDeSeleccion
                  etiqueta="Equipo"
                  name={`equipo-${evento.clave}`}
                  value={evento.equipo_id}
                  onChange={(cambio) => cambiarEvento(evento.clave, 'equipo_id', cambio.target.value)}
                  opciones={[
                    { valor: String(partido.equipo_local.id), etiqueta: partido.equipo_local.nombre },
                    { valor: String(partido.equipo_visitante.id), etiqueta: partido.equipo_visitante.nombre },
                  ]}
                />

                <CampoDeSeleccion
                  etiqueta="Jugador"
                  name={`jugador-${evento.clave}`}
                  value={evento.jugador_id}
                  onChange={(cambio) => cambiarEvento(evento.clave, 'jugador_id', cambio.target.value)}
                  opciones={[
                    { valor: '', etiqueta: 'Elige un jugador' },
                    ...(plantillas.get(Number(evento.equipo_id)) ?? []).map((jugador) => ({
                      valor: String(jugador.id),
                      etiqueta: `${jugador.numero_camiseta ?? '—'} · ${jugador.nombre_completo}`,
                    })),
                  ]}
                />

                <CampoDeSeleccion
                  etiqueta="Tipo"
                  name={`tipo-${evento.clave}`}
                  value={evento.tipo}
                  onChange={(cambio) => cambiarEvento(evento.clave, 'tipo', cambio.target.value)}
                  opciones={TIPOS}
                />

                <Campo
                  etiqueta="Minuto"
                  name={`minuto-${evento.clave}`}
                  type="number"
                  min={0}
                  max={130}
                  value={evento.minuto}
                  onChange={(cambio) => cambiarEvento(evento.clave, 'minuto', cambio.target.value)}
                />

                <Boton
                  variante="peligro"
                  tamano="chico"
                  type="button"
                  onClick={() => setEventos((actuales) => actuales.filter((otro) => otro.clave !== evento.clave))}
                  aria-label="Quitar este evento"
                >
                  <Trash2 size={15} />
                </Boton>
              </div>
            ))}
          </div>

          <Boton variante="contorno" type="button" onClick={agregarEvento} iconoIzquierda={<Plus size={15} />}>
            Agregar gol o tarjeta
          </Boton>

          <Campo
            etiqueta="Observaciones (opcional)"
            name="observaciones"
            value={observaciones}
            onChange={(cambio) => setObservaciones(cambio.target.value)}
            placeholder="Cambio de cancha, suspensión temporal, etc."
          />

          {error && <Aviso tono="error">{error}</Aviso>}

          <div className="dialogo__acciones">
            <Boton variante="contorno" type="button" onClick={alCerrar}>
              Cancelar
            </Boton>
            <Boton type="submit" cargando={guardar.isPending} disabled={eventoIncompleto}>
              Guardar resultado
            </Boton>
          </div>

          {eventoIncompleto && (
            <p className="campo__pista" style={{ textAlign: 'right' }}>
              Falta elegir el jugador de algún evento.
            </p>
          )}
        </form>
      )}
    </Dialogo>
  )
}

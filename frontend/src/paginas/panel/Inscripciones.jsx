import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CreditCard, Plus, Ticket } from 'lucide-react'
import {
  crearOrdenDePago,
  inscribirEquipo,
  listarMisEquipos,
  listarMisInscripciones,
  listarTorneos,
  mensajeDeError,
  retirarInscripcion,
} from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import {
  Aviso,
  Boton,
  CampoDeSeleccion,
  Cargando,
  EstadoVacio,
  Insignia,
  Tarjeta,
} from '../../componentes/ui/Basicos'
import { Escudo } from '../../componentes/ui/Escudo'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_DUENO } from './enlacesDelPanel'

export default function Inscripciones() {
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [seleccion, setSeleccion] = useState({ torneo: '', equipo: '' })
  const [aviso, setAviso] = useState('')
  const [error, setError] = useState('')

  const inscripciones = useQuery({ queryKey: ['mis-inscripciones'], queryFn: listarMisInscripciones })
  const equipos = useQuery({ queryKey: ['mis-equipos'], queryFn: listarMisEquipos })
  const torneosAbiertos = useQuery({
    queryKey: ['torneos', { estado: 'inscripciones_abiertas' }],
    queryFn: () => listarTorneos({ estado: 'inscripciones_abiertas' }),
  })

  function refrescar(mensaje) {
    cliente.invalidateQueries({ queryKey: ['mis-inscripciones'] })
    cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
    setAviso(mensaje)
    setError('')
  }

  const inscribir = useMutation({
    mutationFn: ({ torneo, equipo }) => inscribirEquipo(torneo, Number(equipo)),
    onSuccess: () => {
      refrescar('Inscripción creada. Si el torneo tiene costo, paga para reservar el cupo.')
      setSeleccion({ torneo: '', equipo: '' })
    },
    onError: (fallo) => {
      setAviso('')
      setError(mensajeDeError(fallo))
    },
  })

  const retirar = useMutation({
    mutationFn: retirarInscripcion,
    onSuccess: () => refrescar('El equipo quedó retirado del torneo.'),
    onError: (fallo) => {
      setAviso('')
      setError(mensajeDeError(fallo))
    },
  })

  const pagar = useMutation({
    mutationFn: crearOrdenDePago,
    onSuccess: (respuesta) => navegar(respuesta.cobro.url),
    onError: (fallo) => {
      setAviso('')
      setError(mensajeDeError(fallo))
    },
  })

  const torneos = torneosAbiertos.data?.data ?? []
  const misEquipos = equipos.data ?? []

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Inscripciones y pagos</h1>
          <p>Inscribe tus equipos en los torneos abiertos y paga para reservar el cupo.</p>
        </div>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_DUENO} />

      {aviso && <Aviso tono="exito">{aviso}</Aviso>}
      {error && <Aviso tono="error">{error}</Aviso>}

      <div className="rejilla rejilla--panel" style={{ marginTop: '1.25rem' }}>
        <div>
          {inscripciones.isPending && <Cargando />}

          {inscripciones.data?.length === 0 && (
            <EstadoVacio icono={<Ticket size={28} />} titulo="Todavía no has inscrito ningún equipo">
              Elige un torneo abierto en el panel de la derecha y presenta a tu equipo.
            </EstadoVacio>
          )}

          <div style={{ display: 'grid', gap: '1rem' }}>
            {(inscripciones.data ?? []).map((inscripcion) => (
              <Tarjeta key={inscripcion.id}>
                <div className="tarjeta__titulo">
                  <div className="fila-equipo">
                    <Escudo
                      equipo={{
                        ...inscripcion.equipo,
                        escudo_url: inscripcion.equipo?.imagenes?.escudo_url,
                      }}
                      tamano="medio"
                    />
                    <div>
                      <strong>{inscripcion.equipo?.nombre}</strong>
                      <span>
                        <Link to={`/torneos/${inscripcion.torneo?.slug}`}>{inscripcion.torneo?.nombre}</Link>
                      </span>
                    </div>
                  </div>
                  <Insignia estado={inscripcion.estado} />
                </div>

                <div className="detalle-lista">
                  <div>
                    <span>Valor de la inscripción</span>
                    <strong>{inscripcion.monto_formateado}</strong>
                  </div>
                  <div>
                    <span>Inscrito el</span>
                    <strong>{fechaCorta(inscripcion.creado_en)}</strong>
                  </div>
                  {inscripcion.pagada_en && (
                    <div>
                      <span>Pagado el</span>
                      <strong>{fechaCorta(inscripcion.pagada_en)}</strong>
                    </div>
                  )}
                  {inscripcion.notas_revision && (
                    <div>
                      <span>Nota de la organización</span>
                      <strong>{inscripcion.notas_revision}</strong>
                    </div>
                  )}
                </div>

                <div className="barra-acciones" style={{ marginTop: '1.1rem' }}>
                  {inscripcion.espera_pago && (
                    <Boton
                      iconoIzquierda={<CreditCard size={15} />}
                      cargando={pagar.isPending}
                      onClick={() => pagar.mutate(inscripcion.id)}
                    >
                      Pagar inscripción
                    </Boton>
                  )}

                  {['pendiente_pago', 'pendiente_revision'].includes(inscripcion.estado.valor) && (
                    <Boton
                      variante="contorno"
                      onClick={() => {
                        if (window.confirm('¿Retirar este equipo del torneo?')) {
                          retirar.mutate(inscripcion.id)
                        }
                      }}
                    >
                      Retirar
                    </Boton>
                  )}
                </div>
              </Tarjeta>
            ))}
          </div>
        </div>

        <aside>
          <Tarjeta titulo="Inscribir un equipo">
            {torneos.length === 0 ? (
              <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
                Ahora mismo no hay torneos con inscripciones abiertas. Vuelve a mirar pronto.
              </p>
            ) : misEquipos.length === 0 ? (
              <p style={{ color: 'var(--apagado)', fontSize: '.82rem' }}>
                Primero <Link to="/panel">crea un equipo</Link> y cárgale su plantilla.
              </p>
            ) : (
              <form
                style={{ display: 'grid', gap: '1rem' }}
                onSubmit={(evento) => {
                  evento.preventDefault()
                  inscribir.mutate(seleccion)
                }}
              >
                <CampoDeSeleccion
                  etiqueta="Torneo"
                  name="torneo"
                  value={seleccion.torneo}
                  onChange={(evento) => setSeleccion({ ...seleccion, torneo: evento.target.value })}
                  opciones={[
                    { valor: '', etiqueta: 'Elige un torneo' },
                    ...torneos.map((torneo) => ({
                      valor: torneo.slug,
                      etiqueta: `${torneo.nombre} · ${torneo.inscripcion.costo_formateado}`,
                    })),
                  ]}
                  required
                />

                <CampoDeSeleccion
                  etiqueta="Equipo"
                  name="equipo"
                  value={seleccion.equipo}
                  onChange={(evento) => setSeleccion({ ...seleccion, equipo: evento.target.value })}
                  opciones={[
                    { valor: '', etiqueta: 'Elige un equipo' },
                    ...misEquipos.map((equipo) => ({
                      valor: String(equipo.id),
                      etiqueta: `${equipo.nombre} (${equipo.jugadores_count ?? 0} jugadores)`,
                    })),
                  ]}
                  required
                />

                <Boton
                  type="submit"
                  iconoIzquierda={<Plus size={15} />}
                  cargando={inscribir.isPending}
                  disabled={!seleccion.torneo || !seleccion.equipo}
                >
                  Inscribir
                </Boton>

                <p className="campo__pista">
                  El equipo debe cumplir el mínimo de jugadores activos que exija el torneo.
                </p>
              </form>
            )}
          </Tarjeta>
        </aside>
      </div>
    </>
  )
}

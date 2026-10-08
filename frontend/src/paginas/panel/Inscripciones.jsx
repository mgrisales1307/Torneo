import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { crearOrdenDePago, hacerCheckIn, listarMisInscripciones, mensajeDeError, retirarInscripcion } from '../../lib/api'
import { fechaYHora, relativo } from '../../lib/formato'
import { EquipoEnLinea } from '../../componentes/equipo/LogoDeEquipo'
import { Aviso, Boton, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR } from './enlacesDelPanel'

export default function Inscripciones() {
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [aviso, setAviso] = useState(null)

  const inscripciones = useQuery({ queryKey: ['mis-inscripciones'], queryFn: listarMisInscripciones })

  function refrescar(respuesta) {
    cliente.invalidateQueries({ queryKey: ['mis-inscripciones'] })
    cliente.invalidateQueries({ queryKey: ['cartera'] })
    cliente.invalidateQueries({ queryKey: ['torneo'] })
    setAviso({ tono: 'exito', texto: respuesta.message })
  }

  const alFallar = (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) })

  const pagar = useMutation({
    mutationFn: crearOrdenDePago,
    onSuccess: (respuesta) => navegar(`/panel/pagos/${respuesta.data.referencia}`),
    onError: alFallar,
  })
  const checkIn = useMutation({ mutationFn: hacerCheckIn, onSuccess: refrescar, onError: alFallar })
  const retirar = useMutation({ mutationFn: retirarInscripcion, onSuccess: refrescar, onError: alFallar })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Inscripciones</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_JUGADOR} />

      {aviso && (
        <div style={{ marginBottom: '1.25rem' }}>
          <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>
        </div>
      )}

      {inscripciones.isPending ? (
        <Cargando />
      ) : inscripciones.isError ? (
        <Aviso tono="error">{mensajeDeError(inscripciones.error)}</Aviso>
      ) : inscripciones.data.length === 0 ? (
        <EstadoVacio
          titulo="Sin inscripciones"
          accion={
            <Link className="boton boton--solido" to="/torneos">
              Buscar torneo
            </Link>
          }
        />
      ) : (
        <div className="pila pila--chica">
          {inscripciones.data.map((inscripcion) => {
            const torneo = inscripcion.torneo
            const capitan = inscripcion.equipo.soy_capitan
            const cerrada = ['rechazada', 'retirada'].includes(inscripcion.estado.valor)
            const puedeCheckIn =
              capitan && inscripcion.estado.valor === 'aprobada' && torneo.check_in.minutos > 0 && !inscripcion.check_in_en

            return (
              <div className="accion-pendiente" key={inscripcion.id}>
                <div>
                  <Link className="rotulo" to={`/torneos/${torneo.slug}`}>
                    {torneo.juego.nombre} · {torneo.nombre}
                  </Link>
                  <EquipoEnLinea equipo={inscripcion.equipo} />
                  <span className="apagado">
                    {inscripcion.monto_formateado} · empieza {fechaYHora(torneo.fechas.inicia_en)}
                  </span>
                  {inscripcion.notas_revision && <span className="apagado">«{inscripcion.notas_revision}»</span>}
                </div>

                <span className="fila">
                  <Insignia estado={inscripcion.estado} />
                  {inscripcion.check_in_en && <Insignia tono="exito">Check-in</Insignia>}

                  {inscripcion.puedo_pagar && inscripcion.espera_pago && (
                    <Boton tamano="chico" cargando={pagar.isPending} onClick={() => pagar.mutate(inscripcion.id)}>
                      Pagar
                    </Boton>
                  )}

                  {puedeCheckIn &&
                    (torneo.check_in.abierto ? (
                      <Boton tamano="chico" cargando={checkIn.isPending} onClick={() => checkIn.mutate(inscripcion.id)}>
                        Check-in
                      </Boton>
                    ) : (
                      torneo.check_in.abre_en && <span className="apagado">Check-in {relativo(torneo.check_in.abre_en)}</span>
                    ))}

                  {capitan && !cerrada && !['en_curso', 'finalizado'].includes(torneo.estado.valor) && (
                    <Boton
                      variante="fantasma"
                      tamano="chico"
                      onClick={() => {
                        if (window.confirm(`¿Retirar a ${inscripcion.equipo.nombre} de ${torneo.nombre}?`)) {
                          retirar.mutate(inscripcion.id)
                        }
                      }}
                    >
                      Retirar
                    </Boton>
                  )}
                </span>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

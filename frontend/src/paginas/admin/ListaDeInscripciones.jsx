import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { aprobarInscripcion, mensajeDeError, rechazarInscripcion } from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import { EquipoEnLinea } from '../../componentes/equipo/LogoDeEquipo'
import { Aviso, Boton, CampoDeTexto, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'

/**
 * Inscripciones con sus acciones de revisión. La usan el listado general y la
 * ficha de cada torneo.
 */
export function ListaDeInscripciones({ inscripciones, mostrarTorneo = true }) {
  const cliente = useQueryClient()
  const [rechazando, setRechazando] = useState(null)
  const [notas, setNotas] = useState('')
  const [aviso, setAviso] = useState(null)

  function refrescar(respuesta) {
    cliente.invalidateQueries({ queryKey: ['inscripciones-admin'] })
    cliente.invalidateQueries({ queryKey: ['resumen-admin'] })
    setAviso({ tono: 'exito', texto: respuesta.message })
    setRechazando(null)
    setNotas('')
  }

  const alFallar = (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) })

  const aprobar = useMutation({ mutationFn: (id) => aprobarInscripcion(id), onSuccess: refrescar, onError: alFallar })
  const rechazar = useMutation({
    mutationFn: () => rechazarInscripcion(rechazando.id, notas),
    onSuccess: refrescar,
    onError: alFallar,
  })

  if (inscripciones.length === 0) return <EstadoVacio titulo="Sin inscripciones" />

  return (
    <div className="pila pila--chica">
      {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}

      <div className="tabla-envoltura">
        <table className="tabla">
          <thead>
            <tr>
              <th>Equipo</th>
              {mostrarTorneo && <th>Torneo</th>}
              <th className="numero">Jugadores</th>
              <th>Pago</th>
              <th>Estado</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {inscripciones.map((inscripcion) => (
              <tr key={inscripcion.id}>
                <td>
                  <EquipoEnLinea equipo={inscripcion.equipo} tamano="1.75rem" />
                </td>
                {mostrarTorneo && (
                  <td>
                    <Link to={`/organizacion/torneos/${inscripcion.torneo.slug}`}>{inscripcion.torneo.nombre}</Link>
                  </td>
                )}
                <td className="numero">{inscripcion.equipo.miembros_count}</td>
                <td>
                  {inscripcion.monto_formateado}
                  {inscripcion.pagada_en && <span className="apagado"> · {fechaCorta(inscripcion.pagada_en)}</span>}
                </td>
                <td>
                  <span className="fila">
                    <Insignia estado={inscripcion.estado} />
                    {inscripcion.check_in_en && <Insignia tono="exito">Check-in</Insignia>}
                  </span>
                </td>
                <td>
                  {inscripcion.estado.valor === 'pendiente_revision' && (
                    <span className="fila">
                      <Boton tamano="chico" cargando={aprobar.isPending} onClick={() => aprobar.mutate(inscripcion.id)}>
                        Aprobar
                      </Boton>
                      <Boton variante="fantasma" tamano="chico" onClick={() => setRechazando(inscripcion)}>
                        Rechazar
                      </Boton>
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Dialogo
        abierto={rechazando !== null}
        titulo="Rechazar inscripción"
        descripcion={rechazando?.equipo.nombre}
        alCerrar={() => setRechazando(null)}
      >
        <div className="formulario">
          <CampoDeTexto
            etiqueta="Motivo (lo ve el capitán)"
            name="notas"
            value={notas}
            rows={3}
            onChange={(evento) => setNotas(evento.target.value)}
          />
          <div className="formulario__acciones">
            <Boton variante="fantasma" onClick={() => setRechazando(null)}>
              Cancelar
            </Boton>
            <Boton variante="peligro" disabled={notas.trim().length < 5} cargando={rechazar.isPending} onClick={() => rechazar.mutate()}>
              Rechazar
            </Boton>
          </div>
        </div>
      </Dialogo>
    </div>
  )
}

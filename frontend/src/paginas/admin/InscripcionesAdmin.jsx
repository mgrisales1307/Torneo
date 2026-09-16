import { useState } from 'react'
import { Link } from 'react-router-dom'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, Inbox, X } from 'lucide-react'
import {
  aprobarInscripcion,
  listarInscripcionesAdmin,
  mensajeDeError,
  rechazarInscripcion,
} from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import {
  Aviso,
  Boton,
  CampoDeTexto,
  Cargando,
  EstadoVacio,
  Insignia,
} from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'
import { Escudo } from '../../componentes/ui/Escudo'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

const FILTROS = [
  { valor: 'pendiente_revision', etiqueta: 'Por revisar' },
  { valor: 'pendiente_pago', etiqueta: 'Sin pagar' },
  { valor: 'aprobada', etiqueta: 'Aprobadas' },
  { valor: 'rechazada', etiqueta: 'Rechazadas' },
  { valor: '', etiqueta: 'Todas' },
]

export default function InscripcionesAdmin() {
  const cliente = useQueryClient()
  const [estado, setEstado] = useState('pendiente_revision')
  const [rechazando, setRechazando] = useState(null)
  const [motivo, setMotivo] = useState('')
  const [aviso, setAviso] = useState('')
  const [error, setError] = useState('')

  const inscripciones = useQuery({
    queryKey: ['admin', 'inscripciones', estado],
    queryFn: () => listarInscripcionesAdmin(estado ? { estado } : {}),
    placeholderData: keepPreviousData,
  })

  function refrescar(mensaje) {
    cliente.invalidateQueries({ queryKey: ['admin'] })
    cliente.invalidateQueries({ queryKey: ['torneo'] })
    setAviso(mensaje)
    setError('')
  }

  const aprobar = useMutation({
    mutationFn: (id) => aprobarInscripcion(id),
    onSuccess: () => refrescar('Inscripción aprobada: el equipo ya está dentro del torneo.'),
    onError: (fallo) => {
      setAviso('')
      setError(mensajeDeError(fallo))
    },
  })

  const rechazar = useMutation({
    mutationFn: ({ id, notas }) => rechazarInscripcion(id, notas),
    onSuccess: () => {
      refrescar('Inscripción rechazada. El dueño del equipo verá el motivo.')
      setRechazando(null)
      setMotivo('')
    },
    onError: (fallo) => setError(mensajeDeError(fallo)),
  })

  const filas = inscripciones.data?.data ?? []

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Inscripciones</h1>
          <p>Revisa los equipos que pagaron y decide cuáles entran al torneo.</p>
        </div>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      <div className="pestanas">
        {FILTROS.map((filtro) => (
          <button
            key={filtro.valor || 'todas'}
            className={`pestana ${estado === filtro.valor ? 'pestana--activa' : ''}`}
            type="button"
            onClick={() => setEstado(filtro.valor)}
          >
            {filtro.etiqueta}
          </button>
        ))}
      </div>

      <div style={{ marginTop: '1.5rem' }}>
        {aviso && <Aviso tono="exito">{aviso}</Aviso>}
        {error && <Aviso tono="error">{error}</Aviso>}

        {inscripciones.isPending && <Cargando />}

        {!inscripciones.isPending && filas.length === 0 && (
          <EstadoVacio icono={<Inbox size={28} />} titulo="No hay inscripciones en este estado">
            Cuando un equipo pague su inscripción, aparecerá aquí para que la revises.
          </EstadoVacio>
        )}

        {filas.length > 0 && (
          <div className="tabla-envoltura">
            <table className="tabla">
              <thead>
                <tr>
                  <th scope="col">Equipo</th>
                  <th scope="col">Torneo</th>
                  <th scope="col">Dueño</th>
                  <th scope="col">Monto</th>
                  <th scope="col">Estado</th>
                  <th scope="col">
                    <span className="solo-lectores">Acciones</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {filas.map((inscripcion) => (
                  <tr key={inscripcion.id}>
                    <td>
                      <span className="fila-equipo">
                        <Escudo
                          equipo={{
                            ...inscripcion.equipo,
                            escudo_url: inscripcion.equipo?.imagenes?.escudo_url,
                          }}
                          tamano="chico"
                        />
                        <span>
                          <strong>{inscripcion.equipo?.nombre}</strong>
                          <span>{inscripcion.equipo?.jugadores_count ?? 0} jugadores</span>
                        </span>
                      </span>
                    </td>
                    <td>
                      <Link to={`/organizacion/torneos/${inscripcion.torneo?.slug}`}>
                        {inscripcion.torneo?.nombre}
                      </Link>
                    </td>
                    <td>
                      {inscripcion.equipo?.dueno?.nombre ?? '—'}
                      <div style={{ color: 'var(--apagado-claro)', fontSize: '.7rem' }}>
                        {fechaCorta(inscripcion.creado_en)}
                      </div>
                    </td>
                    <td>{inscripcion.monto_formateado}</td>
                    <td>
                      <Insignia estado={inscripcion.estado} />
                    </td>
                    <td>
                      {inscripcion.estado.valor === 'pendiente_revision' && (
                        <div className="barra-acciones">
                          <Boton
                            tamano="chico"
                            iconoIzquierda={<Check size={14} />}
                            cargando={aprobar.isPending && aprobar.variables === inscripcion.id}
                            onClick={() => aprobar.mutate(inscripcion.id)}
                          >
                            Aprobar
                          </Boton>
                          <Boton
                            variante="peligro"
                            tamano="chico"
                            iconoIzquierda={<X size={14} />}
                            onClick={() => {
                              setRechazando(inscripcion)
                              setMotivo('')
                            }}
                          >
                            Rechazar
                          </Boton>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <Dialogo
        abierto={rechazando !== null}
        titulo="Rechazar inscripción"
        descripcion="El motivo lo verá el dueño del equipo en su panel, así que sé concreto."
        alCerrar={() => setRechazando(null)}
      >
        <form
          style={{ display: 'grid', gap: '1rem' }}
          onSubmit={(evento) => {
            evento.preventDefault()
            rechazar.mutate({ id: rechazando.id, notas: motivo })
          }}
        >
          <CampoDeTexto
            etiqueta="Motivo"
            name="motivo"
            value={motivo}
            onChange={(evento) => setMotivo(evento.target.value)}
            placeholder="Faltan los documentos de tres jugadores."
            required
            minLength={5}
          />

          <div className="dialogo__acciones">
            <Boton variante="contorno" type="button" onClick={() => setRechazando(null)}>
              Cancelar
            </Boton>
            <Boton variante="peligro" type="submit" cargando={rechazar.isPending}>
              Rechazar inscripción
            </Boton>
          </div>
        </form>
      </Dialogo>
    </>
  )
}

import { useState } from 'react'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { listarTorneos, mensajeDeError } from '../../lib/api'
import { colorDeJuego, JUEGOS } from '../../lib/juegos'
import { TarjetaDeTorneo } from '../../componentes/torneo/TarjetaDeTorneo'
import { Aviso, Boton, Cargando, EstadoVacio } from '../../componentes/ui/Basicos'

const ESTADOS = [
  { valor: '', etiqueta: 'Todos' },
  { valor: 'en_curso', etiqueta: 'En curso' },
  { valor: 'inscripciones_abiertas', etiqueta: 'Inscripciones abiertas' },
  { valor: 'finalizado', etiqueta: 'Finalizados' },
]

export default function Torneos() {
  const [juego, setJuego] = useState('')
  const [estado, setEstado] = useState('')
  const [buscar, setBuscar] = useState('')
  const [pagina, setPagina] = useState(1)

  const parametros = { juego: juego || undefined, estado: estado || undefined, buscar: buscar || undefined, page: pagina }

  const consulta = useQuery({
    queryKey: ['torneos', parametros],
    queryFn: () => listarTorneos(parametros),
    placeholderData: keepPreviousData,
  })

  function filtrar(cambio) {
    cambio()
    setPagina(1)
  }

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Torneos</h1>
      </header>

      <div className="pila pila--chica" style={{ marginBottom: '2rem' }}>
        <div className="chips" role="group" aria-label="Juego">
          <button type="button" className={`chip ${juego === '' ? 'chip--activo' : ''}`} onClick={() => filtrar(() => setJuego(''))}>
            Todos los juegos
          </button>
          {JUEGOS.map((opcion) => (
            <button
              key={opcion.valor}
              type="button"
              className={`chip ${juego === opcion.valor ? 'chip--activo' : ''}`}
              style={colorDeJuego(opcion.valor)}
              onClick={() => filtrar(() => setJuego(opcion.valor))}
            >
              <span className="chip__punto" aria-hidden="true" /> {opcion.nombre}
            </button>
          ))}
        </div>

        <div className="fila">
          <div className="chips" role="group" aria-label="Estado">
            {ESTADOS.map((opcion) => (
              <button
                key={opcion.valor}
                type="button"
                className={`chip ${estado === opcion.valor ? 'chip--activo' : ''}`}
                onClick={() => filtrar(() => setEstado(opcion.valor))}
              >
                {opcion.etiqueta}
              </button>
            ))}
          </div>
          <input
            className="campo__control"
            style={{ maxWidth: '18rem', marginLeft: 'auto' }}
            type="search"
            placeholder="Buscar por nombre"
            aria-label="Buscar torneo"
            value={buscar}
            onChange={(evento) => filtrar(() => setBuscar(evento.target.value))}
          />
        </div>
      </div>

      {consulta.isPending ? (
        <Cargando />
      ) : consulta.isError ? (
        <Aviso tono="error">{mensajeDeError(consulta.error)}</Aviso>
      ) : consulta.data.data.length === 0 ? (
        <EstadoVacio titulo="No hay torneos con esos filtros" />
      ) : (
        <>
          <div className="rejilla">
            {consulta.data.data.map((torneo) => (
              <TarjetaDeTorneo key={torneo.id} torneo={torneo} />
            ))}
          </div>

          {consulta.data.meta.last_page > 1 && (
            <div className="fila" style={{ justifyContent: 'center', marginTop: '2rem' }}>
              <Boton variante="contorno" tamano="chico" disabled={pagina === 1} onClick={() => setPagina(pagina - 1)}>
                Anterior
              </Boton>
              <span className="apagado">
                {pagina} de {consulta.data.meta.last_page}
              </span>
              <Boton
                variante="contorno"
                tamano="chico"
                disabled={pagina === consulta.data.meta.last_page}
                onClick={() => setPagina(pagina + 1)}
              >
                Siguiente
              </Boton>
            </div>
          )}
        </>
      )}
    </div>
  )
}

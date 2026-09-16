import { useState } from 'react'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { Search, Trophy } from 'lucide-react'
import { listarTorneos } from '../../lib/api'
import { Aviso, Boton, Cargando, EstadoVacio } from '../../componentes/ui/Basicos'
import { TarjetaDeTorneo } from '../../componentes/torneo/TarjetaDeTorneo'

const ESTADOS = [
  { valor: '', etiqueta: 'Todos' },
  { valor: 'en_curso', etiqueta: 'En curso' },
  { valor: 'inscripciones_abiertas', etiqueta: 'Inscripciones abiertas' },
  { valor: 'inscripciones_cerradas', etiqueta: 'Por comenzar' },
  { valor: 'finalizado', etiqueta: 'Finalizados' },
]

export default function Torneos() {
  const [filtros, setFiltros] = useState({ estado: '', buscar: '', page: 1 })
  const [textoDeBusqueda, setTextoDeBusqueda] = useState('')

  const { data, isPending, isError } = useQuery({
    queryKey: ['torneos', filtros],
    queryFn: () => listarTorneos(filtros),
    placeholderData: keepPreviousData,
  })

  const torneos = data?.data ?? []
  const meta = data?.meta

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Torneos</h1>
          <p>Todas las competencias de Juegos Zone, en curso y por venir.</p>
        </div>

        <form
          style={{ display: 'flex', gap: '.5rem' }}
          onSubmit={(evento) => {
            evento.preventDefault()
            setFiltros((actuales) => ({ ...actuales, buscar: textoDeBusqueda, page: 1 }))
          }}
        >
          <input
            className="campo__control"
            type="search"
            value={textoDeBusqueda}
            onChange={(evento) => setTextoDeBusqueda(evento.target.value)}
            placeholder="Buscar por nombre o ciudad"
            aria-label="Buscar torneos"
          />
          <Boton variante="contorno" type="submit" aria-label="Buscar">
            <Search size={16} />
          </Boton>
        </form>
      </header>

      <div className="pestanas" role="tablist" aria-label="Filtrar por estado">
        {ESTADOS.map((estado) => (
          <button
            key={estado.valor || 'todos'}
            className={`pestana ${filtros.estado === estado.valor ? 'pestana--activa' : ''}`}
            type="button"
            role="tab"
            aria-selected={filtros.estado === estado.valor}
            onClick={() => setFiltros((actuales) => ({ ...actuales, estado: estado.valor, page: 1 }))}
          >
            {estado.etiqueta}
          </button>
        ))}
      </div>

      <div style={{ marginTop: '1.75rem' }}>
        {isError && <Aviso tono="error">No pudimos cargar los torneos. Inténtalo de nuevo.</Aviso>}
        {isPending && <Cargando />}

        {!isPending && torneos.length === 0 && (
          <EstadoVacio icono={<Trophy size={28} />} titulo="No hay torneos que coincidan">
            Prueba con otro filtro o quita la búsqueda.
          </EstadoVacio>
        )}

        {torneos.length > 0 && (
          <div className="rejilla rejilla--tres">
            {torneos.map((torneo) => (
              <TarjetaDeTorneo key={torneo.id} torneo={torneo} />
            ))}
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="paginacion">
            <Boton
              variante="contorno"
              tamano="chico"
              disabled={meta.current_page <= 1}
              onClick={() => setFiltros((actuales) => ({ ...actuales, page: actuales.page - 1 }))}
            >
              Anterior
            </Boton>
            <span>
              Página {meta.current_page} de {meta.last_page}
            </span>
            <Boton
              variante="contorno"
              tamano="chico"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setFiltros((actuales) => ({ ...actuales, page: actuales.page + 1 }))}
            >
              Siguiente
            </Boton>
          </div>
        )}
      </div>
    </>
  )
}

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { listarInscripcionesAdmin, mensajeDeError } from '../../lib/api'
import { Aviso, Cargando } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'
import { ListaDeInscripciones } from './ListaDeInscripciones'

const FILTROS = [
  { valor: 'pendiente_revision', etiqueta: 'Por revisar' },
  { valor: 'pendiente_pago', etiqueta: 'Sin pagar' },
  { valor: 'aprobada', etiqueta: 'Aprobadas' },
  { valor: '', etiqueta: 'Todas' },
]

export default function InscripcionesAdmin() {
  const [estado, setEstado] = useState('pendiente_revision')

  const inscripciones = useQuery({
    queryKey: ['inscripciones-admin', estado],
    queryFn: () => listarInscripcionesAdmin({ estado: estado || undefined }),
  })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Inscripciones</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      <div className="chips" style={{ marginBottom: '1.5rem' }}>
        {FILTROS.map((filtro) => (
          <button
            key={filtro.valor}
            type="button"
            className={`chip ${estado === filtro.valor ? 'chip--activo' : ''}`}
            onClick={() => setEstado(filtro.valor)}
          >
            {filtro.etiqueta}
          </button>
        ))}
      </div>

      {inscripciones.isPending ? (
        <Cargando />
      ) : inscripciones.isError ? (
        <Aviso tono="error">{mensajeDeError(inscripciones.error)}</Aviso>
      ) : (
        <ListaDeInscripciones inscripciones={inscripciones.data.data} />
      )}
    </div>
  )
}

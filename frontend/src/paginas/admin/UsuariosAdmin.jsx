import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Search, Users } from 'lucide-react'
import { listarUsuarios, mensajeDeError } from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import { Aviso, Boton, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

const ROLES = [
  { valor: '', etiqueta: 'Todos' },
  { valor: 'admin', etiqueta: 'Administración' },
  { valor: 'empleado', etiqueta: 'Organización' },
  { valor: 'dueno', etiqueta: 'Dueños' },
  { valor: 'espectador', etiqueta: 'Espectadores' },
]

export default function UsuariosAdmin() {
  const [filtros, setFiltros] = useState({ rol: '', buscar: '', page: 1 })
  const [texto, setTexto] = useState('')

  const usuarios = useQuery({
    queryKey: ['admin', 'usuarios', filtros],
    queryFn: () => listarUsuarios(filtros),
    placeholderData: keepPreviousData,
  })

  const filas = usuarios.data?.data ?? []
  const meta = usuarios.data?.meta

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Usuarios</h1>
          <p>Las cuentas registradas en la plataforma.</p>
        </div>

        <form
          style={{ display: 'flex', gap: '.5rem' }}
          onSubmit={(evento) => {
            evento.preventDefault()
            setFiltros((actuales) => ({ ...actuales, buscar: texto, page: 1 }))
          }}
        >
          <input
            className="campo__control"
            type="search"
            value={texto}
            onChange={(evento) => setTexto(evento.target.value)}
            placeholder="Buscar por nombre o correo"
            aria-label="Buscar usuarios"
          />
          <Boton variante="contorno" type="submit" aria-label="Buscar">
            <Search size={16} />
          </Boton>
        </form>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      <div className="pestanas">
        {ROLES.map((rol) => (
          <button
            key={rol.valor || 'todos'}
            className={`pestana ${filtros.rol === rol.valor ? 'pestana--activa' : ''}`}
            type="button"
            onClick={() => setFiltros((actuales) => ({ ...actuales, rol: rol.valor, page: 1 }))}
          >
            {rol.etiqueta}
          </button>
        ))}
      </div>

      <div style={{ marginTop: '1.5rem' }}>
        {usuarios.isPending && <Cargando />}
        {usuarios.isError && <Aviso tono="error">{mensajeDeError(usuarios.error)}</Aviso>}

        {!usuarios.isPending && filas.length === 0 && (
          <EstadoVacio icono={<Users size={28} />} titulo="No hay usuarios con ese criterio" />
        )}

        {filas.length > 0 && (
          <div className="tabla-envoltura">
            <table className="tabla">
              <thead>
                <tr>
                  <th scope="col">Nombre</th>
                  <th scope="col">Correo</th>
                  <th scope="col">Rol</th>
                  <th scope="col" className="numerico">
                    Equipos
                  </th>
                  <th scope="col">Registro</th>
                </tr>
              </thead>
              <tbody>
                {filas.map((usuario) => (
                  <tr key={usuario.id}>
                    <td>
                      <strong>{usuario.nombre}</strong>
                    </td>
                    <td>{usuario.correo}</td>
                    <td>
                      <Insignia tono={usuario.rol.valor === 'admin' ? 'acento' : 'neutra'}>
                        {usuario.rol.etiqueta}
                      </Insignia>
                    </td>
                    <td className="numerico">{usuario.equipos_count ?? 0}</td>
                    <td>{fechaCorta(usuario.creado_en)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
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

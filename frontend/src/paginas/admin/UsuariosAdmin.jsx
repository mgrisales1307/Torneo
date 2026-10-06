import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { listarUsuarios, mensajeDeError } from '../../lib/api'
import { fechaCorta } from '../../lib/formato'
import { Aviso, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

const ROLES = [
  { valor: '', etiqueta: 'Todos' },
  { valor: 'jugador', etiqueta: 'Jugadores' },
  { valor: 'empleado', etiqueta: 'Organización' },
  { valor: 'admin', etiqueta: 'Administración' },
]

export default function UsuariosAdmin() {
  const [rol, setRol] = useState('')
  const [buscar, setBuscar] = useState('')

  const usuarios = useQuery({
    queryKey: ['usuarios', rol, buscar],
    queryFn: () => listarUsuarios({ rol: rol || undefined, buscar: buscar || undefined }),
    placeholderData: keepPreviousData,
  })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Usuarios</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      <div className="fila" style={{ marginBottom: '1.5rem' }}>
        <div className="chips">
          {ROLES.map((opcion) => (
            <button
              key={opcion.valor}
              type="button"
              className={`chip ${rol === opcion.valor ? 'chip--activo' : ''}`}
              onClick={() => setRol(opcion.valor)}
            >
              {opcion.etiqueta}
            </button>
          ))}
        </div>
        <input
          className="campo__control"
          style={{ maxWidth: '18rem', marginLeft: 'auto' }}
          type="search"
          placeholder="Apodo, nombre o correo"
          aria-label="Buscar usuario"
          value={buscar}
          onChange={(evento) => setBuscar(evento.target.value)}
        />
      </div>

      {usuarios.isPending ? (
        <Cargando />
      ) : usuarios.isError ? (
        <Aviso tono="error">{mensajeDeError(usuarios.error)}</Aviso>
      ) : usuarios.data.data.length === 0 ? (
        <EstadoVacio titulo="Nadie coincide" />
      ) : (
        <div className="tabla-envoltura">
          <table className="tabla">
            <thead>
              <tr>
                <th>Apodo</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th className="numero">Equipos</th>
                <th>Desde</th>
              </tr>
            </thead>
            <tbody>
              {usuarios.data.data.map((usuario) => (
                <tr key={usuario.id}>
                  <td>
                    <Link to={`/jugadores/${usuario.apodo}`}>
                      <strong>{usuario.apodo}</strong>
                    </Link>
                  </td>
                  <td>{usuario.nombre}</td>
                  <td className="apagado">{usuario.correo}</td>
                  <td>
                    <Insignia tono={usuario.es_staff ? 'acento' : 'neutra'}>{usuario.rol.etiqueta}</Insignia>
                  </td>
                  <td className="numero">{usuario.equipos_count}</td>
                  <td>{fechaCorta(usuario.creado_en)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

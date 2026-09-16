import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { LayoutDashboard, LogOut, Menu, ShieldCheck, Trophy, X } from 'lucide-react'
import { useSesion } from '../../contexto/contextoDeSesion'
import { iniciales } from '../../lib/formato'

const ENLACES = [
  { a: '/', texto: 'Inicio' },
  { a: '/torneos', texto: 'Torneos' },
]

export function Cabecera() {
  const { usuario, estaAutenticado, esStaff, puedeTenerEquipos, salir } = useSesion()
  const [menuAbierto, setMenuAbierto] = useState(false)
  const [menuUsuario, setMenuUsuario] = useState(false)
  const contenedorDelMenu = useRef(null)
  const navegar = useNavigate()

  function cerrarMenus() {
    setMenuAbierto(false)
    setMenuUsuario(false)
  }

  useEffect(() => {
    if (!menuUsuario) return undefined

    function alHacerClicFuera(evento) {
      if (!contenedorDelMenu.current?.contains(evento.target)) setMenuUsuario(false)
    }

    document.addEventListener('mousedown', alHacerClicFuera)

    return () => document.removeEventListener('mousedown', alHacerClicFuera)
  }, [menuUsuario])

  async function cerrarSesion() {
    await salir.mutateAsync().catch(() => {})
    navegar('/')
  }

  return (
    <header className="cabecera">
      <div className="contenedor cabecera__fila">
        <Link className="logo" to="/" aria-label="Juegos Zone, inicio">
          <span className="logo__sello" aria-hidden="true">
            JZ
          </span>
          Juegos Zone
        </Link>

        <button
          className="boton-menu"
          type="button"
          onClick={() => setMenuAbierto((abierto) => !abierto)}
          aria-expanded={menuAbierto}
          aria-label={menuAbierto ? 'Cerrar menú' : 'Abrir menú'}
        >
          {menuAbierto ? <X size={18} /> : <Menu size={18} />}
        </button>

        {/* Al pulsar cualquier enlace se cierran los menús, para que no queden
            abiertos encima de la pantalla nueva. */}
        <nav
          className={`navegacion ${menuAbierto ? 'navegacion--abierta' : ''}`}
          aria-label="Principal"
          onClick={cerrarMenus}
        >
          {ENLACES.map((enlace) => (
            <NavLink
              key={enlace.a}
              to={enlace.a}
              end={enlace.a === '/'}
              className={({ isActive }) =>
                `navegacion__enlace ${isActive ? 'navegacion__enlace--activo' : ''}`
              }
            >
              {enlace.texto}
            </NavLink>
          ))}

          {puedeTenerEquipos && (
            <NavLink
              to="/panel"
              className={({ isActive }) =>
                `navegacion__enlace ${isActive ? 'navegacion__enlace--activo' : ''}`
              }
            >
              Mi equipo
            </NavLink>
          )}

          {esStaff && (
            <NavLink
              to="/organizacion"
              className={({ isActive }) =>
                `navegacion__enlace ${isActive ? 'navegacion__enlace--activo' : ''}`
              }
            >
              Organización
            </NavLink>
          )}
        </nav>

        <div className="cabecera__acciones">
          {estaAutenticado ? (
            <div className="menu-usuario" ref={contenedorDelMenu}>
              <button
                className="menu-usuario__boton"
                type="button"
                onClick={() => setMenuUsuario((abierto) => !abierto)}
                aria-expanded={menuUsuario}
                aria-haspopup="menu"
              >
                <span className="avatar-inicial" aria-hidden="true">
                  {iniciales(usuario.nombre)}
                </span>
                <span>{usuario.nombre.split(' ')[0]}</span>
              </button>

              {menuUsuario && (
                <div className="menu-usuario__panel" role="menu" onClick={cerrarMenus}>
                  <div className="menu-usuario__cabecera">
                    <strong>{usuario.nombre}</strong>
                    <span>{usuario.correo}</span>
                  </div>

                  {puedeTenerEquipos && (
                    <Link className="menu-usuario__accion" to="/panel" role="menuitem">
                      <LayoutDashboard size={15} /> Mi panel
                    </Link>
                  )}

                  {esStaff && (
                    <Link className="menu-usuario__accion" to="/organizacion" role="menuitem">
                      <ShieldCheck size={15} /> Organización
                    </Link>
                  )}

                  <button
                    className="menu-usuario__accion"
                    type="button"
                    onClick={cerrarSesion}
                    role="menuitem"
                  >
                    <LogOut size={15} /> Cerrar sesión
                  </button>
                </div>
              )}
            </div>
          ) : (
            <>
              <Link className="boton boton--fantasma boton--chico" to="/ingreso">
                Entrar
              </Link>
              <Link className="boton boton--solido boton--chico" to="/registro">
                <Trophy size={15} /> Inscribir equipo
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  )
}


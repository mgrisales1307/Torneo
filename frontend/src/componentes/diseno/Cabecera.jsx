import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { LayoutDashboard, LogOut, Menu, ShieldCheck, UserRound, X } from 'lucide-react'
import { useSesion } from '../../contexto/contextoDeSesion'
import { iniciales } from '../../lib/formato'
import { JUEGOS } from '../../lib/juegos'

export function Cabecera() {
  const { usuario, estaAutenticado, esStaff, salir } = useSesion()
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

  const claseDeEnlace = ({ isActive }) => `navegacion__enlace ${isActive ? 'navegacion__enlace--activo' : ''}`

  return (
    <header className="cabecera">
      <div className="contenedor cabecera__fila">
        <Link className="logo" to="/" onClick={cerrarMenus}>
          <span className="logo__sello" aria-hidden="true">
            NZ
          </span>
          <span className="logo__texto">NexZone</span>
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

        <nav className={`navegacion ${menuAbierto ? 'navegacion--abierta' : ''}`} aria-label="Principal" onClick={cerrarMenus}>
          <NavLink to="/torneos" className={claseDeEnlace}>
            Torneos
          </NavLink>
          {JUEGOS.map((juego) => (
            <NavLink key={juego.valor} to={`/juegos/${juego.valor}`} className={claseDeEnlace}>
              {juego.valor === 'lol' ? 'LoL' : juego.valor === 'pubg' ? 'PUBG' : juego.nombre}
            </NavLink>
          ))}
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
                <span className="avatar" aria-hidden="true">
                  {iniciales(usuario.apodo)}
                </span>
                <span>{usuario.apodo}</span>
              </button>

              {menuUsuario && (
                <div className="menu-usuario__panel" role="menu" onClick={cerrarMenus}>
                  <div className="menu-usuario__cabecera">
                    <strong>{usuario.apodo}</strong>
                    <span>{usuario.correo}</span>
                  </div>

                  <Link className="menu-usuario__accion" to="/panel" role="menuitem">
                    <LayoutDashboard size={15} /> Mi panel
                  </Link>
                  <Link className="menu-usuario__accion" to={`/jugadores/${usuario.apodo}`} role="menuitem">
                    <UserRound size={15} /> Mi perfil público
                  </Link>

                  {esStaff && (
                    <Link className="menu-usuario__accion" to="/organizacion" role="menuitem">
                      <ShieldCheck size={15} /> Organización
                    </Link>
                  )}

                  <button className="menu-usuario__accion" type="button" onClick={cerrarSesion} role="menuitem">
                    <LogOut size={15} /> Salir
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
                Crear cuenta
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  )
}

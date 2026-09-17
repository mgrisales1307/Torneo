import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
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
  const elementoCabecera = useRef(null)
  const navegar = useNavigate()
  const { pathname } = useLocation()

  /*
   * El alto de la cabecera se publica como variable CSS porque la portada
   * necesita subirse exactamente esa distancia para empezar detrás de ella y
   * llenar la pantalla. Se mide en vez de fijarse a mano: depende de la fuente,
   * del zoom y de si el menú móvil está abierto, así que cualquier constante se
   * desajustaría.
   */
  useEffect(() => {
    const elemento = elementoCabecera.current

    if (!elemento) return undefined

    const observador = new ResizeObserver(([entrada]) => {
      document.documentElement.style.setProperty(
        '--alto-cabecera',
        `${entrada.target.getBoundingClientRect().height}px`,
      )
    })

    observador.observe(elemento)

    return () => observador.disconnect()
  }, [])

  /*
   * Sobre la portada a pantalla completa la cabecera va transparente, para no
   * cortar la imagen con una banda clara; en cuanto se desplaza un poco,
   * recupera su fondo para que los enlaces sigan legibles sobre el contenido.
   *
   * El estado se inicializa leyendo el scroll actual en vez de dentro de un
   * efecto: si se recarga la página a media altura, la cabecera ya nace sólida
   * y no se ve el cambio.
   */
  const [desplazada, setDesplazada] = useState(() => window.scrollY > 48)

  useEffect(() => {
    function alDesplazar() {
      setDesplazada(window.scrollY > 48)
    }

    window.addEventListener('scroll', alDesplazar, { passive: true })

    return () => window.removeEventListener('scroll', alDesplazar)
  }, [])

  const sobreLaPortada = pathname === '/' && !desplazada

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
    <header
      className={`cabecera ${sobreLaPortada ? 'cabecera--sobre-portada' : ''}`}
      ref={elementoCabecera}
    >
      <div className="contenedor cabecera__fila">
        <Link className="logo" to="/" aria-label="Juegos Zone, inicio">
          {/* Solo el sello. El nombre escrito al lado competía con el titular
              de la portada; el sello sigue siendo el enlace al inicio, y el
              nombre viaja en el aria-label para quien navegue con lector. */}
          <span className="logo__sello" aria-hidden="true">
            JZ
          </span>
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


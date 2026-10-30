import { NavLink } from 'react-router-dom'
import { useSesion } from '../../contexto/contextoDeSesion'

export function NavegacionDelPanel({ enlaces, contadores = {} }) {
  const { esStaff } = useSesion()
  // Las cuentas de organización también pueden entrar al panel de jugador.
  // Mantener visibles sus herramientas evita depender del menú del avatar.
  const enlacesVisibles = esStaff && enlaces.some((enlace) => enlace.a === '/panel')
    ? [...enlaces,
        { a: '/organizacion', texto: 'Organización', exacto: true },
        { a: '/organizacion/metricas', texto: 'Métricas' },
      ]
    : enlaces

  return (
    <nav className="panel-navegacion" aria-label="Secciones del panel">
      {enlacesVisibles.map((enlace) => (
        <NavLink key={enlace.a} to={enlace.a} end={enlace.exacto} className={({ isActive }) => (isActive ? 'activo' : '')}>
          {enlace.texto}
          {contadores[enlace.a] > 0 && <span className="contador">{contadores[enlace.a]}</span>}
        </NavLink>
      ))}
    </nav>
  )
}

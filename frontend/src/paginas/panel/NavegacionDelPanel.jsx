import { NavLink } from 'react-router-dom'

export function NavegacionDelPanel({ enlaces, contadores = {} }) {
  return (
    <nav className="panel-navegacion" aria-label="Secciones del panel">
      {enlaces.map((enlace) => (
        <NavLink key={enlace.a} to={enlace.a} end={enlace.exacto} className={({ isActive }) => (isActive ? 'activo' : '')}>
          {enlace.texto}
          {contadores[enlace.a] > 0 && <span className="contador">{contadores[enlace.a]}</span>}
        </NavLink>
      ))}
    </nav>
  )
}

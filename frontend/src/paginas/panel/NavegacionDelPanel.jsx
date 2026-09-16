import { NavLink } from 'react-router-dom'

export function NavegacionDelPanel({ enlaces }) {
  return (
    <nav className="panel-navegacion" aria-label="Secciones del panel">
      {enlaces.map((enlace) => (
        <NavLink key={enlace.a} to={enlace.a} end={enlace.exacto}>
          {enlace.texto}
        </NavLink>
      ))}
    </nav>
  )
}

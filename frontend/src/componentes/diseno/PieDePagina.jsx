import { Link } from 'react-router-dom'

export function PieDePagina() {
  return (
    <footer className="pie">
      <div className="contenedor pie__fila">
        <span>© {new Date().getFullYear()} Juegos Zone · Gestión de torneos de fútbol</span>
        <span>
          <Link to="/torneos">Torneos</Link> · <Link to="/registro">Inscribe tu equipo</Link>
        </span>
      </div>
    </footer>
  )
}

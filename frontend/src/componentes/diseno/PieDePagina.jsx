import { Link } from 'react-router-dom'

export function PieDePagina() {
  return (
    <footer className="pie">
      <div className="contenedor">
        <span>© {new Date().getFullYear()} NexZone</span>
        <span>
          <Link to="/torneos">Torneos</Link>
        </span>
      </div>
    </footer>
  )
}

import { Link } from 'react-router-dom'
import { ShieldCheck } from 'lucide-react'
import estadio from '../../assets/estadio-cartel.jpg'

/**
 * Armazón de las pantallas de ingreso y registro: a la izquierda la marca, a la
 * derecha el formulario.
 */
export function PantallaDeAcceso({ numero = '01', children }) {
  return (
    <main className="acceso-pantalla">
      <section className="marca-panel" aria-label="Juegos Zone Torneos">
        <img
          className="marca-foto"
          src={estadio}
          alt=""
          aria-hidden="true"
          loading="eager"
          fetchPriority="high"
        />
        <span className="marca-velo" aria-hidden="true" />

        <div className="marca-superior">
          <Link className="marca-logo" to="/" aria-label="Juegos Zone, inicio">
            <span className="marca-sello" aria-hidden="true">
              JZ
            </span>
            <span>Juegos Zone</span>
          </Link>
          <span className="edicion">Torneos / {new Date().getFullYear()}</span>
        </div>

        <div className="marca-texto">
          <p className="antetitulo">
            <span /> El fútbol se vive aquí
          </p>
          <h1>
            Cada partido.
            <br />
            <em>Cada historia.</em>
          </h1>
          <p className="marca-descripcion">
            La competencia en un solo lugar. Sigue el ritmo del torneo desde la primera fecha hasta la final.
          </p>
        </div>

        <span className="orbe orbe-uno" aria-hidden="true" />
      </section>

      <section className="acceso-panel">
        <div className="acceso-superior">
          <span className="nota-segura">
            <ShieldCheck size={15} strokeWidth={1.8} /> Acceso seguro
          </span>
          <span className="acceso-numero">JZ — {numero}</span>
        </div>

        <div className="acceso-tarjeta">{children}</div>

        <footer className="acceso-pie">
          <span>Gestión de competencia</span>
          <span>© {new Date().getFullYear()} Juegos Zone</span>
        </footer>
      </section>
    </main>
  )
}

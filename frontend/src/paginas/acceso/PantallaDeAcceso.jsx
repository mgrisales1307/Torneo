import { Link } from 'react-router-dom'
import { JUEGOS } from '../../lib/juegos'
import { PortadaDeJuego } from '../../componentes/juego/PortadaDeJuego'

/**
 * Armazón de ingreso y registro: a la izquierda las portadas de los juegos, a
 * la derecha el formulario.
 */
export function PantallaDeAcceso({ children }) {
  return (
    <main className="acceso">
      <section className="acceso__marca" aria-hidden="true">
        <div className="acceso__mosaico">
          {[...JUEGOS, ...JUEGOS, ...JUEGOS.slice(0, 2)].map((juego, indice) => (
            <PortadaDeJuego key={`${juego.valor}-${indice}`} juego={juego.valor} enlazar={false} />
          ))}
        </div>

        <Link className="logo" to="/" tabIndex={-1}>
          <span className="logo__sello">NZ</span>
          NexZone
        </Link>

        <div className="acceso__lema">
          <p className="rotulo rotulo--acento">Valorant · LoL · Dota 2 · PUBG · Free Fire</p>
          <p className="titulo-seccion">Tu equipo. Tu torneo.</p>
        </div>
      </section>

      <section className="acceso__formulario">
        <div className="acceso__tarjeta">{children}</div>
      </section>
    </main>
  )
}

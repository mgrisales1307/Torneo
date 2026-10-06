import { Link } from 'react-router-dom'
import { colorDeJuego, juegoDe } from '../../lib/juegos'

/**
 * La portada de un juego: su arte, su nombre y una línea de contexto. Sin
 * imagen, se pinta con el color del juego y su sigla.
 */
export function PortadaDeJuego({ juego, detalle, contador, enlazar = true, className = '' }) {
  const visual = juegoDe(juego)
  const contenido = (
    <>
      {visual.portada ? (
        <img src={visual.portada} alt="" loading="lazy" />
      ) : (
        <span className="portada-juego__sin-imagen" aria-hidden="true">
          {visual.abreviatura}
        </span>
      )}

      <span className="portada-juego__texto">
        {contador && <span className="portada-juego__contador">{contador}</span>}
        <strong>{visual.nombre}</strong>
        {detalle && <span>{detalle}</span>}
      </span>
    </>
  )

  if (!enlazar) {
    return (
      <div className={`portada-juego ${className}`} style={colorDeJuego(juego)} aria-hidden="true">
        {contenido}
      </div>
    )
  }

  return (
    <Link className={`portada-juego ${className}`} style={colorDeJuego(juego)} to={`/juegos/${juego}`}>
      {contenido}
    </Link>
  )
}

export function EtiquetaDeJuego({ juego }) {
  const visual = juegoDe(juego)

  return (
    <span className="etiqueta-juego" style={colorDeJuego(juego)}>
      {visual.nombre}
    </span>
  )
}

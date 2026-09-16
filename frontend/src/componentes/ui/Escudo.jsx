/**
 * Escudo del equipo, con las iniciales como respaldo mientras no haya imagen.
 * Así la interfaz nunca muestra un hueco gris.
 */
export function Escudo({ equipo, tamano = 'medio' }) {
  const url = equipo?.escudo_url ?? equipo?.imagenes?.escudo_url
  const nombre = equipo?.nombre ?? 'Equipo'
  const abreviatura = equipo?.abreviatura ?? nombre.slice(0, 3)

  return (
    <span
      className={`escudo escudo--${tamano}`}
      style={equipo?.color_primario ? { background: equipo.color_primario } : undefined}
      title={nombre}
    >
      {url ? <img src={url} alt="" loading="lazy" /> : abreviatura}
    </span>
  )
}

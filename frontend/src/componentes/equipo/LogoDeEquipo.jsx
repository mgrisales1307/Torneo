import { Link } from 'react-router-dom'

/**
 * Logo del equipo, o su sigla sobre su color si todavía no subió uno.
 */
export function LogoDeEquipo({ equipo, tamano }) {
  const estilo = {
    '--color-equipo': equipo?.color ?? '#555',
    ...(tamano ? { '--tamano-logo': tamano } : {}),
  }

  return (
    <span className="logo-equipo" style={estilo} aria-hidden="true">
      {equipo?.logo_url ? <img src={equipo.logo_url} alt="" loading="lazy" /> : (equipo?.abreviatura ?? '?')}
    </span>
  )
}

/**
 * Logo y nombre en una línea, enlazando a la ficha del equipo.
 */
export function EquipoEnLinea({ equipo, enlazar = true, tamano }) {
  if (!equipo) {
    return <span className="equipo-en-linea apagado">Por definir</span>
  }

  const contenido = (
    <>
      <LogoDeEquipo equipo={equipo} tamano={tamano} />
      <span>{equipo.nombre}</span>
    </>
  )

  return enlazar ? (
    <Link className="equipo-en-linea" to={equipo.es_individual ? `/jugadores/${encodeURIComponent(equipo.nombre)}` : `/equipos/${equipo.slug}`}>
      {contenido}
    </Link>
  ) : (
    <span className="equipo-en-linea">{contenido}</span>
  )
}

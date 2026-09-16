import { Link } from 'react-router-dom'
import { ListOrdered } from 'lucide-react'
import { EstadoVacio, Forma } from '../ui/Basicos'
import { Escudo } from '../ui/Escudo'

/**
 * Tabla de posiciones.
 *
 * Las columnas cortas son las de siempre en el fútbol —PJ, G, E, P, GF, GC, DG—
 * y llevan su nombre completo en el title para quien no las conozca.
 */
const COLUMNAS = [
  ['PJ', 'Partidos jugados'],
  ['G', 'Ganados'],
  ['E', 'Empatados'],
  ['P', 'Perdidos'],
  ['GF', 'Goles a favor'],
  ['GC', 'Goles en contra'],
  ['DG', 'Diferencia de gol'],
]

export function TablaDePosiciones({ posiciones = [] }) {
  if (posiciones.length === 0) {
    return (
      <EstadoVacio icono={<ListOrdered size={28} />} titulo="La tabla todavía está vacía">
        Aparecerá en cuanto la organización apruebe los equipos y se juegue la primera fecha.
      </EstadoVacio>
    )
  }

  return (
    <div className="tabla-envoltura">
      <table className="tabla">
        <caption className="solo-lectores">Tabla de posiciones del torneo</caption>
        <thead>
          <tr>
            <th scope="col" className="numerico">
              #
            </th>
            <th scope="col">Equipo</th>
            {COLUMNAS.map(([corta, larga]) => (
              <th scope="col" className="numerico" key={corta} title={larga}>
                <abbr title={larga}>{corta}</abbr>
              </th>
            ))}
            <th scope="col" className="numerico">
              Pts
            </th>
            <th scope="col">Forma</th>
          </tr>
        </thead>
        <tbody>
          {posiciones.map((fila) => (
            <tr key={fila.equipo.id} className={fila.posicion === 1 ? 'tabla__destacada' : undefined}>
              <td className="numerico">{fila.posicion}</td>
              <td>
                <span className="fila-equipo">
                  <Escudo equipo={fila.equipo} tamano="chico" />
                  <Link to={`/equipos/${fila.equipo.slug}`}>{fila.equipo.nombre}</Link>
                </span>
              </td>
              <td className="numerico">{fila.partidos_jugados}</td>
              <td className="numerico">{fila.ganados}</td>
              <td className="numerico">{fila.empatados}</td>
              <td className="numerico">{fila.perdidos}</td>
              <td className="numerico">{fila.goles_a_favor}</td>
              <td className="numerico">{fila.goles_en_contra}</td>
              <td className="numerico">
                {fila.diferencia_de_gol > 0 ? '+' : ''}
                {fila.diferencia_de_gol}
              </td>
              <td className="numerico">
                <strong>{fila.puntos}</strong>
              </td>
              <td>
                <Forma resultados={fila.forma} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

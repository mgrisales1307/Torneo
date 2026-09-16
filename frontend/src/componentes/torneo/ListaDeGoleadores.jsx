import { Link } from 'react-router-dom'
import { Goal } from 'lucide-react'
import { EstadoVacio } from '../ui/Basicos'
import { Escudo } from '../ui/Escudo'

export function ListaDeGoleadores({ goleadores = [] }) {
  if (goleadores.length === 0) {
    return (
      <EstadoVacio icono={<Goal size={28} />} titulo="Todavía no hay goles">
        La tabla de goleadores se llena con los goles que la organización carga en cada partido.
      </EstadoVacio>
    )
  }

  return (
    <div className="tabla-envoltura">
      <table className="tabla">
        <caption className="solo-lectores">Tabla de goleadores</caption>
        <thead>
          <tr>
            <th scope="col" className="numerico">
              #
            </th>
            <th scope="col">Jugador</th>
            <th scope="col">Equipo</th>
            <th scope="col" className="numerico">
              Goles
            </th>
            <th scope="col" className="numerico">
              <abbr title="Goles de penal">De penal</abbr>
            </th>
          </tr>
        </thead>
        <tbody>
          {goleadores.map((fila) => (
            <tr key={fila.jugador.id}>
              <td className="numerico">{fila.posicion}</td>
              <td>
                <strong>{fila.jugador.nombre_completo}</strong>
                {fila.jugador.numero_camiseta && (
                  <span style={{ color: 'var(--apagado-claro)' }}> · #{fila.jugador.numero_camiseta}</span>
                )}
              </td>
              <td>
                <span className="fila-equipo">
                  <Escudo equipo={fila.equipo} tamano="chico" />
                  <Link to={`/equipos/${fila.equipo.slug}`}>{fila.equipo.abreviatura}</Link>
                </span>
              </td>
              <td className="numerico">
                <strong>{fila.goles}</strong>
              </td>
              <td className="numerico">{fila.goles_de_penal}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

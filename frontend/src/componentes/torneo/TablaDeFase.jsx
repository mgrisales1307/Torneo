import { EquipoEnLinea } from '../equipo/LogoDeEquipo'

/*
 * Columnas de cada tipo de tabla. El primer número de cada fila es siempre el
 * que ordena; va en negrita.
 */
const COLUMNAS = {
  grupos: [
    ['jugados', 'PJ'],
    ['ganados', 'G'],
    ['perdidos', 'P'],
    ['diferencia', 'Dif', (valor) => (valor > 0 ? `+${valor}` : valor)],
    ['puntos', 'Pts', null, true],
  ],
  suizo: [
    ['ganados', 'G', null, true],
    ['perdidos', 'P'],
    ['buchholz', 'Buch'],
    ['diferencia', 'Dif', (valor) => (valor > 0 ? `+${valor}` : valor)],
  ],
  battle_royale: [
    ['partidas', 'Part.'],
    ['victorias', '#1'],
    ['bajas', 'Bajas'],
    ['puntos', 'Pts', null, true],
  ],
}

export function TablaDeFase({ tipo, filas, clasifican = 0 }) {
  const columnas = COLUMNAS[tipo] ?? []

  if (filas.length === 0) {
    return <p className="apagado">La tabla aparece con el primer resultado.</p>
  }

  return (
    <div className="tabla-envoltura">
      <table className="tabla">
        <thead>
          <tr>
            <th className="numero">#</th>
            <th>Equipo</th>
            {columnas.map(([clave, titulo]) => (
              <th className="numero" key={clave}>
                {titulo}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => (
            <tr key={fila.equipo.id} className={fila.posicion <= clasifican ? 'clasifica' : ''}>
              <td className="numero posicion">{fila.posicion}</td>
              <td>
                <EquipoEnLinea equipo={fila.equipo} tamano="1.6rem" />
              </td>
              {columnas.map(([clave, , formato, destacada]) => (
                <td className={`numero ${destacada ? 'destacada' : ''}`} key={clave}>
                  {formato ? formato(fila[clave]) : fila[clave]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

/**
 * Ranking de un juego: Elo en los de duelo, puntos acumulados en battle royale.
 */
export function TablaDeRanking({ filas, esBattleRoyale }) {
  if (filas.length === 0) {
    return <p className="apagado">El ranking arranca con el primer partido jugado.</p>
  }

  return (
    <div className="tabla-envoltura">
      <table className="tabla">
        <thead>
          <tr>
            <th className="numero">#</th>
            <th>Equipo</th>
            <th className="numero">{esBattleRoyale ? 'Partidas' : 'PJ'}</th>
            <th className="numero">{esBattleRoyale ? '#1' : 'G'}</th>
            <th className="numero">{esBattleRoyale ? 'Bajas' : 'P'}</th>
            <th className="numero">{esBattleRoyale ? 'Pts' : 'Elo'}</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => (
            <tr key={fila.equipo.id}>
              <td className="numero posicion">{fila.posicion}</td>
              <td>
                <EquipoEnLinea equipo={fila.equipo} tamano="1.6rem" />
              </td>
              <td className="numero">{fila.partidos}</td>
              <td className="numero">{fila.victorias}</td>
              <td className="numero">{esBattleRoyale ? fila.bajas : fila.derrotas}</td>
              <td className="numero destacada">{fila.puntos}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

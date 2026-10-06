import { useState } from 'react'
import { Boton, Campo } from '../ui/Basicos'
import { LogoDeEquipo } from '../equipo/LogoDeEquipo'

/**
 * Dos casillas para el marcador de la serie, una por equipo.
 */
export function EntradaDeMarcador({ partido, valor, alCambiar }) {
  const maximo = Math.floor(partido.mejor_de / 2) + 1

  return (
    <div className="marcador-entrada">
      <Campo
        etiqueta={partido.equipo_a?.nombre}
        name="victorias_a"
        type="number"
        min={0}
        max={maximo}
        value={valor.a}
        onChange={(evento) => alCambiar({ ...valor, a: evento.target.value })}
      />
      <span className="marcador-entrada__guion" aria-hidden="true">
        –
      </span>
      <Campo
        etiqueta={partido.equipo_b?.nombre}
        name="victorias_b"
        type="number"
        min={0}
        max={maximo}
        value={valor.b}
        onChange={(evento) => alCambiar({ ...valor, b: evento.target.value })}
      />
    </div>
  )
}

/**
 * Marcadores posibles de una serie, para elegir con un clic: 1-0 y 0-1 al
 * mejor de 1; 2-0, 2-1, 1-2 y 0-2 al mejor de 3…
 */
function marcadoresPosibles(mejorDe) {
  const necesarias = Math.floor(mejorDe / 2) + 1
  const posibles = []

  for (let perdedor = 0; perdedor < necesarias; perdedor++) posibles.push([necesarias, perdedor])
  for (let perdedor = necesarias - 1; perdedor >= 0; perdedor--) posibles.push([perdedor, necesarias])

  return posibles
}

export function AtajosDeMarcador({ partido, alElegir }) {
  return (
    <div className="chips" role="group" aria-label="Marcadores posibles">
      {marcadoresPosibles(partido.mejor_de).map(([a, b]) => (
        <button key={`${a}-${b}`} type="button" className="chip" onClick={() => alElegir({ a: String(a), b: String(b) })}>
          {a}–{b}
        </button>
      ))}
    </div>
  )
}

/**
 * La sala de una partida de battle royale: puesto y bajas de cada equipo.
 */
export function FormularioDeSala({ equipos, resultadosPrevios = [], cargando, alGuardar }) {
  const [filas, setFilas] = useState(() =>
    equipos.map((equipo, indice) => {
      const previo = resultadosPrevios.find((resultado) => resultado.equipo.id === equipo.id)

      return { equipo, posicion: String(previo?.posicion ?? indice + 1), bajas: String(previo?.bajas ?? 0) }
    }),
  )

  function cambiar(indice, campo, valor) {
    setFilas((actuales) => actuales.map((fila, posicion) => (posicion === indice ? { ...fila, [campo]: valor } : fila)))
  }

  return (
    <form
      className="formulario"
      onSubmit={(evento) => {
        evento.preventDefault()
        alGuardar(
          filas.map((fila) => ({
            equipo_id: fila.equipo.id,
            posicion: Number(fila.posicion),
            bajas: Number(fila.bajas),
          })),
        )
      }}
    >
      <div className="sala">
        <div className="sala__fila sala__cabecera">
          <span>Equipo</span>
          <span>Puesto</span>
          <span>Bajas</span>
        </div>
        {filas.map((fila, indice) => (
          <div className="sala__fila" key={fila.equipo.id}>
            <span className="equipo-en-linea">
              <LogoDeEquipo equipo={fila.equipo} tamano="1.6rem" />
              <span>{fila.equipo.nombre}</span>
            </span>
            <input
              className="campo__control"
              type="number"
              min={1}
              max={equipos.length}
              aria-label={`Puesto de ${fila.equipo.nombre}`}
              value={fila.posicion}
              onChange={(evento) => cambiar(indice, 'posicion', evento.target.value)}
            />
            <input
              className="campo__control"
              type="number"
              min={0}
              aria-label={`Bajas de ${fila.equipo.nombre}`}
              value={fila.bajas}
              onChange={(evento) => cambiar(indice, 'bajas', evento.target.value)}
            />
          </div>
        ))}
      </div>
      <div className="formulario__acciones">
        <Boton type="submit" cargando={cargando}>
          Guardar partida
        </Boton>
      </div>
    </form>
  )
}

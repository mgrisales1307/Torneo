import { Link } from 'react-router-dom'
import { Cruce } from './Cruce'
import { FilaDePartido } from './FilaDePartido'
import { TablaDeFase } from './TablaDeFase'
import { fechaYHora } from '../../lib/formato'
import { EquipoEnLinea } from '../equipo/LogoDeEquipo'
import { Insignia } from '../ui/Basicos'

const ORDEN_DE_LLAVES = ['superior', 'inferior', 'gran_final', 'tercer_puesto']

/**
 * Todas las fases del torneo, cada una con la vista que le corresponde: el
 * cuadro en columnas para la eliminación, y la tabla con sus partidos para
 * grupos, suizo y battle royale.
 */
export function Cuadro({ fases, tablas, torneo }) {
  return (
    <div className="pila">
      {[...fases].reverse().map((fase) => (
        <section key={fase.id} aria-labelledby={`fase-${fase.id}`}>
          <div className="encabezado-seccion">
            <div>
              <p className="rotulo">Fase {fase.orden}</p>
              <h2 className="titulo-bloque" id={`fase-${fase.id}`}>
                {fase.nombre}
              </h2>
            </div>
            <Insignia estado={fase.estado} tono={fase.estado.valor === 'en_curso' ? 'acento' : 'neutra'} />
          </div>

          {fase.tipo.es_eliminacion ? (
            <Eliminacion fase={fase} />
          ) : (
            <ConTabla fase={fase} tablas={tablas?.[fase.id] ?? {}} torneo={torneo} />
          )}
        </section>
      ))}
    </div>
  )
}

function Eliminacion({ fase }) {
  const doble = fase.tipo.valor === 'eliminacion_doble'
  const porLlave = agrupar(fase.partidos, (partido) => partido.llave?.valor ?? 'superior')

  return ORDEN_DE_LLAVES.filter((llave) => porLlave[llave]).map((llave) => {
    const rondas = agrupar(porLlave[llave], (partido) => partido.ronda)
    const numeros = Object.keys(rondas).map(Number).sort((a, b) => a - b)
    const ultima = Math.max(...numeros)

    return (
      <div className="llave" key={llave}>
        {doble && llave !== 'gran_final' && (
          <p className="rotulo rotulo--acento llave__titulo">{porLlave[llave][0].llave.etiqueta}</p>
        )}

        <div className="cuadro">
          {numeros.map((ronda) => (
            <div className="cuadro__ronda" key={ronda}>
              <p className="rotulo cuadro__titulo">{nombreDeColumna(llave, ronda, ultima, doble)}</p>
              <div className="cuadro__partidos">
                {rondas[ronda]
                  .sort((uno, otro) => uno.numero - otro.numero)
                  .map((partido) => (
                    <Cruce key={partido.id} partido={partido} />
                  ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    )
  })
}

function nombreDeColumna(llave, ronda, ultima, doble) {
  if (llave === 'gran_final') return ronda === 2 ? 'Revancha de la gran final' : 'Gran final'
  if (llave === 'tercer_puesto') return 'Tercer puesto'

  if (doble) {
    return ronda === ultima ? `Final ${llave === 'superior' ? 'superior' : 'inferior'}` : `Ronda ${ronda}`
  }

  const restantes = ultima - ronda

  if (restantes === 0) return 'Final'
  if (restantes === 1) return 'Semifinal'
  if (restantes === 2) return 'Cuartos'

  return `Ronda ${ronda}`
}

function ConTabla({ fase, tablas, torneo }) {
  const tipo = fase.tipo.valor

  if (tipo === 'grupos') {
    return (
      <div className="rejilla rejilla--grupos">
        {Object.entries(tablas).map(([letra, filas]) => (
          <div className="pila pila--chica" key={letra}>
            <p className="rotulo rotulo--acento">Grupo {letra}</p>
            <TablaDeFase tipo={tipo} filas={filas} clasifican={torneo?.ajustes?.clasifican_por_grupo} />
            <ListaPorRonda partidos={fase.partidos.filter((partido) => partido.grupo === letra)} etiqueta="Jornada" />
          </div>
        ))}
      </div>
    )
  }

  if (tipo === 'battle_royale') {
    return (
      <div className="dos-columnas">
        <TablaDeFase tipo={tipo} filas={tablas.general ?? []} />
        <Partidas partidas={fase.partidos} />
      </div>
    )
  }

  return (
    <div className="dos-columnas">
      <TablaDeFase tipo={tipo} filas={tablas.general ?? []} />
      <ListaPorRonda partidos={fase.partidos} etiqueta="Ronda" />
    </div>
  )
}

function ListaPorRonda({ partidos, etiqueta }) {
  const rondas = agrupar(partidos, (partido) => partido.ronda)

  return (
    <div className="pila pila--chica">
      {Object.keys(rondas)
        .map(Number)
        .sort((a, b) => a - b)
        .map((ronda) => (
          <div className="pila pila--chica" key={ronda}>
            <p className="rotulo">
              {etiqueta} {ronda}
            </p>
            {rondas[ronda].map((partido) =>
              partido.es_descanso ? (
                <div className="accion-pendiente" key={partido.id}>
                  <EquipoEnLinea equipo={partido.equipo_a} />
                  <span className="apagado">Descansa · cuenta como victoria</span>
                </div>
              ) : (
                <FilaDePartido key={partido.id} partido={partido} />
              ),
            )}
          </div>
        ))}
    </div>
  )
}

function Partidas({ partidas }) {
  return (
    <div className="pila pila--chica">
      {[...partidas]
        .sort((una, otra) => una.ronda - otra.ronda)
        .map((partida) => {
          const podio = (partida.resultados ?? []).slice(0, 3)

          return (
            <Link className="accion-pendiente" key={partida.id} to={`/partidos/${partida.id}`}>
              <div>
                <strong>Partida {partida.ronda}</strong>
                <span className="apagado">
                  {podio.length > 0
                    ? podio.map((fila) => `${fila.posicion}. ${fila.equipo.nombre}`).join('  ·  ')
                    : fechaYHora(partida.programado_para)}
                </span>
              </div>
              <Insignia estado={partida.estado} />
            </Link>
          )
        })}
    </div>
  )
}

function agrupar(lista, clave) {
  return lista.reduce((grupos, elemento) => {
    const valor = clave(elemento)
    ;(grupos[valor] ??= []).push(elemento)

    return grupos
  }, {})
}

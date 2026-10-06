import { useState } from 'react'
import { aCentavos, aPesos, paraInputDeFecha } from '../../lib/formato'
import { JUEGOS, juegoDe } from '../../lib/juegos'
import { Aviso, Boton, Campo, CampoDeSeleccion, CampoDeTexto } from '../ui/Basicos'

const FORMATOS_DE_DUELO = [
  { valor: 'eliminacion_simple', etiqueta: 'Eliminación simple' },
  { valor: 'eliminacion_doble', etiqueta: 'Eliminación doble' },
  { valor: 'grupos_y_eliminacion', etiqueta: 'Grupos y eliminación' },
  { valor: 'suizo', etiqueta: 'Sistema suizo' },
]

const SERIES = [
  { valor: '1', etiqueta: 'Al mejor de 1' },
  { valor: '3', etiqueta: 'Al mejor de 3' },
  { valor: '5', etiqueta: 'Al mejor de 5' },
]

function inicial(torneo) {
  return {
    juego: torneo?.juego.valor ?? 'valorant',
    nombre: torneo?.nombre ?? '',
    formato: torneo?.formato.valor ?? 'eliminacion_simple',
    cupo_maximo_equipos: String(torneo?.ajustes.cupo_maximo_equipos ?? 16),
    mejor_de: String(torneo?.ajustes.mejor_de ?? 1),
    mejor_de_final: String(torneo?.ajustes.mejor_de_final ?? 3),
    cantidad_de_grupos: String(torneo?.ajustes.cantidad_de_grupos ?? 2),
    clasifican_por_grupo: String(torneo?.ajustes.clasifican_por_grupo ?? 2),
    rondas_suizo: String(torneo?.ajustes.rondas_suizo ?? ''),
    partidas_battle_royale: String(torneo?.ajustes.partidas_battle_royale ?? 4),
    check_in_minutos: String(torneo?.check_in.minutos ?? 30),
    costo: aPesos(torneo?.inscripcion.costo_centavos ?? 0),
    premios: [0, 1, 2].map((indice) => aPesos(torneo?.premios.puestos[indice]?.centavos ?? 0)),
    inscripciones_abren_en: paraInputDeFecha(torneo?.inscripcion.abren_en),
    inscripciones_cierran_en: paraInputDeFecha(torneo?.inscripcion.cierran_en),
    inicia_en: paraInputDeFecha(torneo?.fechas.inicia_en),
    reglas: torneo?.reglas ?? '',
  }
}

/**
 * Crear o editar un torneo. Solo muestra los ajustes que usa el formato
 * elegido; el resto no viaja.
 */
export function FormularioDeTorneo({ torneo, errores = {}, error, cargando, alGuardar, alCancelar }) {
  const [formulario, setFormulario] = useState(() => inicial(torneo))
  const empezo = ['en_curso', 'finalizado'].includes(torneo?.estado.valor)
  const juego = juegoDe(formulario.juego)
  const formato = formulario.formato

  function cambiar(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => {
      const siguiente = { ...actual, [name]: value }

      // Al cambiar de juego el formato tiene que seguir siendo válido.
      if (name === 'juego') {
        const esBattleRoyale = juegoDe(value).esBattleRoyale
        siguiente.formato = esBattleRoyale ? 'battle_royale' : actual.formato === 'battle_royale' ? 'eliminacion_simple' : actual.formato
        siguiente.cupo_maximo_equipos = String(esBattleRoyale ? juegoDe(value).sala : 16)
      }

      return siguiente
    })
  }

  function enviar(evento) {
    evento.preventDefault()

    const fecha = (valor) => (valor ? new Date(valor).toISOString() : null)

    const datos = {
      nombre: formulario.nombre,
      reglas: formulario.reglas || null,
      check_in_minutos: Number(formulario.check_in_minutos || 0),
      premios: formulario.premios.map(aCentavos),
      inscripciones_abren_en: fecha(formulario.inscripciones_abren_en),
      inscripciones_cierran_en: fecha(formulario.inscripciones_cierran_en),
      inicia_en: fecha(formulario.inicia_en),
    }

    if (!empezo) {
      Object.assign(datos, {
        juego: formulario.juego,
        formato,
        cupo_maximo_equipos: Number(formulario.cupo_maximo_equipos),
        costo_inscripcion_centavos: aCentavos(formulario.costo),
        ...(juego.esBattleRoyale
          ? { partidas_battle_royale: Number(formulario.partidas_battle_royale) }
          : { mejor_de: Number(formulario.mejor_de), mejor_de_final: Number(formulario.mejor_de_final) }),
        ...(formato === 'grupos_y_eliminacion' && {
          cantidad_de_grupos: Number(formulario.cantidad_de_grupos),
          clasifican_por_grupo: Number(formulario.clasifican_por_grupo),
        }),
        ...(formato === 'suizo' && { rondas_suizo: formulario.rondas_suizo ? Number(formulario.rondas_suizo) : null }),
      })
    }

    alGuardar(datos)
  }

  return (
    <form className="formulario-torneo" onSubmit={enviar} noValidate>
      <fieldset>
        <legend>Torneo</legend>
        <div className="formulario__fila">
          <CampoDeSeleccion
            etiqueta="Juego"
            name="juego"
            value={formulario.juego}
            onChange={cambiar}
            disabled={empezo}
            error={errores.juego}
            opciones={JUEGOS.map((opcion) => ({ valor: opcion.valor, etiqueta: opcion.nombre }))}
          />
          <Campo etiqueta="Nombre" name="nombre" value={formulario.nombre} onChange={cambiar} error={errores.nombre} />
        </div>
      </fieldset>

      <fieldset disabled={empezo}>
        <legend>Formato{empezo ? ' · ya no se cambia' : ''}</legend>
        <div className="formulario__fila">
          <CampoDeSeleccion
            etiqueta="Formato"
            name="formato"
            value={formato}
            onChange={cambiar}
            error={errores.formato}
            opciones={juego.esBattleRoyale ? [{ valor: 'battle_royale', etiqueta: 'Battle royale por puntos' }] : FORMATOS_DE_DUELO}
          />
          <Campo
            etiqueta="Cupo de equipos"
            name="cupo_maximo_equipos"
            type="number"
            min={2}
            max={juego.esBattleRoyale ? juego.sala : 64}
            value={formulario.cupo_maximo_equipos}
            onChange={cambiar}
            error={errores.cupo_maximo_equipos}
          />
        </div>

        {juego.esBattleRoyale ? (
          <Campo
            etiqueta="Partidas"
            name="partidas_battle_royale"
            type="number"
            min={1}
            max={12}
            value={formulario.partidas_battle_royale}
            onChange={cambiar}
            error={errores.partidas_battle_royale}
          />
        ) : (
          <div className="formulario__fila">
            <CampoDeSeleccion etiqueta="Series" name="mejor_de" value={formulario.mejor_de} onChange={cambiar} opciones={SERIES} />
            <CampoDeSeleccion etiqueta="Final" name="mejor_de_final" value={formulario.mejor_de_final} onChange={cambiar} opciones={SERIES} />
          </div>
        )}

        {formato === 'grupos_y_eliminacion' && (
          <div className="formulario__fila">
            <Campo
              etiqueta="Grupos"
              name="cantidad_de_grupos"
              type="number"
              min={2}
              max={8}
              value={formulario.cantidad_de_grupos}
              onChange={cambiar}
              error={errores.cantidad_de_grupos}
            />
            <Campo
              etiqueta="Clasifican por grupo"
              name="clasifican_por_grupo"
              type="number"
              min={1}
              max={4}
              value={formulario.clasifican_por_grupo}
              onChange={cambiar}
              error={errores.clasifican_por_grupo}
            />
          </div>
        )}

        {formato === 'suizo' && (
          <Campo
            etiqueta="Rondas"
            name="rondas_suizo"
            type="number"
            min={2}
            max={9}
            pista="Vacío: las justas para un solo invicto."
            value={formulario.rondas_suizo}
            onChange={cambiar}
            error={errores.rondas_suizo}
          />
        )}
      </fieldset>

      <fieldset>
        <legend>Fechas</legend>
        <div className="formulario__fila">
          <Campo
            etiqueta="Abren inscripciones"
            name="inscripciones_abren_en"
            type="datetime-local"
            value={formulario.inscripciones_abren_en}
            onChange={cambiar}
            error={errores.inscripciones_abren_en}
          />
          <Campo
            etiqueta="Cierran inscripciones"
            name="inscripciones_cierran_en"
            type="datetime-local"
            value={formulario.inscripciones_cierran_en}
            onChange={cambiar}
            error={errores.inscripciones_cierran_en}
          />
        </div>
        <div className="formulario__fila">
          <Campo
            etiqueta="Inicio"
            name="inicia_en"
            type="datetime-local"
            value={formulario.inicia_en}
            onChange={cambiar}
            error={errores.inicia_en}
          />
          <Campo
            etiqueta="Check-in (minutos antes)"
            name="check_in_minutos"
            type="number"
            min={0}
            max={240}
            pista="0: sin check-in."
            value={formulario.check_in_minutos}
            onChange={cambiar}
            error={errores.check_in_minutos}
          />
        </div>
      </fieldset>

      <fieldset>
        <legend>Dinero (pesos)</legend>
        <Campo
          etiqueta="Inscripción"
          name="costo"
          type="number"
          min={0}
          step={1000}
          pista="0: gratis."
          value={formulario.costo}
          onChange={cambiar}
          disabled={empezo}
          error={errores.costo_inscripcion_centavos}
        />
        <div className="formulario__fila">
          {formulario.premios.map((premio, indice) => (
            <Campo
              key={indice}
              etiqueta={`Premio ${indice + 1}.º`}
              name={`premio-${indice}`}
              type="number"
              min={0}
              step={10000}
              value={premio}
              onChange={(evento) =>
                setFormulario((actual) => ({
                  ...actual,
                  premios: actual.premios.map((valor, posicion) => (posicion === indice ? evento.target.value : valor)),
                }))
              }
            />
          ))}
        </div>
      </fieldset>

      <CampoDeTexto
        etiqueta="Reglas (una por línea)"
        name="reglas"
        value={formulario.reglas}
        onChange={cambiar}
        error={errores.reglas}
        rows={4}
      />

      {error && <Aviso tono="error">{error}</Aviso>}

      <div className="formulario__acciones">
        {alCancelar && (
          <Boton variante="fantasma" type="button" onClick={alCancelar}>
            Cancelar
          </Boton>
        )}
        <Boton type="submit" cargando={cargando}>
          {torneo ? 'Guardar cambios' : 'Crear torneo'}
        </Boton>
      </div>
    </form>
  )
}

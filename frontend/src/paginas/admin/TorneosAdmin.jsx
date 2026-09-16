import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trophy } from 'lucide-react'
import { crearTorneo, erroresPorCampo, listarTorneosAdmin, mensajeDeError } from '../../lib/api'
import { aCentavos, fechaCorta } from '../../lib/formato'
import {
  Aviso,
  Boton,
  Campo,
  CampoDeSeleccion,
  CampoDeTexto,
  Cargando,
  EstadoVacio,
  Insignia,
} from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

const FORMATOS = [
  { valor: 'liga_ida', etiqueta: 'Liga · todos contra todos' },
  { valor: 'liga_ida_vuelta', etiqueta: 'Liga · ida y vuelta' },
]

const TORNEO_VACIO = {
  nombre: '',
  temporada: String(new Date().getFullYear()),
  categoria: 'Libre',
  descripcion: '',
  formato: 'liga_ida',
  ciudad: '',
  sede: '',
  cupo_maximo_equipos: 8,
  minimo_jugadores_por_equipo: 7,
  maximo_jugadores_por_equipo: 20,
  costo: '150000',
  inscripciones_cierran_en: '',
  inicia_en: '',
}

export default function TorneosAdmin() {
  const cliente = useQueryClient()
  const [abierto, setAbierto] = useState(false)
  const [formulario, setFormulario] = useState(TORNEO_VACIO)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')

  const torneos = useQuery({ queryKey: ['admin', 'torneos'], queryFn: () => listarTorneosAdmin() })

  const crear = useMutation({
    mutationFn: crearTorneo,
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['admin', 'torneos'] })
      cliente.invalidateQueries({ queryKey: ['torneos'] })
      cerrar()
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo))
    },
  })

  function cerrar() {
    setAbierto(false)
    setFormulario(TORNEO_VACIO)
    setErrores({})
    setError('')
  }

  function alCambiar(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: value }))
    setErrores((actuales) => ({ ...actuales, [name]: undefined }))
  }

  function alEnviar(evento) {
    evento.preventDefault()
    setError('')

    const { costo, inscripciones_cierran_en, inicia_en, ...resto } = formulario

    crear.mutate({
      ...resto,
      cupo_maximo_equipos: Number(resto.cupo_maximo_equipos),
      minimo_jugadores_por_equipo: Number(resto.minimo_jugadores_por_equipo),
      maximo_jugadores_por_equipo: Number(resto.maximo_jugadores_por_equipo),
      costo_inscripcion_centavos: aCentavos(costo),
      inscripciones_abren_en: new Date().toISOString(),
      inscripciones_cierran_en: inscripciones_cierran_en || null,
      inicia_en: inicia_en || null,
    })
  }

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Torneos</h1>
          <p>Crea la competencia, define sus reglas y su costo de inscripción.</p>
        </div>
        <Boton onClick={() => setAbierto(true)} iconoIzquierda={<Plus size={16} />}>
          Nuevo torneo
        </Boton>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      {torneos.isPending && <Cargando />}
      {torneos.isError && <Aviso tono="error">{mensajeDeError(torneos.error)}</Aviso>}

      {torneos.data?.data.length === 0 && (
        <EstadoVacio icono={<Trophy size={30} />} titulo="Todavía no hay torneos">
          Crea el primero y define cuántos equipos admite y cuánto cuesta inscribirse.
        </EstadoVacio>
      )}

      {torneos.data?.data.length > 0 && (
        <div className="tabla-envoltura">
          <table className="tabla">
            <thead>
              <tr>
                <th scope="col">Torneo</th>
                <th scope="col">Estado</th>
                <th scope="col" className="numerico">
                  Equipos
                </th>
                <th scope="col" className="numerico">
                  Partidos
                </th>
                <th scope="col">Inicia</th>
                <th scope="col">Inscripción</th>
              </tr>
            </thead>
            <tbody>
              {torneos.data.data.map((torneo) => (
                <tr key={torneo.id}>
                  <td>
                    <Link to={`/organizacion/torneos/${torneo.slug}`}>
                      <strong>{torneo.nombre}</strong>
                    </Link>
                    <div style={{ color: 'var(--apagado-claro)', fontSize: '.72rem' }}>
                      {torneo.formato.etiqueta}
                    </div>
                  </td>
                  <td>
                    <Insignia estado={torneo.estado} />
                  </td>
                  <td className="numerico">
                    {torneo.inscripcion.equipos_inscritos}/{torneo.reglas.cupo_maximo_equipos}
                  </td>
                  <td className="numerico">{torneo.partidos_count ?? 0}</td>
                  <td>{fechaCorta(torneo.fechas.inicia_en)}</td>
                  <td>{torneo.inscripcion.costo_formateado}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialogo abierto={abierto} ancho titulo="Nuevo torneo" alCerrar={cerrar}>
        <form style={{ display: 'grid', gap: '1.1rem' }} onSubmit={alEnviar}>
          <Campo
            etiqueta="Nombre"
            name="nombre"
            value={formulario.nombre}
            onChange={alCambiar}
            error={errores.nombre}
            placeholder="Copa Juegos Zone 2026"
            required
          />

          <div className="rejilla-campos">
            <Campo etiqueta="Temporada" name="temporada" value={formulario.temporada} onChange={alCambiar} />
            <Campo etiqueta="Categoría" name="categoria" value={formulario.categoria} onChange={alCambiar} />
            <CampoDeSeleccion
              etiqueta="Formato"
              name="formato"
              opciones={FORMATOS}
              value={formulario.formato}
              onChange={alCambiar}
              error={errores.formato}
            />
          </div>

          <CampoDeTexto
            etiqueta="Descripción"
            name="descripcion"
            value={formulario.descripcion}
            onChange={alCambiar}
            error={errores.descripcion}
          />

          <div className="rejilla-campos">
            <Campo etiqueta="Ciudad" name="ciudad" value={formulario.ciudad} onChange={alCambiar} />
            <Campo etiqueta="Sede" name="sede" value={formulario.sede} onChange={alCambiar} />
          </div>

          <div className="rejilla-campos">
            <Campo
              etiqueta="Cupo de equipos"
              name="cupo_maximo_equipos"
              type="number"
              min={2}
              max={64}
              value={formulario.cupo_maximo_equipos}
              onChange={alCambiar}
              error={errores.cupo_maximo_equipos}
            />
            <Campo
              etiqueta="Mínimo de jugadores"
              name="minimo_jugadores_por_equipo"
              type="number"
              min={5}
              max={30}
              value={formulario.minimo_jugadores_por_equipo}
              onChange={alCambiar}
              error={errores.minimo_jugadores_por_equipo}
            />
            <Campo
              etiqueta="Máximo de jugadores"
              name="maximo_jugadores_por_equipo"
              type="number"
              min={5}
              max={40}
              value={formulario.maximo_jugadores_por_equipo}
              onChange={alCambiar}
              error={errores.maximo_jugadores_por_equipo}
            />
          </div>

          <div className="rejilla-campos">
            <Campo
              etiqueta="Costo de inscripción"
              name="costo"
              type="number"
              min={0}
              step={1000}
              value={formulario.costo}
              onChange={alCambiar}
              error={errores.costo_inscripcion_centavos}
              pista="En pesos. Escribe 0 si el torneo es gratuito."
            />
            <Campo
              etiqueta="Cierre de inscripciones"
              name="inscripciones_cierran_en"
              type="datetime-local"
              value={formulario.inscripciones_cierran_en}
              onChange={alCambiar}
              error={errores.inscripciones_cierran_en}
            />
            <Campo
              etiqueta="Primera fecha"
              name="inicia_en"
              type="datetime-local"
              value={formulario.inicia_en}
              onChange={alCambiar}
              error={errores.inicia_en}
              pista="De aquí arranca el calendario."
            />
          </div>

          {error && <Aviso tono="error">{error}</Aviso>}

          <div className="dialogo__acciones">
            <Boton variante="contorno" type="button" onClick={cerrar}>
              Cancelar
            </Boton>
            <Boton type="submit" cargando={crear.isPending}>
              Crear torneo
            </Boton>
          </div>
        </form>
      </Dialogo>
    </>
  )
}

import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Shield } from 'lucide-react'
import { crearEquipo, erroresPorCampo, listarMisEquipos, mensajeDeError } from '../../lib/api'
import { Aviso, Boton, Campo, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'
import { Escudo } from '../../componentes/ui/Escudo'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_DUENO } from './enlacesDelPanel'

export default function MisEquipos() {
  const cliente = useQueryClient()
  const [dialogoAbierto, setDialogoAbierto] = useState(false)
  const [formulario, setFormulario] = useState({ nombre: '', abreviatura: '', ciudad: '' })
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')

  const equipos = useQuery({ queryKey: ['mis-equipos'], queryFn: listarMisEquipos })

  const crear = useMutation({
    mutationFn: crearEquipo,
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['mis-equipos'] })
      cerrarDialogo()
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo))
    },
  })

  function cerrarDialogo() {
    setDialogoAbierto(false)
    setFormulario({ nombre: '', abreviatura: '', ciudad: '' })
    setErrores({})
    setError('')
  }

  function alCambiar(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: value }))
    setErrores((actuales) => ({ ...actuales, [name]: undefined }))
  }

  return (
    <>
      <header className="panel-cabecera">
        <div>
          <h1>Mi panel</h1>
          <p>Aquí gestionas tus equipos, sus plantillas y sus inscripciones.</p>
        </div>
        <Boton onClick={() => setDialogoAbierto(true)} iconoIzquierda={<Plus size={16} />}>
          Nuevo equipo
        </Boton>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_DUENO} />

      {equipos.isPending && <Cargando />}
      {equipos.isError && <Aviso tono="error">{mensajeDeError(equipos.error)}</Aviso>}

      {equipos.data?.length === 0 && (
        <EstadoVacio
          icono={<Shield size={30} />}
          titulo="Todavía no tienes equipos"
          accion={
            <Boton onClick={() => setDialogoAbierto(true)} iconoIzquierda={<Plus size={16} />}>
              Crear mi primer equipo
            </Boton>
          }
        >
          Crea tu equipo, cárgale el escudo y los jugadores, y ya podrás inscribirlo en un torneo.
        </EstadoVacio>
      )}

      {equipos.data?.length > 0 && (
        <div className="rejilla rejilla--tres">
          {equipos.data.map((equipo) => (
            <Link className="tarjeta" key={equipo.id} to={`/panel/equipos/${equipo.slug}`}>
              <div style={{ display: 'flex', gap: '.9rem', alignItems: 'center' }}>
                <Escudo equipo={{ ...equipo, escudo_url: equipo.imagenes?.escudo_url }} tamano="grande" />
                <div style={{ minWidth: 0 }}>
                  <h3 style={{ fontSize: '.98rem' }}>{equipo.nombre}</h3>
                  <p style={{ color: 'var(--apagado)', fontSize: '.76rem', marginTop: '.2rem' }}>
                    {equipo.jugadores_count ?? 0} jugadores · {equipo.ciudad ?? 'Sin ciudad'}
                  </p>
                </div>
              </div>

              {(equipo.inscripciones ?? []).length > 0 && (
                <div style={{ display: 'flex', gap: '.35rem', flexWrap: 'wrap', marginTop: '1rem' }}>
                  {equipo.inscripciones.map((inscripcion) => (
                    <Insignia key={inscripcion.id} estado={inscripcion.estado} />
                  ))}
                </div>
              )}
            </Link>
          ))}
        </div>
      )}

      <Dialogo
        abierto={dialogoAbierto}
        titulo="Nuevo equipo"
        descripcion="Con el nombre y la abreviatura basta para empezar; el resto lo completas después."
        alCerrar={cerrarDialogo}
      >
        <form
          style={{ display: 'grid', gap: '1.1rem' }}
          onSubmit={(evento) => {
            evento.preventDefault()
            setError('')
            crear.mutate(formulario)
          }}
        >
          <Campo
            etiqueta="Nombre del equipo"
            name="nombre"
            value={formulario.nombre}
            onChange={alCambiar}
            error={errores.nombre}
            placeholder="Atlético Universidad"
            required
          />
          <div className="rejilla-campos">
            <Campo
              etiqueta="Abreviatura"
              name="abreviatura"
              value={formulario.abreviatura}
              onChange={alCambiar}
              error={errores.abreviatura}
              placeholder="ATU"
              maxLength={4}
              required
            />
            <Campo
              etiqueta="Ciudad"
              name="ciudad"
              value={formulario.ciudad}
              onChange={alCambiar}
              error={errores.ciudad}
              placeholder="Medellín"
            />
          </div>

          {error && <Aviso tono="error">{error}</Aviso>}

          <div className="dialogo__acciones">
            <Boton variante="contorno" type="button" onClick={cerrarDialogo}>
              Cancelar
            </Boton>
            <Boton type="submit" cargando={crear.isPending}>
              Crear equipo
            </Boton>
          </div>
        </form>
      </Dialogo>
    </>
  )
}

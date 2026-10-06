import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { crearTorneo, erroresPorCampo, listarTorneosAdmin, mensajeDeError } from '../../lib/api'
import { fechaYHora } from '../../lib/formato'
import { FormularioDeTorneo } from '../../componentes/torneo/FormularioDeTorneo'
import { EtiquetaDeJuego } from '../../componentes/juego/PortadaDeJuego'
import { Aviso, Boton, Cargando, EstadoVacio, Insignia } from '../../componentes/ui/Basicos'
import { Dialogo } from '../../componentes/ui/Dialogo'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

export default function TorneosAdmin() {
  const cliente = useQueryClient()
  const navegar = useNavigate()
  const [creando, setCreando] = useState(false)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')

  const torneos = useQuery({ queryKey: ['torneos-admin'], queryFn: () => listarTorneosAdmin() })

  const crear = useMutation({
    mutationFn: crearTorneo,
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['torneos-admin'] })
      navegar(`/organizacion/torneos/${respuesta.data.slug}`)
    },
    onError: (fallo) => {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo))
    },
  })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Torneos</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />

      <div className="fila" style={{ marginBottom: '1.5rem' }}>
        <Boton iconoIzquierda={<Plus size={16} />} onClick={() => setCreando(true)}>
          Nuevo torneo
        </Boton>
      </div>

      {torneos.isPending ? (
        <Cargando />
      ) : torneos.isError ? (
        <Aviso tono="error">{mensajeDeError(torneos.error)}</Aviso>
      ) : torneos.data.data.length === 0 ? (
        <EstadoVacio titulo="Todavía no hay torneos" />
      ) : (
        <div className="tabla-envoltura">
          <table className="tabla">
            <thead>
              <tr>
                <th>Torneo</th>
                <th>Formato</th>
                <th className="numero">Equipos</th>
                <th>Inicio</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {torneos.data.data.map((torneo) => (
                <tr key={torneo.id}>
                  <td>
                    <Link to={`/organizacion/torneos/${torneo.slug}`} style={{ display: 'grid' }}>
                      <strong>{torneo.nombre}</strong>
                      <EtiquetaDeJuego juego={torneo.juego.valor} />
                    </Link>
                  </td>
                  <td>{torneo.formato.etiqueta}</td>
                  <td className="numero">
                    {torneo.inscripcion.equipos_inscritos}/{torneo.ajustes.cupo_maximo_equipos}
                  </td>
                  <td>{fechaYHora(torneo.fechas.inicia_en)}</td>
                  <td>
                    <Insignia estado={torneo.estado} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialogo abierto={creando} titulo="Nuevo torneo" ancho alCerrar={() => setCreando(false)}>
        <FormularioDeTorneo
          errores={errores}
          error={error}
          cargando={crear.isPending}
          alGuardar={(datos) => {
            setErrores({})
            setError('')
            crear.mutate(datos)
          }}
          alCancelar={() => setCreando(false)}
        />
      </Dialogo>
    </div>
  )
}

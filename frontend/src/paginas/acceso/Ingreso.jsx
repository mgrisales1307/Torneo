import { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useSesion } from '../../contexto/contextoDeSesion'
import { mensajeDeError } from '../../lib/api'
import { Aviso, Boton, Campo, Interruptor } from '../../componentes/ui/Basicos'
import { PantallaDeAcceso } from './PantallaDeAcceso'

export default function Ingreso() {
  const { estaAutenticado, cargandoSesion, ingresar } = useSesion()
  const navegar = useNavigate()
  const ubicacion = useLocation()
  const destino = ubicacion.state?.destino ?? '/panel'

  const [credenciales, setCredenciales] = useState({ correo: '', clave: '', recordarme: false })
  const [error, setError] = useState('')

  // Quien ya entró no tiene nada que hacer en el formulario de ingreso.
  if (!cargandoSesion && estaAutenticado) {
    return <Navigate to={destino} replace />
  }

  function alCambiarCampo(evento) {
    const { name, type, checked, value } = evento.target

    setCredenciales((actuales) => ({ ...actuales, [name]: type === 'checkbox' ? checked : value }))
    setError('')
  }

  async function alEnviar(evento) {
    evento.preventDefault()

    try {
      await ingresar.mutateAsync({
        correo: credenciales.correo.trim(),
        clave: credenciales.clave,
        recordarme: credenciales.recordarme,
      })

      navegar(destino, { replace: true })
    } catch (fallo) {
      setError(mensajeDeError(fallo, 'No fue posible iniciar sesión.'))
      setCredenciales((actuales) => ({ ...actuales, clave: '' }))
    }
  }

  return (
    <PantallaDeAcceso>
      <header>
        <h1 className="titulo-pagina">Entrar</h1>
      </header>

      <form className="formulario" onSubmit={alEnviar} noValidate>
        <Campo
          etiqueta="Correo"
          name="correo"
          type="email"
          autoComplete="email"
          value={credenciales.correo}
          onChange={alCambiarCampo}
          required
        />
        <Campo
          etiqueta="Contraseña"
          name="clave"
          type="password"
          autoComplete="current-password"
          value={credenciales.clave}
          onChange={alCambiarCampo}
          required
        />
        <Interruptor etiqueta="Recordarme" name="recordarme" checked={credenciales.recordarme} onChange={alCambiarCampo} />

        {error && <Aviso tono="error">{error}</Aviso>}

        <Boton type="submit" className="boton--ancho" cargando={ingresar.isPending}>
          Entrar
        </Boton>
      </form>

      <p className="acceso__pie">
        ¿No tienes cuenta? <Link to="/registro">Crear cuenta</Link>
      </p>
    </PantallaDeAcceso>
  )
}

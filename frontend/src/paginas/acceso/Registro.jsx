import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useSesion } from '../../contexto/contextoDeSesion'
import { erroresPorCampo, mensajeDeError } from '../../lib/api'
import { Aviso, Boton, Campo } from '../../componentes/ui/Basicos'
import { PantallaDeAcceso } from './PantallaDeAcceso'

const FORMULARIO_VACIO = {
  apodo: '',
  nombre: '',
  correo: '',
  clave: '',
  clave_confirmation: '',
}

export default function Registro() {
  const { estaAutenticado, cargandoSesion, registrarse } = useSesion()
  const navegar = useNavigate()

  const [formulario, setFormulario] = useState(FORMULARIO_VACIO)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')

  if (!cargandoSesion && estaAutenticado && !registrarse.isPending && !registrarse.isSuccess) {
    return <Navigate to="/panel" replace />
  }

  function alCambiarCampo(evento) {
    const { name, value } = evento.target

    setFormulario((actual) => ({ ...actual, [name]: value }))
    setErrores((actuales) => ({ ...actuales, [name]: undefined }))
    setError('')
  }

  async function alEnviar(evento) {
    evento.preventDefault()

    if (formulario.clave !== formulario.clave_confirmation) {
      setErrores({ clave_confirmation: 'Las dos contraseñas deben ser iguales.' })
      return
    }

    try {
      await registrarse.mutateAsync(formulario)
      navegar('/panel/cuentas', { replace: true })
    } catch (fallo) {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo, 'No fue posible crear la cuenta.'))
    }
  }

  return (
    <PantallaDeAcceso>
      <header>
        <h1 className="titulo-pagina">Crear cuenta</h1>
      </header>

      <form className="formulario" onSubmit={alEnviar} noValidate>
        <Campo
          etiqueta="Apodo"
          name="apodo"
          autoComplete="nickname"
          pista="Público. Letras, números, guion y guion bajo."
          value={formulario.apodo}
          onChange={alCambiarCampo}
          error={errores.apodo}
          maxLength={20}
        />
        <Campo
          etiqueta="Nombre completo"
          name="nombre"
          autoComplete="name"
          value={formulario.nombre}
          onChange={alCambiarCampo}
          error={errores.nombre}
        />
        <Campo
          etiqueta="Correo"
          name="correo"
          type="email"
          autoComplete="email"
          value={formulario.correo}
          onChange={alCambiarCampo}
          error={errores.correo}
        />
        <div className="formulario__fila">
          <Campo
            etiqueta="Contraseña"
            name="clave"
            type="password"
            autoComplete="new-password"
            value={formulario.clave}
            onChange={alCambiarCampo}
            error={errores.clave}
          />
          <Campo
            etiqueta="Repítela"
            name="clave_confirmation"
            type="password"
            autoComplete="new-password"
            value={formulario.clave_confirmation}
            onChange={alCambiarCampo}
            error={errores.clave_confirmation}
          />
        </div>

        {error && !Object.keys(errores).length && <Aviso tono="error">{error}</Aviso>}

        <Boton type="submit" className="boton--ancho" cargando={registrarse.isPending}>
          Crear cuenta
        </Boton>
      </form>

      <p className="acceso__pie">
        ¿Ya tienes cuenta? <Link to="/ingreso">Entrar</Link>
      </p>
    </PantallaDeAcceso>
  )
}

import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { ArrowUpRight, LoaderCircle } from 'lucide-react'
import { useSesion } from '../../contexto/contextoDeSesion'
import { erroresPorCampo, mensajeDeError } from '../../lib/api'
import { PantallaDeAcceso } from './PantallaDeAcceso'

const FORMULARIO_VACIO = {
  nombre: '',
  correo: '',
  telefono: '',
  clave: '',
  clave_confirmation: '',
}

export default function Registro() {
  const { estaAutenticado, cargandoSesion, registrarse } = useSesion()
  const navegar = useNavigate()

  const [formulario, setFormulario] = useState(FORMULARIO_VACIO)
  const [errores, setErrores] = useState({})
  const [error, setError] = useState('')

  if (!cargandoSesion && estaAutenticado) {
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
    setError('')
    setErrores({})

    if (formulario.clave !== formulario.clave_confirmation) {
      setErrores({ clave_confirmation: 'Las dos contraseñas deben ser iguales.' })
      return
    }

    try {
      await registrarse.mutateAsync(formulario)
      navegar('/panel', { replace: true })
    } catch (fallo) {
      setErrores(erroresPorCampo(fallo))
      setError(mensajeDeError(fallo, 'No fue posible crear la cuenta.'))
    }
  }

  const ocupado = registrarse.isPending

  return (
    <PantallaDeAcceso numero="02">
      <section aria-labelledby="titulo-registro">
        <header className="formulario-encabezado">
          <p className="indice-seccion">Crear cuenta</p>
          <h2 id="titulo-registro">
            Inscribe
            <br />
            tu equipo.
          </h2>
          <p>Crea tu cuenta de dueño y empieza a armar la plantilla.</p>
        </header>

        <form className="formulario-acceso" onSubmit={alEnviar} noValidate aria-busy={ocupado}>
          <CampoDeAcceso
            etiqueta="Nombre completo"
            name="nombre"
            value={formulario.nombre}
            onChange={alCambiarCampo}
            autoComplete="name"
            placeholder="Tu nombre"
            error={errores.nombre}
            disabled={ocupado}
          />

          <CampoDeAcceso
            etiqueta="Correo electrónico"
            name="correo"
            type="email"
            value={formulario.correo}
            onChange={alCambiarCampo}
            autoComplete="email"
            placeholder="nombre@equipo.com"
            error={errores.correo}
            disabled={ocupado}
          />

          <CampoDeAcceso
            etiqueta="Teléfono (opcional)"
            name="telefono"
            value={formulario.telefono}
            onChange={alCambiarCampo}
            autoComplete="tel"
            placeholder="300 000 0000"
            error={errores.telefono}
            disabled={ocupado}
          />

          <CampoDeAcceso
            etiqueta="Contraseña"
            name="clave"
            type="password"
            value={formulario.clave}
            onChange={alCambiarCampo}
            autoComplete="new-password"
            placeholder="Mínimo 8 caracteres, con letras y números"
            error={errores.clave}
            disabled={ocupado}
          />

          <CampoDeAcceso
            etiqueta="Repite la contraseña"
            name="clave_confirmation"
            type="password"
            value={formulario.clave_confirmation}
            onChange={alCambiarCampo}
            autoComplete="new-password"
            placeholder="La misma contraseña"
            error={errores.clave_confirmation}
            disabled={ocupado}
          />

          <div className="ranura-aviso" aria-live="polite">
            {error && (
              <p className="formulario-error" role="alert">
                {error}
              </p>
            )}
          </div>

          <button className="boton-principal" type="submit" disabled={ocupado}>
            <span>{ocupado ? 'Creando tu cuenta' : 'Crear cuenta'}</span>
            {ocupado ? <LoaderCircle className="girador" size={19} /> : <ArrowUpRight size={19} />}
          </button>
        </form>

        <p style={{ marginTop: '1.75rem', fontSize: '.78rem', color: 'var(--apagado)' }}>
          ¿Ya tienes cuenta? <Link to="/ingreso">Entrar</Link>
        </p>
      </section>
    </PantallaDeAcceso>
  )
}

function CampoDeAcceso({ etiqueta, error, name, ...resto }) {
  return (
    <div className="grupo-campo">
      <label htmlFor={name}>{etiqueta}</label>
      <div className="campo-caja">
        <input id={name} name={name} aria-invalid={Boolean(error)} {...resto} />
      </div>
      {error && (
        <p className="formulario-error" style={{ marginTop: '.4rem' }} role="alert">
          {error}
        </p>
      )}
    </div>
  )
}

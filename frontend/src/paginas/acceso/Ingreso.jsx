import { useId, useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { ArrowUpRight, Check, Eye, EyeOff, LoaderCircle, LockKeyhole, Mail } from 'lucide-react'
import { useSesion } from '../../contexto/contextoDeSesion'
import { mensajeDeError } from '../../lib/api'
import { PantallaDeAcceso } from './PantallaDeAcceso'

export default function Ingreso() {
  const identificadorDelError = useId()
  const { estaAutenticado, cargandoSesion, ingresar } = useSesion()
  const navegar = useNavigate()
  const ubicacion = useLocation()

  const [credenciales, setCredenciales] = useState({ correo: '', clave: '', recordarme: false })
  const [claveVisible, setClaveVisible] = useState(false)
  const [error, setError] = useState('')

  // Quien ya entró no tiene nada que hacer en el formulario de ingreso.
  if (!cargandoSesion && estaAutenticado) {
    return <Navigate to={ubicacion.state?.destino ?? '/panel'} replace />
  }

  function alCambiarCampo(evento) {
    const { name, type, checked, value } = evento.target

    setCredenciales((actuales) => ({ ...actuales, [name]: type === 'checkbox' ? checked : value }))

    if (error) setError('')
  }

  async function alEnviar(evento) {
    evento.preventDefault()
    setError('')

    if (!credenciales.correo.trim() || !credenciales.clave) {
      setError('Ingresa tu correo y contraseña para continuar.')
      return
    }

    try {
      await ingresar.mutateAsync({
        correo: credenciales.correo.trim(),
        clave: credenciales.clave,
        recordarme: credenciales.recordarme,
      })

      navegar(ubicacion.state?.destino ?? '/panel', { replace: true })
    } catch (fallo) {
      setError(mensajeDeError(fallo, 'No fue posible iniciar sesión.'))
      setCredenciales((actuales) => ({ ...actuales, clave: '' }))
    }
  }

  const ocupado = ingresar.isPending || cargandoSesion

  return (
    <PantallaDeAcceso numero="01">
      <section aria-labelledby="titulo-ingreso">
        <header className="formulario-encabezado">
          <p className="indice-seccion">Acceso a plataforma</p>
          <h2 id="titulo-ingreso">
            Bienvenido
            <br />
            de nuevo.
          </h2>
          <p>Ingresa tus datos para continuar al torneo.</p>
        </header>

        <form className="formulario-acceso" onSubmit={alEnviar} noValidate aria-busy={ocupado}>
          <div className="grupo-campo">
            <label htmlFor="correo">Correo electrónico</label>
            <div className="campo-caja">
              <Mail size={18} aria-hidden="true" />
              <input
                id="correo"
                name="correo"
                type="email"
                value={credenciales.correo}
                onChange={alCambiarCampo}
                placeholder="nombre@equipo.com"
                autoComplete="email"
                inputMode="email"
                aria-describedby={error ? identificadorDelError : undefined}
                aria-invalid={Boolean(error)}
                disabled={ocupado}
              />
            </div>
          </div>

          <div className="grupo-campo">
            <label htmlFor="clave">Contraseña</label>
            <div className="campo-caja">
              <LockKeyhole size={18} aria-hidden="true" />
              <input
                id="clave"
                name="clave"
                type={claveVisible ? 'text' : 'password'}
                value={credenciales.clave}
                onChange={alCambiarCampo}
                placeholder="Tu contraseña"
                autoComplete="current-password"
                aria-describedby={error ? identificadorDelError : undefined}
                aria-invalid={Boolean(error)}
                disabled={ocupado}
              />
              <button
                className="alternar-clave"
                type="button"
                onClick={() => setClaveVisible((visible) => !visible)}
                aria-label={claveVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                aria-pressed={claveVisible}
                disabled={ocupado}
              >
                {claveVisible ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
          </div>

          <label className="control-recordar">
            <input
              name="recordarme"
              type="checkbox"
              checked={credenciales.recordarme}
              onChange={alCambiarCampo}
              disabled={ocupado}
            />
            <span className="casilla" aria-hidden="true">
              <Check size={12} />
            </span>
            Mantener mi sesión
          </label>

          <div className="ranura-aviso" aria-live="polite">
            {error && (
              <p id={identificadorDelError} className="formulario-error" role="alert">
                {error}
              </p>
            )}
          </div>

          <button className="boton-principal" type="submit" disabled={ocupado}>
            <span>{ingresar.isPending ? 'Validando acceso' : 'Entrar al torneo'}</span>
            {ocupado ? <LoaderCircle className="girador" size={19} /> : <ArrowUpRight size={19} />}
          </button>
        </form>

        <p style={{ marginTop: '1.75rem', fontSize: '.78rem', color: 'var(--apagado)' }}>
          ¿Aún no tienes cuenta? <Link to="/registro">Inscribe tu equipo</Link>
        </p>
      </section>
    </PantallaDeAcceso>
  )
}

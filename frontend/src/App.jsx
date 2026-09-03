import { useEffect, useId, useState } from 'react'
import {
  ArrowUpRight,
  Check,
  Eye,
  EyeOff,
  LoaderCircle,
  LockKeyhole,
  LogOut,
  Mail,
  ShieldCheck,
} from 'lucide-react'
import { getCurrentUser, getLoginError, isUnauthenticated, login, logout } from './lib/api'
import './App.css'

const roleLabels = {
  admin: 'Administración',
  empleado: 'Organización',
  user: 'Participante',
}

function App() {
  const errorId = useId()
  const [credentials, setCredentials] = useState({ email: '', password: '', remember: false })
  const [isPasswordVisible, setIsPasswordVisible] = useState(false)
  const [status, setStatus] = useState('checking')
  const [user, setUser] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let isActive = true

    getCurrentUser()
      .then((currentUser) => {
        if (isActive) {
          setUser(currentUser)
        }
      })
      .catch((requestError) => {
        if (isActive && !isUnauthenticated(requestError)) {
          setError('El servicio no está disponible en este momento. Puedes intentar acceder de nuevo.')
        }
      })
      .finally(() => {
        if (isActive) {
          setStatus('idle')
        }
      })

    return () => {
      isActive = false
    }
  }, [])

  function handleFieldChange(event) {
    const { name, type, checked, value } = event.target

    setCredentials((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))

    if (error) {
      setError('')
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')

    if (!credentials.email.trim() || !credentials.password) {
      setError('Ingresa tu correo y contraseña para continuar.')
      return
    }

    setStatus('submitting')

    try {
      const authenticatedUser = await login({
        email: credentials.email.trim(),
        password: credentials.password,
        remember: credentials.remember,
      })

      setUser(authenticatedUser)
      setCredentials((current) => ({ ...current, password: '' }))
    } catch (requestError) {
      setError(getLoginError(requestError))
    } finally {
      setStatus('idle')
    }
  }

  async function handleLogout() {
    setStatus('submitting')
    setError('')

    try {
      await logout()
      setUser(null)
    } catch (requestError) {
      if (isUnauthenticated(requestError)) {
        setUser(null)
      } else {
        setError('No pudimos cerrar la sesión. Inténtalo nuevamente.')
      }
    } finally {
      setStatus('idle')
    }
  }

  const isBusy = status === 'checking' || status === 'submitting'

  return (
    <main className="login-shell">
      <section className="brand-panel" aria-label="Juegos Zone Torneos">
        <div className="brand-topline">
          <a className="brand-lockup" href="/" aria-label="Juegos Zone, inicio">
            <span className="brand-mark" aria-hidden="true">JZ</span>
            <span>Juegos Zone</span>
          </a>
          <span className="edition">Torneos / 2026</span>
        </div>

        <div className="brand-copy">
          <p className="eyebrow"><span /> El fútbol se vive aquí</p>
          <h1>
            Cada partido.<br />
            <em>Cada historia.</em>
          </h1>
          <p className="brand-description">
            La competencia en un solo lugar. Sigue el ritmo del torneo desde la primera fecha hasta la final.
          </p>
        </div>

        <div className="competition-strip" aria-label="Funciones de la plataforma">
          <div><span>01</span><strong>Partidos</strong></div>
          <div><span>02</span><strong>Posiciones</strong></div>
          <div><span>03</span><strong>Estadísticas</strong></div>
        </div>

        <span className="orb orb-one" aria-hidden="true" />
        <span className="orb orb-two" aria-hidden="true" />
      </section>

      <section className="access-panel">
        <div className="access-topline">
          <span className="secure-note"><ShieldCheck size={15} strokeWidth={1.8} /> Acceso seguro</span>
          <span className="access-number">JZ — 01</span>
        </div>

        <div className="access-card">
          {user ? (
            <section className="session-view" aria-labelledby="session-title">
              <div className="session-check" aria-hidden="true"><Check size={22} strokeWidth={2} /></div>
              <p className="section-index">Sesión activa</p>
              <h2 id="session-title">Todo listo,<br />{user.name}.</h2>
              <p className="session-description">Tu acceso a Juegos Zone está confirmado.</p>

              <div className="profile-card">
                <div className="avatar" aria-hidden="true">
                  {user.name?.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase() || 'JZ'}
                </div>
                <div>
                  <strong>{user.name}</strong>
                  <span>{user.email}</span>
                </div>
                <span className="role-badge">{roleLabels[user.role] ?? user.role}</span>
              </div>

              {error && <p className="form-error" role="alert">{error}</p>}

              <button className="secondary-button" type="button" onClick={handleLogout} disabled={isBusy}>
                {status === 'submitting' ? <LoaderCircle className="spinner" size={18} /> : <LogOut size={18} />}
                Cerrar sesión
              </button>
            </section>
          ) : (
            <section aria-labelledby="login-title">
              <header className="form-header">
                <p className="section-index">Acceso a plataforma</p>
                <h2 id="login-title">Bienvenido<br />de nuevo.</h2>
                <p>Ingresa tus datos para continuar al torneo.</p>
              </header>

              <form className="login-form" onSubmit={handleSubmit} noValidate aria-busy={isBusy}>
                <div className="field-group">
                  <label htmlFor="email">Correo electrónico</label>
                  <div className="input-shell">
                    <Mail size={18} aria-hidden="true" />
                    <input
                      id="email"
                      name="email"
                      type="email"
                      value={credentials.email}
                      onChange={handleFieldChange}
                      placeholder="nombre@equipo.com"
                      autoComplete="email"
                      inputMode="email"
                      aria-describedby={error ? errorId : undefined}
                      aria-invalid={Boolean(error)}
                      disabled={isBusy}
                    />
                  </div>
                </div>

                <div className="field-group">
                  <label htmlFor="password">Contraseña</label>
                  <div className="input-shell">
                    <LockKeyhole size={18} aria-hidden="true" />
                    <input
                      id="password"
                      name="password"
                      type={isPasswordVisible ? 'text' : 'password'}
                      value={credentials.password}
                      onChange={handleFieldChange}
                      placeholder="Tu contraseña"
                      autoComplete="current-password"
                      aria-describedby={error ? errorId : undefined}
                      aria-invalid={Boolean(error)}
                      disabled={isBusy}
                    />
                    <button
                      className="password-toggle"
                      type="button"
                      onClick={() => setIsPasswordVisible((visible) => !visible)}
                      aria-label={isPasswordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                      aria-pressed={isPasswordVisible}
                      disabled={isBusy}
                    >
                      {isPasswordVisible ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                </div>

                <label className="remember-control">
                  <input
                    name="remember"
                    type="checkbox"
                    checked={credentials.remember}
                    onChange={handleFieldChange}
                    disabled={isBusy}
                  />
                  <span className="custom-checkbox" aria-hidden="true"><Check size={12} /></span>
                  Mantener mi sesión
                </label>

                <div className="feedback-slot" aria-live="polite">
                  {error && <p id={errorId} className="form-error" role="alert">{error}</p>}
                </div>

                <button className="primary-button" type="submit" disabled={isBusy}>
                  <span>{status === 'submitting' ? 'Validando acceso' : status === 'checking' ? 'Comprobando sesión' : 'Entrar al torneo'}</span>
                  {isBusy ? <LoaderCircle className="spinner" size={19} /> : <ArrowUpRight size={19} />}
                </button>
              </form>
            </section>
          )}
        </div>

        <footer className="access-footer">
          <span>Gestión de competencia</span>
          <span>© 2026 Juegos Zone</span>
        </footer>
      </section>
    </main>
  )
}

export default App

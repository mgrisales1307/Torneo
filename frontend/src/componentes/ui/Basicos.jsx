import { AlertCircle, CheckCircle2, Info, LoaderCircle, TriangleAlert } from 'lucide-react'

/*
 * Piezas sueltas de interfaz. Son deliberadamente tontas: reciben props y
 * pintan. Ninguna sabe de la API ni del enrutador.
 */

export function Boton({
  variante = 'solido',
  tamano,
  cargando = false,
  iconoIzquierda,
  children,
  className = '',
  disabled,
  ...resto
}) {
  const clases = [
    'boton',
    `boton--${variante}`,
    tamano === 'chico' ? 'boton--chico' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <button className={clases} disabled={disabled || cargando} {...resto}>
      {cargando ? <LoaderCircle className="girador" size={16} aria-hidden="true" /> : iconoIzquierda}
      {children}
    </button>
  )
}

export function Campo({ etiqueta, error, pista, id, children, ...resto }) {
  const identificador = id ?? resto.name

  return (
    <label className={`campo ${error ? 'campo--invalido' : ''}`} htmlFor={identificador}>
      <span className="campo__etiqueta">{etiqueta}</span>
      {children ?? (
        <input
          id={identificador}
          className="campo__control"
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${identificador}-error` : undefined}
          {...resto}
        />
      )}
      {pista && !error && <span className="campo__pista">{pista}</span>}
      {error && (
        <span className="campo__error" id={`${identificador}-error`} role="alert">
          {error}
        </span>
      )}
    </label>
  )
}

export function CampoDeSeleccion({ etiqueta, error, pista, opciones = [], id, ...resto }) {
  const identificador = id ?? resto.name

  return (
    <Campo etiqueta={etiqueta} error={error} pista={pista} id={identificador}>
      <select id={identificador} className="campo__control" aria-invalid={Boolean(error)} {...resto}>
        {opciones.map((opcion) => (
          <option key={opcion.valor} value={opcion.valor}>
            {opcion.etiqueta}
          </option>
        ))}
      </select>
    </Campo>
  )
}

export function CampoDeTexto({ etiqueta, error, pista, id, ...resto }) {
  const identificador = id ?? resto.name

  return (
    <Campo etiqueta={etiqueta} error={error} pista={pista} id={identificador}>
      <textarea id={identificador} className="campo__control" aria-invalid={Boolean(error)} {...resto} />
    </Campo>
  )
}

export function CampoDeColor({ etiqueta, error, value, onChange, name }) {
  return (
    <Campo etiqueta={etiqueta} error={error} id={name}>
      <span className="campo-color">
        <input type="color" id={name} name={name} value={value || '#0B1D17'} onChange={onChange} />
        <input
          className="campo__control"
          name={name}
          value={value || ''}
          onChange={onChange}
          placeholder="#0B1D17"
          maxLength={7}
          aria-label={`${etiqueta} en hexadecimal`}
        />
      </span>
    </Campo>
  )
}

export function Interruptor({ etiqueta, ...resto }) {
  return (
    <label className="campo-interruptor">
      <input type="checkbox" {...resto} />
      {etiqueta}
    </label>
  )
}

export function Tarjeta({ titulo, accion, children, plana = false, className = '' }) {
  return (
    <section className={`tarjeta ${plana ? 'tarjeta--plana' : ''} ${className}`}>
      {(titulo || accion) && (
        <header className="tarjeta__titulo">
          {typeof titulo === 'string' ? <h2>{titulo}</h2> : titulo}
          {accion}
        </header>
      )}
      {children}
    </section>
  )
}

const TONOS_DE_INSIGNIA = {
  borrador: 'neutra',
  inscripciones_abiertas: 'exito',
  inscripciones_cerradas: 'aviso',
  en_curso: 'acento',
  finalizado: 'neutra',
  cancelado: 'error',
  pendiente_pago: 'aviso',
  pendiente_revision: 'info',
  aprobada: 'exito',
  rechazada: 'error',
  retirada: 'neutra',
  programado: 'info',
  en_juego: 'acento',
  aplazado: 'aviso',
  pendiente: 'aviso',
  pagada: 'exito',
  fallida: 'error',
  expirada: 'neutra',
  activo: 'exito',
  lesionado: 'aviso',
  suspendido: 'error',
  inactivo: 'neutra',
}

/**
 * Insignia de estado. El tono sale del valor del estado que manda la API, así
 * que un estado nuevo en el backend aparece aquí sin tocar nada (en gris).
 */
export function Insignia({ estado, tono, children }) {
  const elegido = tono ?? TONOS_DE_INSIGNIA[estado?.valor ?? estado] ?? 'neutra'

  return <span className={`insignia insignia--${elegido}`}>{children ?? estado?.etiqueta ?? estado}</span>
}

const ICONOS_DE_AVISO = {
  error: AlertCircle,
  exito: CheckCircle2,
  info: Info,
  atencion: TriangleAlert,
}

export function Aviso({ tono = 'info', children }) {
  const Icono = ICONOS_DE_AVISO[tono] ?? Info

  return (
    <p className={`aviso aviso--${tono}`} role={tono === 'error' ? 'alert' : 'status'}>
      <Icono size={16} aria-hidden="true" />
      <span>{children}</span>
    </p>
  )
}

export function Cargando({ texto = 'Cargando…' }) {
  return (
    <div className="cargando" role="status">
      <LoaderCircle className="girador" size={22} aria-hidden="true" />
      {texto}
    </div>
  )
}

export function EstadoVacio({ icono, titulo, children, accion }) {
  return (
    <div className="estado-vacio">
      {icono}
      <h3>{titulo}</h3>
      {children && <p>{children}</p>}
      {accion}
    </div>
  )
}

export function Forma({ resultados = [] }) {
  if (resultados.length === 0) return <span aria-hidden="true">—</span>

  return (
    <span className="forma" aria-label={`Últimos resultados: ${resultados.join(', ')}`}>
      {resultados.map((resultado, indice) => (
        <span className={`forma__marca forma__marca--${resultado}`} key={`${resultado}-${indice}`} aria-hidden="true">
          {resultado}
        </span>
      ))}
    </span>
  )
}

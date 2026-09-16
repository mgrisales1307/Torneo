import { useEffect, useRef } from 'react'
import { X } from 'lucide-react'
import { Boton } from './Basicos'

/**
 * Ventana modal.
 *
 * Se cierra con Escape y haciendo clic fuera, y al abrirse mueve el foco dentro
 * para que quien navegue con teclado no se quede atrás del diálogo.
 */
export function Dialogo({ abierto, titulo, descripcion, ancho = false, alCerrar, children }) {
  const contenedor = useRef(null)

  useEffect(() => {
    if (!abierto) return undefined

    function alPulsarTecla(evento) {
      if (evento.key === 'Escape') alCerrar()
    }

    document.addEventListener('keydown', alPulsarTecla)
    document.body.style.overflow = 'hidden'
    contenedor.current?.focus()

    return () => {
      document.removeEventListener('keydown', alPulsarTecla)
      document.body.style.overflow = ''
    }
  }, [abierto, alCerrar])

  if (!abierto) return null

  return (
    <div
      className="dialogo-fondo"
      role="presentation"
      onMouseDown={(evento) => {
        if (evento.target === evento.currentTarget) alCerrar()
      }}
    >
      <div
        className={`dialogo ${ancho ? 'dialogo--ancho' : ''}`}
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
        tabIndex={-1}
        ref={contenedor}
      >
        <header className="dialogo__encabezado">
          <div>
            <h2>{titulo}</h2>
            {descripcion && <p>{descripcion}</p>}
          </div>
          <Boton variante="fantasma" tamano="chico" onClick={alCerrar} aria-label="Cerrar">
            <X size={16} />
          </Boton>
        </header>
        {children}
      </div>
    </div>
  )
}

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

  /*
   * La función de cierre se guarda en una referencia, y el efecto de abajo
   * depende solo de `abierto`.
   *
   * No es un adorno: quien usa este componente le pasa a `alCerrar` una
   * función declarada en el cuerpo de su propio componente, así que es una
   * función distinta en cada render. Si el efecto dependiera de ella, cada
   * tecla escrita en un campo del formulario re-renderizaría al padre,
   * volvería a ejecutar el efecto y el `focus()` de abajo le quitaría el foco
   * al campo. El resultado era que solo entraba la primera letra.
   */
  const alCerrarRef = useRef(alCerrar)

  useEffect(() => {
    alCerrarRef.current = alCerrar
  }, [alCerrar])

  useEffect(() => {
    if (!abierto) return undefined

    function alPulsarTecla(evento) {
      if (evento.key === 'Escape') alCerrarRef.current()
    }

    document.addEventListener('keydown', alPulsarTecla)
    document.body.style.overflow = 'hidden'
    contenedor.current?.focus()

    return () => {
      document.removeEventListener('keydown', alPulsarTecla)
      document.body.style.overflow = ''
    }
  }, [abierto])

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

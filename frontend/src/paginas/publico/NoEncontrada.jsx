import { Link } from 'react-router-dom'
import { Compass } from 'lucide-react'
import { EstadoVacio } from '../../componentes/ui/Basicos'

export default function NoEncontrada() {
  return (
    <EstadoVacio
      icono={<Compass size={30} />}
      titulo="Esta página no existe"
      accion={
        <Link className="boton boton--solido" to="/">
          Volver al inicio
        </Link>
      }
    >
      Puede que el enlace esté mal escrito o que la página se haya movido.
    </EstadoVacio>
  )
}

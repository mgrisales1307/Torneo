import { Outlet } from 'react-router-dom'
import { Cabecera } from './Cabecera'
import { PieDePagina } from './PieDePagina'

/**
 * Armazón de todas las páginas con cabecera y pie: la portada, los torneos y
 * los dos paneles. La pantalla de acceso va aparte, a sangre completa.
 */
export function Sitio() {
  return (
    <div className="sitio">
      <a className="salto-contenido" href="#contenido">
        Saltar al contenido
      </a>
      <Cabecera />
      <main className="sitio__contenido" id="contenido">
        <div className="contenedor">
          <Outlet />
        </div>
      </main>
      <PieDePagina />
    </div>
  )
}

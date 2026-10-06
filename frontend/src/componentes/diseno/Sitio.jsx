import { Outlet, useLocation } from 'react-router-dom'
import { Cabecera } from './Cabecera'
import { PieDePagina } from './PieDePagina'

/**
 * Armazón de todas las páginas con cabecera y pie: la portada, los torneos y
 * los dos paneles. La pantalla de acceso va aparte, a sangre completa.
 *
 * La portada no va dentro del contenedor: el vídeo de bienvenida ocupa todo el
 * ancho, y cada sección de la portada pone su propio contenedor.
 */
export function Sitio() {
  const { pathname } = useLocation()
  const esPortada = pathname === '/'

  return (
    <div className="sitio">
      <a className="salto-contenido" href="#contenido">
        Saltar al contenido
      </a>
      <Cabecera />
      <main className={`sitio__contenido ${esPortada ? 'sitio__contenido--portada' : ''}`} id="contenido">
        {esPortada ? (
          <Outlet />
        ) : (
          <div className="contenedor">
            <Outlet />
          </div>
        )}
      </main>
      <PieDePagina />
    </div>
  )
}

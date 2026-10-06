import { Link, Navigate, Outlet, useLocation } from 'react-router-dom'
import { useSesion } from '../../contexto/contextoDeSesion'
import { Cargando, EstadoVacio } from '../ui/Basicos'
import { ShieldAlert } from 'lucide-react'

/**
 * Puerta de las zonas con sesión.
 *
 * Es comodidad, no seguridad: quien quite esto desde el navegador solo verá
 * pantallas vacías, porque cada dato lo vuelve a autorizar el backend. Lo que
 * evita es enviar a alguien a un panel donde todo le va a responder 403.
 */
export function RutaProtegida({ rolesPermitidos }) {
  const { cargandoSesion, estaAutenticado, rol } = useSesion()
  const ubicacion = useLocation()

  if (cargandoSesion) {
    return <Cargando texto="Comprobando tu sesión…" />
  }

  if (!estaAutenticado) {
    // Se recuerda a dónde iba para volver ahí después de entrar.
    return <Navigate to="/ingreso" state={{ destino: ubicacion.pathname }} replace />
  }

  if (rolesPermitidos && !rolesPermitidos.includes(rol)) {
    return (
      <EstadoVacio
        icono={<ShieldAlert size={30} />}
        titulo="Esta zona es de la organización"
        accion={
          <Link className="boton boton--contorno boton--chico" to="/">
            Volver al inicio
          </Link>
        }
      />
    )
  }

  return <Outlet />
}

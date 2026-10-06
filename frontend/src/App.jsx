import { useEffect } from 'react'
import { Route, Routes, useLocation } from 'react-router-dom'
import { Sitio } from './componentes/diseno/Sitio'
import { RutaProtegida } from './componentes/diseno/RutaProtegida'

import Inicio from './paginas/publico/Inicio'
import Juego from './paginas/publico/Juego'
import Torneos from './paginas/publico/Torneos'
import Torneo from './paginas/publico/Torneo'
import Partido from './paginas/publico/Partido'
import Equipo from './paginas/publico/Equipo'
import Jugador from './paginas/publico/Jugador'
import NoEncontrada from './paginas/publico/NoEncontrada'

import Ingreso from './paginas/acceso/Ingreso'
import Registro from './paginas/acceso/Registro'

import ResumenDelPanel from './paginas/panel/ResumenDelPanel'
import MisEquipos from './paginas/panel/MisEquipos'
import EquipoDelPanel from './paginas/panel/EquipoDelPanel'
import Inscripciones from './paginas/panel/Inscripciones'
import Cuentas from './paginas/panel/Cuentas'
import Pago from './paginas/panel/Pago'

import ResumenOrganizacion from './paginas/admin/ResumenOrganizacion'
import TorneosAdmin from './paginas/admin/TorneosAdmin'
import TorneoAdmin from './paginas/admin/TorneoAdmin'
import InscripcionesAdmin from './paginas/admin/InscripcionesAdmin'
import DisputasAdmin from './paginas/admin/DisputasAdmin'
import UsuariosAdmin from './paginas/admin/UsuariosAdmin'

/**
 * Mapa de la aplicación.
 *
 *   /               juegos, torneos, cuadros, equipos y jugadores: abierto a cualquiera
 *   /ingreso        entrada y registro, a pantalla completa
 *   /panel          el jugador: sus partidos, equipos, inscripciones y cuentas
 *   /organizacion   la organización: torneos, inscripciones, disputas y usuarios
 *
 * Las rutas con sesión pasan por RutaProtegida. Eso es comodidad de navegación:
 * la autorización de verdad la hace el backend en cada petición.
 */
const ROLES_DE_ORGANIZACION = ['admin', 'empleado']

function AlCambiarDePagina() {
  const { pathname } = useLocation()

  // Al navegar, la página nueva empieza arriba y no a media altura.
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' })
  }, [pathname])

  return null
}

export default function App() {
  return (
    <>
      <AlCambiarDePagina />

      <Routes>
        <Route path="/ingreso" element={<Ingreso />} />
        <Route path="/registro" element={<Registro />} />

        <Route element={<Sitio />}>
          <Route index element={<Inicio />} />
          <Route path="juegos/:juego" element={<Juego />} />
          <Route path="torneos" element={<Torneos />} />
          <Route path="torneos/:slug" element={<Torneo />} />
          <Route path="partidos/:id" element={<Partido />} />
          <Route path="equipos/:slug" element={<Equipo />} />
          <Route path="jugadores/:apodo" element={<Jugador />} />

          <Route element={<RutaProtegida />}>
            <Route path="panel" element={<ResumenDelPanel />} />
            <Route path="panel/equipos" element={<MisEquipos />} />
            <Route path="panel/equipos/:slug" element={<EquipoDelPanel />} />
            <Route path="panel/inscripciones" element={<Inscripciones />} />
            <Route path="panel/cuentas" element={<Cuentas />} />
            <Route path="panel/pagos/:referencia" element={<Pago />} />
          </Route>

          <Route element={<RutaProtegida rolesPermitidos={ROLES_DE_ORGANIZACION} />}>
            <Route path="organizacion" element={<ResumenOrganizacion />} />
            <Route path="organizacion/torneos" element={<TorneosAdmin />} />
            <Route path="organizacion/torneos/:slug" element={<TorneoAdmin />} />
            <Route path="organizacion/inscripciones" element={<InscripcionesAdmin />} />
            <Route path="organizacion/disputas" element={<DisputasAdmin />} />
            <Route path="organizacion/usuarios" element={<UsuariosAdmin />} />
          </Route>

          <Route path="*" element={<NoEncontrada />} />
        </Route>
      </Routes>
    </>
  )
}

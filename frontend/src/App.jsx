import { useEffect } from 'react'
import { Route, Routes, useLocation } from 'react-router-dom'
import { Sitio } from './componentes/diseno/Sitio'
import { RutaProtegida } from './componentes/diseno/RutaProtegida'

import Inicio from './paginas/publico/Inicio'
import Torneos from './paginas/publico/Torneos'
import Torneo from './paginas/publico/Torneo'
import Equipo from './paginas/publico/Equipo'
import Partido from './paginas/publico/Partido'
import NoEncontrada from './paginas/publico/NoEncontrada'

import Ingreso from './paginas/acceso/Ingreso'
import Registro from './paginas/acceso/Registro'

import MisEquipos from './paginas/panel/MisEquipos'
import EquipoDelDueno from './paginas/panel/EquipoDelDueno'
import Inscripciones from './paginas/panel/Inscripciones'
import Pago from './paginas/panel/Pago'

import ResumenOrganizacion from './paginas/admin/ResumenOrganizacion'
import TorneosAdmin from './paginas/admin/TorneosAdmin'
import TorneoAdmin from './paginas/admin/TorneoAdmin'
import InscripcionesAdmin from './paginas/admin/InscripcionesAdmin'
import UsuariosAdmin from './paginas/admin/UsuariosAdmin'

/**
 * Mapa de la aplicación.
 *
 *   /               portada, torneos, equipos y partidos: abierto a cualquiera
 *   /ingreso        entrada y registro, a pantalla completa
 *   /panel          el dueño gestiona sus equipos, plantillas, inscripciones y pagos
 *   /organizacion   la organización gestiona torneos, calendario y resultados
 *
 * Las rutas con sesión pasan por RutaProtegida. Eso es comodidad de navegación:
 * quien la saltee desde el navegador solo verá pantallas vacías, porque la
 * autorización de verdad la hace el backend en cada petición.
 */
const ROLES_CON_EQUIPOS = ['dueno', 'admin', 'empleado']
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
          <Route path="torneos" element={<Torneos />} />
          <Route path="torneos/:slug" element={<Torneo />} />
          <Route path="equipos/:slug" element={<Equipo />} />
          <Route path="partidos/:id" element={<Partido />} />

          <Route element={<RutaProtegida rolesPermitidos={ROLES_CON_EQUIPOS} />}>
            <Route path="panel" element={<MisEquipos />} />
            <Route path="panel/equipos/:slug" element={<EquipoDelDueno />} />
            <Route path="panel/inscripciones" element={<Inscripciones />} />
            <Route path="panel/pagos/:referencia" element={<Pago />} />
          </Route>

          <Route element={<RutaProtegida rolesPermitidos={ROLES_DE_ORGANIZACION} />}>
            <Route path="organizacion" element={<ResumenOrganizacion />} />
            <Route path="organizacion/torneos" element={<TorneosAdmin />} />
            <Route path="organizacion/torneos/:slug" element={<TorneoAdmin />} />
            <Route path="organizacion/inscripciones" element={<InscripcionesAdmin />} />
            <Route path="organizacion/usuarios" element={<UsuariosAdmin />} />
          </Route>

          <Route path="*" element={<NoEncontrada />} />
        </Route>
      </Routes>
    </>
  )
}

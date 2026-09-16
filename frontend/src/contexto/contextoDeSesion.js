import { createContext, useContext } from 'react'

/**
 * Sesión de la persona que está usando la aplicación.
 *
 * Vive en un contexto porque media aplicación necesita saber quién entró: la
 * cabecera para el menú, las rutas protegidas para dejar pasar, y cada pantalla
 * para saber si algo es suyo.
 */
export const ContextoDeSesion = createContext(null)

/**
 * El nombre empieza por «use» porque React exige ese prefijo para reconocer un
 * hook y poder verificar sus reglas. Es la única concesión al inglés en los
 * nombres del proyecto, y es obligatoria.
 */
export function useSesion() {
  const valor = useContext(ContextoDeSesion)

  if (valor === null) {
    throw new Error('usarSesion() debe usarse dentro de <ProveedorDeSesion>.')
  }

  return valor
}

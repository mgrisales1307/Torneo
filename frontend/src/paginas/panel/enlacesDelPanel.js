/**
 * Enlaces de los dos paneles. Viven aparte del componente para que el archivo
 * de la navegación exporte solo componentes (lo que pide el recargado en
 * caliente de Vite).
 */

export const ENLACES_DEL_JUGADOR = [
  { a: '/panel', texto: 'Pendientes', exacto: true },
  { a: '/panel/equipos', texto: 'Equipos' },
  { a: '/panel/inscripciones', texto: 'Inscripciones' },
  { a: '/panel/cuentas', texto: 'Cuentas de juego' },
  { a: '/panel/cartera', texto: 'Cartera' },
]

export const ENLACES_DE_ORGANIZACION = [
  { a: '/organizacion', texto: 'Resumen', exacto: true },
  { a: '/organizacion/torneos', texto: 'Torneos' },
  { a: '/organizacion/inscripciones', texto: 'Inscripciones' },
  { a: '/organizacion/disputas', texto: 'Disputas' },
  { a: '/organizacion/usuarios', texto: 'Usuarios' },
]

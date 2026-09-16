/**
 * Enlaces de los dos paneles. Viven aparte del componente para que el archivo
 * de la navegación exporte solo componentes (lo que pide el recargado en
 * caliente de Vite).
 */

export const ENLACES_DEL_DUENO = [
  { a: '/panel', texto: 'Mis equipos', exacto: true },
  { a: '/panel/inscripciones', texto: 'Inscripciones y pagos' },
]

export const ENLACES_DE_ORGANIZACION = [
  { a: '/organizacion', texto: 'Resumen', exacto: true },
  { a: '/organizacion/torneos', texto: 'Torneos' },
  { a: '/organizacion/inscripciones', texto: 'Inscripciones' },
  { a: '/organizacion/usuarios', texto: 'Usuarios' },
]

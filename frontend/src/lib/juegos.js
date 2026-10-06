/*
 * Lo visual de cada juego: su color y su portada. Las reglas (titulares,
 * cuenta, formatos) llegan del backend; aquí solo vive lo que es de pintura.
 *
 * Las portadas se toman de src/assets/juegos/ por nombre de archivo
 * (valorant, lol, dota2, pubg, freefire, con cualquier extensión de imagen).
 * Si falta alguna, la tarjeta se pinta con el color del juego y su sigla.
 */

const PORTADAS = import.meta.glob('../assets/juegos/*.{png,jpg,jpeg,webp,avif}', {
  eager: true,
  import: 'default',
})

function portadaDe(valor) {
  const ruta = Object.keys(PORTADAS).find((archivo) => archivo.split('/').pop().split('.')[0] === valor)

  return ruta ? PORTADAS[ruta] : null
}

/*
 * `sala` es cuántos equipos caben en una partida: dos en los duelos y la sala
 * personalizada en battle royale. El backend lo vuelve a validar.
 */
export const JUEGOS = [
  { valor: 'valorant', nombre: 'Valorant', abreviatura: 'VAL', color: 'var(--juego-valorant)', sala: 2 },
  { valor: 'lol', nombre: 'League of Legends', abreviatura: 'LOL', color: 'var(--juego-lol)', sala: 2 },
  { valor: 'dota2', nombre: 'Dota 2', abreviatura: 'DOTA', color: 'var(--juego-dota2)', sala: 2 },
  { valor: 'pubg', nombre: 'PUBG: Battlegrounds', abreviatura: 'PUBG', color: 'var(--juego-pubg)', sala: 16 },
  { valor: 'freefire', nombre: 'Free Fire', abreviatura: 'FF', color: 'var(--juego-freefire)', sala: 12 },
].map((juego) => ({ ...juego, esBattleRoyale: juego.sala > 2, portada: portadaDe(juego.valor) }))

export function juegoDe(valor) {
  return JUEGOS.find((juego) => juego.valor === valor) ?? JUEGOS[0]
}

/**
 * Variable CSS con el color del juego, para colgarla de un `style`.
 */
export function colorDeJuego(valor) {
  return { '--color-juego': juegoDe(valor).color }
}

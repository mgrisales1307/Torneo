import { describe, expect, it } from 'vitest'
import { colorDeJuego, JUEGOS, juegoDe } from '../lib/juegos'

describe('catálogo de juegos del frontend', () => {
  it('tiene los cinco juegos de la plataforma, en el mismo orden que el backend', () => {
    expect(JUEGOS.map((juego) => juego.valor)).toEqual(['valorant', 'lol', 'dota2', 'pubg', 'freefire'])
  })

  it('cada juego trae nombre, sigla y color', () => {
    for (const juego of JUEGOS) {
      expect(juego.nombre).toBeTruthy()
      expect(juego.abreviatura).toBeTruthy()
      expect(juego.color).toMatch(/^var\(--juego-/)
    }
  })

  it('el tamaño de sala coincide con el del backend', () => {
    const sala = Object.fromEntries(JUEGOS.map((juego) => [juego.valor, juego.sala]))

    expect(sala).toEqual({ valorant: 2, lol: 2, dota2: 2, pubg: 16, freefire: 12 })
  })

  it('solo PUBG y Free Fire son battle royale', () => {
    const battleRoyale = JUEGOS.filter((juego) => juego.esBattleRoyale).map((juego) => juego.valor)

    expect(battleRoyale).toEqual(['pubg', 'freefire'])
  })
})

describe('juegoDe', () => {
  it('encuentra un juego por su valor', () => {
    expect(juegoDe('dota2').nombre).toBe('Dota 2')
  })

  it('con un valor desconocido devuelve el primero en vez de romper', () => {
    expect(juegoDe('fortnite').valor).toBe('valorant')
    expect(juegoDe(undefined).valor).toBe('valorant')
  })
})

describe('colorDeJuego', () => {
  it('devuelve la variable CSS que cuelga del style', () => {
    expect(colorDeJuego('pubg')).toEqual({ '--color-juego': 'var(--juego-pubg)' })
  })
})

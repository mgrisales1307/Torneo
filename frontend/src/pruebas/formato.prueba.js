import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  aCentavos,
  aPesos,
  fechaCompacta,
  fechaCorta,
  fechaLarga,
  fechaYHora,
  hora,
  iniciales,
  nombreDeRonda,
  paraInputDeFecha,
  pluralizar,
  relativo,
} from '../lib/formato'

describe('fechas inválidas o ausentes', () => {
  it('no rompen la pantalla: devuelven un guion o un texto de reserva', () => {
    expect(fechaLarga(null)).toBe('—')
    expect(fechaLarga(undefined)).toBe('—')
    expect(fechaCorta('')).toBe('—')
    expect(fechaCorta('esto no es una fecha')).toBe('—')
    expect(hora('basura')).toBe('—')
    expect(fechaCompacta(null)).toBe('Por definir')
    expect(fechaYHora(null)).toBe('Por definir')
    expect(paraInputDeFecha(null)).toBe('')
    expect(relativo(null)).toBe('')
  })
})

describe('fechas válidas', () => {
  it('la fecha corta lleva el año', () => {
    expect(fechaCorta('2030-05-10T12:00:00')).toContain('2030')
  })

  it('la fecha larga no devuelve el guion', () => {
    expect(fechaLarga('2030-05-10T12:00:00')).not.toBe('—')
  })

  it('fecha y hora junta las dos partes con un punto medio', () => {
    expect(fechaYHora('2030-05-10T15:30:00')).toContain('·')
  })

  it('paraInputDeFecha devuelve la hora local sin zona, como pide datetime-local', () => {
    expect(paraInputDeFecha('2030-05-10T15:30:00')).toBe('2030-05-10T15:30')
  })
})

describe('fechaCompacta', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-11-05T12:00:00'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('omite el año cuando es el actual', () => {
    expect(fechaCompacta('2026-12-01T19:00:00')).not.toContain('2026')
  })

  it('muestra el año cuando es otro', () => {
    expect(fechaCompacta('2027-01-02T19:00:00')).toContain('2027')
  })
})

describe('relativo', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-11-05T12:00:00'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('habla en minutos, horas y días según la distancia', () => {
    expect(relativo(new Date('2026-11-05T12:10:00'))).toMatch(/10/)
    expect(relativo(new Date('2026-11-05T14:00:00'))).toMatch(/2/)
    expect(relativo(new Date('2026-11-08T12:00:00'))).toMatch(/3/)
  })

  it('distingue el pasado del futuro', () => {
    const futuro = relativo(new Date('2026-11-05T12:30:00'))
    const pasado = relativo(new Date('2026-11-05T11:30:00'))

    expect(futuro).not.toBe(pasado)
  })
})

describe('iniciales', () => {
  it('toma la primera letra de cada palabra', () => {
    expect(iniciales('Los Cóndores')).toBe('LC')
    expect(iniciales('team_alpha-one')).toBe('TA')
  })

  it('con una sola palabra toma las dos primeras letras', () => {
    expect(iniciales('valorant')).toBe('VA')
  })

  it('sin nombre devuelve la sigla de la marca', () => {
    expect(iniciales('')).toBe('NZ')
    expect(iniciales()).toBe('NZ')
  })
})

describe('nombreDeRonda', () => {
  it('nombra las últimas rondas del cuadro', () => {
    expect(nombreDeRonda(3, 3)).toBe('Final')
    expect(nombreDeRonda(2, 3)).toBe('Semifinal')
    expect(nombreDeRonda(1, 3)).toBe('Cuartos')
  })

  it('numera las rondas anteriores', () => {
    expect(nombreDeRonda(1, 5)).toBe('Ronda 1')
    expect(nombreDeRonda(2, 5)).toBe('Ronda 2')
  })

  it('distingue la llave inferior y la gran final', () => {
    expect(nombreDeRonda(2, 4, 'inferior')).toBe('Inferior · ronda 2')
    expect(nombreDeRonda(1, 1, 'gran_final')).toBe('Gran final')
  })
})

describe('dinero en los formularios', () => {
  it('convierte pesos a centavos sin errores de coma flotante', () => {
    expect(aCentavos('150.5')).toBe(15050)
    expect(aCentavos(0.29)).toBe(29)
    expect(aCentavos(0.1 + 0.2)).toBe(30)
    expect(aCentavos(50000)).toBe(5_000_000)
  })

  it('un valor vacío o nulo son cero centavos', () => {
    expect(aCentavos('')).toBe(0)
    expect(aCentavos(null)).toBe(0)
    expect(aCentavos(undefined)).toBe(0)
  })

  it('convierte centavos a pesos para mostrarlos en el campo', () => {
    expect(aPesos(15050)).toBe('150.5')
    expect(aPesos(5_000_000)).toBe('50000')
    expect(aPesos(null)).toBe('0')
  })

  it('ir y volver no cambia el valor', () => {
    expect(aCentavos(aPesos(123456))).toBe(123456)
  })
})

describe('pluralizar', () => {
  it('usa el singular solo con uno', () => {
    expect(pluralizar(1, 'equipo', 'equipos')).toBe('1 equipo')
    expect(pluralizar(0, 'equipo', 'equipos')).toBe('0 equipos')
    expect(pluralizar(8, 'equipo', 'equipos')).toBe('8 equipos')
  })
})

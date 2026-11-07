import { AxiosError } from 'axios'
import { describe, expect, it } from 'vitest'
import { erroresPorCampo, esNoAutenticado, esNoEncontrado, esProhibido, mensajeDeError } from '../lib/api'

/**
 * Un error de axios como el que llega cuando la API responde con ese estado.
 */
function errorHttp(status, data = {}) {
  return new AxiosError('fallo', 'ERR_BAD_REQUEST', undefined, undefined, { status, data })
}

describe('clasificación de errores', () => {
  it('reconoce 401, 403 y 404', () => {
    expect(esNoAutenticado(errorHttp(401))).toBe(true)
    expect(esProhibido(errorHttp(403))).toBe(true)
    expect(esNoEncontrado(errorHttp(404))).toBe(true)
  })

  it('no confunde unos con otros', () => {
    expect(esNoAutenticado(errorHttp(403))).toBe(false)
    expect(esProhibido(errorHttp(404))).toBe(false)
    expect(esNoEncontrado(errorHttp(401))).toBe(false)
  })

  it('un error que no es de axios nunca cuenta', () => {
    expect(esNoAutenticado(new Error('x'))).toBe(false)
    expect(esProhibido(null)).toBe(false)
    expect(esNoEncontrado(undefined)).toBe(false)
  })
})

describe('erroresPorCampo', () => {
  it('deja el primer mensaje de cada campo', () => {
    const error = errorHttp(422, {
      errors: {
        correo: ['El correo ya está en uso.', 'Otro mensaje'],
        clave: 'La contraseña es muy corta.',
      },
    })

    expect(erroresPorCampo(error)).toEqual({
      correo: 'El correo ya está en uso.',
      clave: 'La contraseña es muy corta.',
    })
  })

  it('sin errores de validación devuelve un objeto vacío', () => {
    expect(erroresPorCampo(errorHttp(500))).toEqual({})
    expect(erroresPorCampo(new Error('x'))).toEqual({})
  })
})

describe('mensajeDeError', () => {
  it('si no hubo respuesta, explica que no se pudo conectar', () => {
    const sinRespuesta = new AxiosError('Network Error', 'ERR_NETWORK')

    expect(mensajeDeError(sinRespuesta)).toMatch(/No pudimos conectar con el servidor/)
  })

  it('429 pide esperar', () => {
    expect(mensajeDeError(errorHttp(429))).toMatch(/Demasiados intentos/)
  })

  it('419 avisa que la sesión caducó', () => {
    expect(mensajeDeError(errorHttp(419))).toMatch(/sesión caducó/)
  })

  it('403 usa el mensaje del servidor o uno por defecto', () => {
    expect(mensajeDeError(errorHttp(403, { message: 'Solo para la organización.' }))).toBe('Solo para la organización.')
    expect(mensajeDeError(errorHttp(403))).toBe('No tienes permiso para hacer esto.')
  })

  it('422 muestra el primer error de campo', () => {
    const error = errorHttp(422, { message: 'Datos inválidos.', errors: { apodo: ['Ese apodo ya está en uso.'] } })

    expect(mensajeDeError(error)).toBe('Ese apodo ya está en uso.')
  })

  it('sin errores de campo usa el mensaje general y luego el de reserva', () => {
    expect(mensajeDeError(errorHttp(422, { message: 'No tienes saldo suficiente.' }))).toBe('No tienes saldo suficiente.')
    expect(mensajeDeError(errorHttp(500), 'Algo salió mal.')).toBe('Algo salió mal.')
  })

  it('un error que no es de axios devuelve el mensaje de reserva', () => {
    expect(mensajeDeError(new Error('x'), 'Reserva')).toBe('Reserva')
    expect(mensajeDeError(new Error('x'))).toMatch(/No pudimos completar la acción/)
  })
})

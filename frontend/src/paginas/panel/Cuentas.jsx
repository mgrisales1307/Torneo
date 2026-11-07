import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { guardarCuenta, listarJuegos, listarMisCuentas, mensajeDeError, quitarCuenta } from '../../lib/api'
import { colorDeJuego } from '../../lib/juegos'
import { Aviso, Boton, Cargando } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR } from './enlacesDelPanel'

/**
 * La cuenta del jugador en cada juego. Sin ella, sus equipos no se pueden
 * inscribir en los torneos de ese juego.
 *
 * La vinculación es simulada: no se verifica con el juego, y vale cualquier
 * texto de entre 3 y 60 caracteres.
 */
export default function Cuentas() {
  const juegos = useQuery({ queryKey: ['juegos'], queryFn: listarJuegos, staleTime: Infinity })
  const cuentas = useQuery({ queryKey: ['mis-cuentas'], queryFn: listarMisCuentas })

  return (
    <div>
      <header className="encabezado-pagina">
        <h1 className="titulo-pagina">Cuentas de juego</h1>
      </header>

      <NavegacionDelPanel enlaces={ENLACES_DEL_JUGADOR} />

      <Aviso tono="info">
        Vinculación simulada: tu cuenta no se verifica con el juego. Escribe el nombre o ID que quieras (mínimo 3
        caracteres) para poder inscribirte.
      </Aviso>

      {juegos.isPending || cuentas.isPending ? (
        <Cargando />
      ) : juegos.isError || cuentas.isError ? (
        <Aviso tono="error">{mensajeDeError(juegos.error ?? cuentas.error)}</Aviso>
      ) : (
        <div className="pila pila--chica">
          {juegos.data.map((juego) => (
            <FilaDeCuenta
              key={juego.valor}
              juego={juego}
              cuenta={cuentas.data.find((cuenta) => cuenta.juego === juego.valor)}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function FilaDeCuenta({ juego, cuenta }) {
  const cliente = useQueryClient()
  const [valor, setValor] = useState(cuenta?.identificador ?? '')
  const [aviso, setAviso] = useState(null)

  const refrescar = (respuesta) => {
    cliente.invalidateQueries({ queryKey: ['mis-cuentas'] })
    setAviso({ tono: 'exito', texto: respuesta.message })
  }

  const guardar = useMutation({
    mutationFn: () => guardarCuenta(juego.valor, valor.trim()),
    onSuccess: refrescar,
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  const quitar = useMutation({
    mutationFn: () => quitarCuenta(juego.valor),
    onSuccess: (respuesta) => {
      setValor('')
      refrescar(respuesta)
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  return (
    <form
      className="cuenta-juego"
      style={colorDeJuego(juego.valor)}
      onSubmit={(evento) => {
        evento.preventDefault()
        guardar.mutate()
      }}
    >
      <div className="cuenta-juego__nombre">
        <strong>{juego.nombre}</strong>
        <span>{juego.cuenta.nombre}</span>
      </div>

      <div className="campo">
        <input
          className="campo__control"
          aria-label={`${juego.cuenta.nombre} en ${juego.nombre}`}
          placeholder={juego.cuenta.ejemplo}
          value={valor}
          onChange={(evento) => {
            setValor(evento.target.value)
            setAviso(null)
          }}
        />
        {aviso && <span className={aviso.tono === 'error' ? 'campo__error' : 'campo__pista'}>{aviso.texto}</span>}
      </div>

      <div className="fila">
        <Boton type="submit" tamano="chico" disabled={!valor.trim() || valor.trim() === cuenta?.identificador} cargando={guardar.isPending}>
          Guardar
        </Boton>
        {cuenta && (
          <Boton type="button" variante="fantasma" tamano="chico" cargando={quitar.isPending} onClick={() => quitar.mutate()}>
            Quitar
          </Boton>
        )}
      </div>
    </form>
  )
}

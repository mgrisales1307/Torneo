import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Dialogo } from '../componentes/ui/Dialogo'
import { Campo } from '../componentes/ui/Basicos'

/*
 * Un modal con un formulario dentro, montado igual que los de verdad: el
 * padre guarda lo que se escribe en su propio estado y le pasa a Dialogo una
 * función de cierre declarada en el cuerpo del componente —es decir, una
 * función nueva en cada render—. Así es como lo usan MisEquipos,
 * PlantillaDelEquipo, TorneosAdmin e InscripcionesAdmin.
 */
function ModalDePrueba() {
  const [abierto, setAbierto] = useState(true)
  const [nombre, setNombre] = useState('')

  function cerrar() {
    setAbierto(false)
  }

  return (
    <Dialogo abierto={abierto} titulo="Nuevo equipo" alCerrar={cerrar}>
      <Campo
        etiqueta="Nombre del equipo"
        name="nombre"
        value={nombre}
        onChange={(evento) => setNombre(evento.target.value)}
      />
    </Dialogo>
  )
}

describe('Dialogo', () => {
  it('deja escribir seguido en un campo sin perder el foco', async () => {
    const usuario = userEvent.setup()

    render(<ModalDePrueba />)

    const campo = screen.getByLabelText('Nombre del equipo')

    await usuario.click(campo)
    await usuario.keyboard('Atlético Parceros')

    // Si el modal le roba el foco tras la primera pulsación, solo llega la
    // primera letra y el resto del texto se pierde.
    expect(campo).toHaveValue('Atlético Parceros')
    expect(campo).toHaveFocus()
  })

  it('mueve el foco dentro del modal al abrirlo', async () => {
    render(<ModalDePrueba />)

    expect(screen.getByRole('dialog')).toHaveFocus()
  })
})

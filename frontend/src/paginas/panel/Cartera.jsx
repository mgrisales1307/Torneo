import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { obtenerCartera, retirarDeCartera, mensajeDeError } from '../../lib/api'
import { aCentavos, fechaYHora } from '../../lib/formato'
import { Aviso, Boton, Campo, Cargando, EstadoVacio, Tarjeta } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from './NavegacionDelPanel'
import { ENLACES_DEL_JUGADOR } from './enlacesDelPanel'

const TIPOS = { premio: 'Premio', ajuste_premio: 'Corrección de premio', inscripcion: 'Inscripción', devolucion: 'Devolución', retiro: 'Retiro simulado' }
const pesos = (centavos) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(centavos / 100)

export default function Cartera() {
  const cliente = useQueryClient()
  const [pagina, setPagina] = useState(1)
  const [monto, setMonto] = useState('')
  const [referencia, setReferencia] = useState(() => crypto.randomUUID())
  const [aviso, setAviso] = useState(null)
  const cartera = useQuery({ queryKey: ['cartera', pagina], queryFn: () => obtenerCartera(pagina), refetchInterval: 10000 })
  const retiro = useMutation({
    mutationFn: () => retirarDeCartera(aCentavos(monto), referencia),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['cartera'] })
      setMonto('')
      setReferencia(crypto.randomUUID())
      setAviso({ tono: 'exito', texto: respuesta.message })
    },
    onError: (error) => setAviso({ tono: 'error', texto: mensajeDeError(error) }),
  })
  if (cartera.isPending) return <Cargando />
  if (cartera.isError) return <Aviso tono="error">{mensajeDeError(cartera.error)}</Aviso>
  const datos = cartera.data
  return (
    <div className="pila pila--media">
      <header className="encabezado-pagina"><p className="rotulo">Tus premios</p><h1 className="titulo-pagina">Cartera</h1><p>Recibe premios automáticamente, paga inscripciones y simula retiros.</p></header>
      <NavegacionDelPanel enlaces={ENLACES_DEL_JUGADOR} />
      <Aviso tono="info">Todo el saldo es virtual. Los pagos y retiros de esta demostración no mueven dinero real.</Aviso>
      {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}
      <div className="dos-columnas">
        <Tarjeta titulo="Saldo disponible"><p className="saldo-cartera cifra">{datos.saldo_formateado}</p><Link className="boton boton--contorno" to="/torneos">Usar saldo en un torneo</Link><p className="apagado">En torneos por equipos, el premio completo se acredita a quien realizó la inscripción.</p></Tarjeta>
        <Tarjeta titulo="Retirar saldo">
          <form className="formulario" onSubmit={(evento) => { evento.preventDefault(); if (window.confirm(`¿Simular un retiro de ${pesos(aCentavos(monto))}?`)) retiro.mutate() }}>
            <Campo etiqueta="Monto en pesos" name="retiro" type="number" min={1} step={1} max={datos.saldo_centavos / 100} required value={monto} onChange={(evento) => setMonto(evento.target.value)} pista="El retiro se registra al instante y descuenta el saldo virtual." />
            <Boton type="submit" disabled={!monto || aCentavos(monto) <= 0 || aCentavos(monto) > datos.saldo_centavos} cargando={retiro.isPending}>Simular retiro</Boton>
          </form>
        </Tarjeta>
      </div>
      <Tarjeta titulo="Movimientos">
        {datos.movimientos.data.length === 0 ? <EstadoVacio titulo="Aún no tienes movimientos" descripcion="Cuando ganes un premio aparecerá aquí." /> : <>
          <div className="tabla-envoltura"><table className="tabla"><thead><tr><th>Fecha</th><th>Movimiento</th><th className="numero">Monto</th><th className="numero">Saldo</th></tr></thead><tbody>{datos.movimientos.data.map((movimiento) => <tr key={movimiento.id}><td>{fechaYHora(movimiento.created_at)}</td><td><strong>{TIPOS[movimiento.tipo] ?? movimiento.tipo}</strong><p className="apagado">{movimiento.descripcion}</p></td><td className={`numero ${movimiento.monto_centavos > 0 ? 'movimiento-positivo' : ''}`}>{movimiento.monto_centavos > 0 ? '+' : ''}{pesos(movimiento.monto_centavos)}</td><td className="numero">{pesos(movimiento.saldo_centavos)}</td></tr>)}</tbody></table></div>
          <div className="fila" style={{ marginTop: '1rem' }}><Boton variante="contorno" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>Anterior</Boton><span>Página {pagina} de {datos.movimientos.last_page}</span><Boton variante="contorno" disabled={pagina >= datos.movimientos.last_page} onClick={() => setPagina(pagina + 1)}>Siguiente</Boton></div>
        </>}
      </Tarjeta>
    </div>
  )
}

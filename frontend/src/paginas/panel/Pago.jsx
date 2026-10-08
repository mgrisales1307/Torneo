import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CreditCard, ShieldCheck } from 'lucide-react'
import { mensajeDeError, obtenerOrden, pagarConLaPasarelaSimulada, obtenerCartera, pagarConCartera } from '../../lib/api'
import { fechaYHora } from '../../lib/formato'
import { Aviso, Boton, Cargando, Insignia, Tarjeta } from '../../componentes/ui/Basicos'

/**
 * Pantalla de pago.
 *
 * Con la pasarela de desarrollo, los dos botones le piden al servidor que
 * simule un pago aprobado o uno rechazado. El navegador nunca decide el monto
 * ni firma nada: solo dice qué caso quiere probar. Con una pasarela real, este
 * componente se cambia por la redirección al formulario del proveedor.
 */
export default function Pago() {
  const { referencia } = useParams()
  const cliente = useQueryClient()
  const [aviso, setAviso] = useState(null)

  const orden = useQuery({ queryKey: ['orden', referencia], queryFn: () => obtenerOrden(referencia) })
  const cartera = useQuery({ queryKey: ['cartera', 1], queryFn: () => obtenerCartera(1) })

  const pagar = useMutation({
    mutationFn: (resultado) => pagarConLaPasarelaSimulada(referencia, resultado),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries()
      setAviso({ tono: respuesta.data.estado.valor === 'pagada' ? 'exito' : 'atencion', texto: respuesta.message })
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })
  const usarSaldo = useMutation({
    mutationFn: () => pagarConCartera(orden.data.inscripcion.id),
    onSuccess: (respuesta) => { cliente.invalidateQueries(); setAviso({ tono: 'exito', texto: respuesta.message }) },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  if (orden.isPending) return <Cargando />
  if (orden.isError) return <Aviso tono="error">{mensajeDeError(orden.error)}</Aviso>

  const datos = orden.data
  const inscripcion = datos.inscripcion

  return (
    <div className="pila pila--chica" style={{ maxWidth: '36rem', marginInline: 'auto' }}>
      <header className="encabezado-pagina">
        <p className="rotulo">Orden {datos.referencia}</p>
        <h1 className="titulo-pagina">Pago</h1>
        <Insignia estado={datos.estado} />
      </header>

      {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}

      <Tarjeta className="seccion">
        <div className="lista-detalle">
          <div>
            <span>Torneo</span>
            <strong>{inscripcion?.torneo?.nombre}</strong>
          </div>
          <div>
            <span>{inscripcion?.torneo?.modalidad === 'individual' ? 'Jugador' : 'Equipo'}</span>
            <strong>{inscripcion?.equipo?.nombre}</strong>
          </div>
          <div>
            <span>Valor</span>
            <strong style={{ fontSize: '1.15rem' }}>{datos.monto_formateado}</strong>
          </div>
          <div>
            <span>La orden vence</span>
            <strong>{fechaYHora(datos.expira_en)}</strong>
          </div>
        </div>

        {datos.estado.valor === 'pagada' ? (
          <div style={{ marginTop: '1.5rem', display: 'grid', gap: '1rem', justifyItems: 'start' }}>
            <Aviso tono="exito">
              {inscripcion?.torneo.flujo_automatico ? 'Cupo confirmado. Al completar el torneo comienza la cuenta regresiva.' : 'Pago confirmado. Tu inscripción quedó en revisión de la organización.'}
            </Aviso>
            <Link className="boton boton--solido" to="/panel/inscripciones">
              Volver a mis inscripciones
            </Link>
          </div>
        ) : datos.admite_pago ? (
          <>
            <p
              style={{
                display: 'flex',
                gap: '.5rem',
                alignItems: 'center',
                marginTop: '1.5rem',
                color: 'var(--apagado)',
                fontSize: '.78rem',
              }}
            >
              <ShieldCheck size={15} aria-hidden="true" />
              Entorno de pruebas: ningún cobro real se realiza.
            </p>

            <div className="fila" style={{ marginTop: '1rem' }}>
              <Boton
                iconoIzquierda={<CreditCard size={16} />}
                cargando={pagar.isPending}
                disabled={usarSaldo.isPending}
                onClick={() => pagar.mutate('aprobado')}
              >
                Confirmar pago simulado · {datos.monto_formateado}
              </Boton>
              <Boton variante="contorno" disabled={pagar.isPending || usarSaldo.isPending} onClick={() => pagar.mutate('rechazado')}>
                Simular pago rechazado
              </Boton>
            </div>
            <div className="pila pila--chica" style={{ marginTop: '1.5rem' }}>
              {cartera.isError ? <Aviso tono="error">{mensajeDeError(cartera.error)}</Aviso> : <p>Tu cartera: {cartera.data?.saldo_formateado ?? 'Cargando saldo…'}</p>}
              <Boton variante="contorno" disabled={pagar.isPending || !cartera.data || cartera.data.saldo_centavos < datos.monto_centavos} cargando={usarSaldo.isPending} onClick={() => usarSaldo.mutate()}>Pagar con saldo virtual</Boton>
              <Link to="/panel/cartera">Ver cartera y movimientos</Link>
            </div>
          </>
        ) : (
          <div style={{ marginTop: '1.5rem', display: 'grid', gap: '1rem', justifyItems: 'start' }}>
            <Aviso tono="atencion">
              Esta orden ya no admite pagos. Genera una nueva desde tus inscripciones.
            </Aviso>
            <Link className="boton boton--contorno" to="/panel/inscripciones">
              Ir a mis inscripciones
            </Link>
          </div>
        )}
      </Tarjeta>
    </div>
  )
}

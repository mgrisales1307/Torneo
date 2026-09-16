import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CreditCard, ShieldCheck } from 'lucide-react'
import { mensajeDeError, obtenerOrden, pagarConLaPasarelaSimulada } from '../../lib/api'
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

  const pagar = useMutation({
    mutationFn: (resultado) => pagarConLaPasarelaSimulada(referencia, resultado),
    onSuccess: (respuesta) => {
      cliente.invalidateQueries({ queryKey: ['orden', referencia] })
      cliente.invalidateQueries({ queryKey: ['mis-inscripciones'] })
      setAviso({ tono: respuesta.data.estado.valor === 'pagada' ? 'exito' : 'atencion', texto: respuesta.message })
    },
    onError: (fallo) => setAviso({ tono: 'error', texto: mensajeDeError(fallo) }),
  })

  if (orden.isPending) return <Cargando />
  if (orden.isError) return <Aviso tono="error">{mensajeDeError(orden.error)}</Aviso>

  const datos = orden.data
  const inscripcion = datos.inscripcion

  return (
    <div style={{ maxWidth: '36rem', marginInline: 'auto' }}>
      <header className="panel-cabecera">
        <div>
          <h1>Pago de inscripción</h1>
          <p>Orden {datos.referencia}</p>
        </div>
        <Insignia estado={datos.estado} />
      </header>

      {aviso && <Aviso tono={aviso.tono}>{aviso.texto}</Aviso>}

      <Tarjeta className="seccion">
        <div className="detalle-lista">
          <div>
            <span>Torneo</span>
            <strong>{inscripcion?.torneo?.nombre}</strong>
          </div>
          <div>
            <span>Equipo</span>
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
              Pago confirmado. Tu inscripción quedó en revisión de la organización.
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

            <div className="barra-acciones" style={{ justifyContent: 'flex-start', marginTop: '1rem' }}>
              <Boton
                iconoIzquierda={<CreditCard size={16} />}
                cargando={pagar.isPending}
                onClick={() => pagar.mutate('aprobado')}
              >
                Pagar {datos.monto_formateado}
              </Boton>
              <Boton variante="contorno" disabled={pagar.isPending} onClick={() => pagar.mutate('rechazado')}>
                Simular pago rechazado
              </Boton>
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

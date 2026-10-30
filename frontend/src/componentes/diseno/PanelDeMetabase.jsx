import { useEffect, useState } from 'react'
import { Aviso, Boton, Cargando } from '../ui/Basicos'

let cargaDelScript
let origenDelScript

function cargarMetabase(url) {
  if (cargaDelScript && origenDelScript === url) return cargaDelScript
  origenDelScript = url
  window.metabaseConfig = { isGuest: true, instanceUrl: url, locale: 'es', theme: { preset: 'dark' } }
  cargaDelScript = new Promise((resolver, rechazar) => {
    const script = document.createElement('script')
    script.src = `${url}/app/embed.js`
    script.async = true
    const tiempo = setTimeout(() => { script.remove(); rechazar(new Error('Tiempo de espera agotado')) }, 20000)
    script.onload = () => { clearTimeout(tiempo); resolver() }
    script.onerror = () => { clearTimeout(tiempo); script.remove(); rechazar(new Error('Metabase no está disponible')) }
    document.head.appendChild(script)
  }).catch((error) => { cargaDelScript = undefined; throw error })
  return cargaDelScript
}

export function PanelDeMetabase({ url, jwt }) {
  const [estado, cambiarEstado] = useState('cargando')
  const [intento, cambiarIntento] = useState(0)

  useEffect(() => {
    let vigente = true
    cargarMetabase(url).then(() => { if (vigente) cambiarEstado('listo') }).catch(() => { if (vigente) cambiarEstado('error') })
    return () => { vigente = false }
  }, [url, intento])

  if (estado === 'cargando') return <Cargando />
  if (estado === 'error') return <Aviso tono="error">No pudimos conectar con Metabase. Comprueba que el servicio esté encendido. <Boton variante="fantasma" onClick={() => { cambiarEstado('cargando'); cambiarIntento((valor) => valor + 1) }}>Reintentar</Boton></Aviso>

  return <div className="panel-metabase"><metabase-dashboard key={jwt} token={jwt} with-title="false" with-downloads="false" /></div>
}

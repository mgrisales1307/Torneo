import { useState } from 'react'
import { Confeti } from './Confeti'
import video1440 from '../../assets/estadio-1440.mp4'
import video720 from '../../assets/estadio-720.mp4'
import cartel from '../../assets/estadio-cartel.jpg'

/*
 * Fondo animado de la portada.
 *
 * Se apila así, de atrás hacia delante:
 *
 *   1. el cartel      un fotograma del vídeo, para que la portada tenga imagen
 *                     en el primer pintado y no haya un hueco negro mientras
 *                     carga el vídeo
 *   2. el vídeo       la tribuna de verdad, en bucle sin costura y sin sonido
 *   3. el velo        degradados que oscurecen donde va el texto
 *   4. los rayos      la luz de los focos, respirando despacio
 *   5. el confeti     partículas en canvas, en el dorado de la marca
 *
 * El vídeo no se sirve a todo el mundo: quien pide movimiento reducido, quien
 * navega con ahorro de datos y quien está en 2G se queda con el cartel, que ya
 * es la misma imagen. Nadie ve una portada rota por eso.
 */
function elegirFuente() {
  if (typeof window === 'undefined') return null

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return null

  const conexion = navigator.connection

  if (conexion?.saveData === true) return null
  if (typeof conexion?.effectiveType === 'string' && conexion.effectiveType.includes('2g')) return null

  // En pantallas pequeñas no tiene sentido bajar 3,5 MB de 1440p.
  return window.matchMedia('(min-width: 860px)').matches ? video1440 : video720
}

export function FondoDeEstadio() {
  // Se decide una sola vez, antes del primer pintado: así no hay un cambio de
  // fuente a media carga ni un efecto que provoque otro render.
  const [fuente] = useState(elegirFuente)

  return (
    <div className="fondo-estadio" aria-hidden="true">
      <img className="fondo-estadio__cartel" src={cartel} alt="" fetchPriority="high" decoding="async" />

      {fuente && (
        <video
          className="fondo-estadio__video"
          src={fuente}
          poster={cartel}
          autoPlay
          muted
          loop
          playsInline
          preload="auto"
          tabIndex={-1}
        />
      )}

      <div className="fondo-estadio__velo" />
      <div className="fondo-estadio__luz" />
      <Confeti />
    </div>
  )
}

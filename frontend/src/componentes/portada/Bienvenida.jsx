import { useEffect, useRef, useState } from 'react'
import { Volume2, VolumeX } from 'lucide-react'
import videoGrande from '../../assets/bienvenida/bienvenida-720.mp4'
import videoChico from '../../assets/bienvenida/bienvenida-480.mp4'
import cartel from '../../assets/bienvenida/bienvenida-cartel.jpg'

/*
 * ¿Vale la pena descargar el vídeo? No, para quien pidió menos movimiento, para
 * quien navega con ahorro de datos y para quien está en una conexión 2G: a
 * ellos les basta el cartel, que es un fotograma del mismo vídeo.
 */
function conVideo() {
  if (typeof window === 'undefined') return false

  const conexion = navigator.connection

  return !(
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ||
    conexion?.saveData ||
    ['slow-2g', '2g'].includes(conexion?.effectiveType)
  )
}

/**
 * El cinemático de bienvenida de la portada, a todo el ancho y debajo del
 * título. Arranca silenciado —los navegadores no dejan reproducir sonido sin
 * que la persona lo pida— y el botón de la esquina le da voz.
 *
 * Hay dos codificaciones: 1280 × 720 para pantallas anchas y 854 × 480 para
 * móviles, que pesa la mitad.
 */
export function Bienvenida({ children }) {
  const video = useRef(null)
  const [reproducir] = useState(conVideo)
  const [silenciado, setSilenciado] = useState(true)
  const [fuente] = useState(() => (window.matchMedia?.('(max-width: 48rem)').matches ? videoChico : videoGrande))

  useEffect(() => {
    if (video.current) video.current.muted = silenciado
  }, [silenciado])

  return (
    <section className="bienvenida" aria-label="Bienvenida">
      {reproducir ? (
        <video
          ref={video}
          className="bienvenida__video"
          src={fuente}
          poster={cartel}
          autoPlay
          muted
          loop
          playsInline
          preload="auto"
          aria-hidden="true"
        />
      ) : (
        <img className="bienvenida__video" src={cartel} alt="" aria-hidden="true" />
      )}

      <span className="bienvenida__velo" aria-hidden="true" />

      <div className="contenedor bienvenida__contenido">{children}</div>

      {reproducir && (
        <button
          type="button"
          className="bienvenida__sonido"
          onClick={() => setSilenciado((actual) => !actual)}
          aria-pressed={!silenciado}
          aria-label={silenciado ? 'Activar el sonido del vídeo' : 'Silenciar el vídeo'}
        >
          {silenciado ? <VolumeX size={18} aria-hidden="true" /> : <Volume2 size={18} aria-hidden="true" />}
        </button>
      )}
    </section>
  )
}

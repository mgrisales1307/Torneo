import { useEffect, useRef } from 'react'

/*
 * Confeti en canvas.
 *
 * Va aparte del vídeo a propósito. El metraje de archivo que trae confeti lo
 * trae del color de otro equipo; generándolo aquí sale en el dorado de la marca
 * y se puede ajustar sin volver a codificar un vídeo de tres megas.
 *
 * Lo que hace que parezca papel y no puntos cayendo:
 *
 *   - Tres capas de profundidad. Las de delante son grandes, rápidas y algo
 *     desenfocadas; las de atrás, pequeñas, lentas y tenues. Eso da relieve.
 *   - Cada papelito gira sobre dos ejes. El giro en Y se simula achatando el
 *     rectángulo, así que a veces se ve de canto y casi desaparece: es lo que
 *     delata que es una lámina y no una bolita.
 *   - Rozamiento del aire y bamboleo propio, con fase distinta por partícula.
 *   - Rachas de viento cada tantos segundos, para que la caída no sea uniforme.
 */

const COLORES = [
  { r: 234, g: 184, b: 87 },   // dorado de la marca
  { r: 246, g: 243, b: 233 },  // crema
  { r: 201, g: 148, b: 58 },   // ámbar oscuro
  { r: 255, g: 214, b: 138 },  // dorado claro
]

function crearPapelito(ancho, alto, capa, arriba) {
  // capa 0 = fondo, 2 = primer plano.
  const escala = [0.55, 0.8, 1.25][capa]
  const color = COLORES[Math.floor(Math.random() * COLORES.length)]

  return {
    capa,
    x: Math.random() * ancho,
    // Al arrancar se reparten por toda la pantalla; después nacen arriba.
    y: arriba ? -20 - Math.random() * alto * 0.5 : Math.random() * alto,
    ancho: (5 + Math.random() * 6) * escala,
    alto: (9 + Math.random() * 8) * escala,
    color,
    caida: (22 + Math.random() * 26) * escala,
    bamboleo: 12 + Math.random() * 26,
    fase: Math.random() * Math.PI * 2,
    frecuencia: 0.5 + Math.random() * 1.1,
    giro: Math.random() * Math.PI * 2,
    velocidadDeGiro: (Math.random() - 0.5) * 2.6,
    canto: Math.random() * Math.PI * 2,
    velocidadDeCanto: 1.4 + Math.random() * 2.8,
    opacidad: [0.5, 0.72, 0.9][capa] * (0.75 + Math.random() * 0.25),
  }
}

export function Confeti({ densidad = 1 }) {
  const lienzo = useRef(null)

  useEffect(() => {
    const elemento = lienzo.current

    if (!elemento) return undefined

    const quietud = window.matchMedia('(prefers-reduced-motion: reduce)')
    const pincel = elemento.getContext('2d', { alpha: true })

    if (!pincel) return undefined

    let ancho = 0
    let alto = 0
    let papelitos = []
    let fotograma = 0
    let ultimoInstante = 0
    let visible = true

    function medir() {
      const puntos = Math.min(window.devicePixelRatio || 1, 2)
      const caja = elemento.getBoundingClientRect()

      ancho = caja.width
      alto = caja.height
      elemento.width = Math.round(ancho * puntos)
      elemento.height = Math.round(alto * puntos)
      pincel.setTransform(puntos, 0, 0, puntos, 0, 0)

      // La cantidad va con el área, no fija: en un móvil no tiene sentido
      // dibujar los mismos papelitos que en un monitor ancho.
      const cuantos = Math.round((ancho * alto) / 11000 * densidad)
      const total = Math.max(26, Math.min(190, cuantos))

      papelitos = Array.from({ length: total }, (_, i) =>
        crearPapelito(ancho, alto, i % 3, false),
      )
    }

    function dibujar(papelito) {
      const { color, opacidad } = papelito
      // El canto: cuando el papel se pone de perfil, se estrecha y se apaga.
      const perfil = Math.abs(Math.cos(papelito.canto))
      const anchoVisto = papelito.ancho * (0.15 + perfil * 0.85)

      pincel.save()
      pincel.translate(papelito.x, papelito.y)
      pincel.rotate(papelito.giro)
      pincel.globalAlpha = opacidad * (0.45 + perfil * 0.55)
      pincel.fillStyle = `rgb(${color.r} ${color.g} ${color.b})`
      pincel.fillRect(-anchoVisto / 2, -papelito.alto / 2, anchoVisto, papelito.alto)
      pincel.restore()
    }

    function paso(instante) {
      fotograma = requestAnimationFrame(paso)

      if (!visible) {
        ultimoInstante = instante
        return
      }

      // Se limita el salto de tiempo: si la pestaña estuvo en segundo plano, el
      // primer fotograma traería un delta enorme y el confeti daría un tirón.
      const delta = Math.min((instante - ultimoInstante) / 1000, 0.05)

      ultimoInstante = instante
      pincel.clearRect(0, 0, ancho, alto)

      // Racha de viento: una onda lenta más otra más corta, para que no se note
      // el ciclo.
      const segundos = instante / 1000
      const viento = Math.sin(segundos * 0.11) * 16 + Math.sin(segundos * 0.37) * 7

      for (const papelito of papelitos) {
        papelito.fase += delta * papelito.frecuencia
        papelito.giro += delta * papelito.velocidadDeGiro
        papelito.canto += delta * papelito.velocidadDeCanto

        const empuje = 0.35 + papelito.capa * 0.32

        papelito.y += papelito.caida * delta
        papelito.x += (Math.sin(papelito.fase) * papelito.bamboleo + viento * empuje) * delta

        if (papelito.y - papelito.alto > alto) {
          Object.assign(papelito, crearPapelito(ancho, alto, papelito.capa, true))
        }

        if (papelito.x < -30) papelito.x = ancho + 20
        else if (papelito.x > ancho + 30) papelito.x = -20

        dibujar(papelito)
      }
    }

    function pintarQuieto() {
      pincel.clearRect(0, 0, ancho, alto)
      // Con movimiento reducido no se anima nada: queda una lluvia detenida,
      // que sigue aportando textura sin marear a nadie.
      papelitos.forEach(dibujar)
    }

    function arrancar() {
      cancelAnimationFrame(fotograma)
      medir()

      if (quietud.matches) {
        pintarQuieto()
        return
      }

      ultimoInstante = performance.now()
      fotograma = requestAnimationFrame(paso)
    }

    function alCambiarLaPestana() {
      visible = document.visibilityState === 'visible'
    }

    // Cuando la portada sale de pantalla se deja de dibujar: no tiene sentido
    // gastar batería animando lo que nadie ve.
    const observador = new IntersectionObserver(
      ([entrada]) => {
        visible = entrada.isIntersecting && document.visibilityState === 'visible'
      },
      { threshold: 0 },
    )

    observador.observe(elemento)
    arrancar()

    window.addEventListener('resize', arrancar)
    document.addEventListener('visibilitychange', alCambiarLaPestana)
    quietud.addEventListener('change', arrancar)

    return () => {
      cancelAnimationFrame(fotograma)
      observador.disconnect()
      window.removeEventListener('resize', arrancar)
      document.removeEventListener('visibilitychange', alCambiarLaPestana)
      quietud.removeEventListener('change', arrancar)
    }
  }, [densidad])

  return <canvas ref={lienzo} className="portada__confeti" aria-hidden="true" />
}

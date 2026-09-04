import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const apiPort = process.env.API_PORT ?? '8000'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    // Si el 5173 estuviera ocupado, Vite se cambiaría de puerto en silencio y
    // la página no cargaría en la dirección esperada. Preferimos que falle
    // aquí, diciendo el motivo.
    strictPort: true,
    // El navegador habla siempre con http://localhost:5173, y Vite reenvía las
    // llamadas a la API. Al quedar todo en un mismo origen no hay CORS que
    // configurar ni cookies de sesión que se pierdan entre dominios.
    proxy: {
      '/api': `http://localhost:${apiPort}`,
      '/sanctum': `http://localhost:${apiPort}`,
    },
  },
})

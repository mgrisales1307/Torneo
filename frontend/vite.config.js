import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    // El backend solo autoriza http://localhost:5173 como origen. Si Vite
    // cambiara de puerto en silencio, la página cargaría pero el login
    // fallaría por CORS: es preferible que falle aquí, con un mensaje claro.
    strictPort: true,
  },
})

import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ProveedorDeSesion } from './contexto/ProveedorDeSesion'
import App from './App.jsx'
import './index.css'

/**
 * Configuración de la caché de datos.
 *
 * Los datos se consideran frescos medio minuto: evita que cambiar de pestaña
 * dispare una ráfaga de peticiones, y aun así la tabla de posiciones se
 * actualiza sola al volver a la ventana.
 */
const clienteDeConsultas = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30 * 1000,
      retry: 1,
      refetchOnWindowFocus: true,
    },
  },
})

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <QueryClientProvider client={clienteDeConsultas}>
      <BrowserRouter>
        <ProveedorDeSesion>
          <App />
        </ProveedorDeSesion>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)

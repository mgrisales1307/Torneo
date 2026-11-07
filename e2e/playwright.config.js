import { defineConfig, devices } from '@playwright/test'

// Puertos propios de las pruebas: no chocan con la API (8000) ni con el
// frontend (5173) de desarrollo, que pueden estar encendidos.
const PUERTO_API = process.env.E2E_API_PORT ?? '8010'
const PUERTO_WEB = process.env.E2E_WEB_PORT ?? '5183'
const ORIGEN = `http://127.0.0.1:${PUERTO_WEB}`

/*
 * Las pruebas hablan con el navegador contra el frontend de Vite, y Vite
 * reenvía /api y /sanctum a Laravel: exactamente el camino de uso real.
 *
 * Un solo worker y sin paralelismo: todas comparten la misma base de datos.
 */
export default defineConfig({
  testDir: './pruebas',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never' }]],

  use: {
    baseURL: ORIGEN,
    locale: 'es-CO',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: [
    {
      command: 'bash e2e/servidor-api.sh',
      cwd: '..',
      url: `http://127.0.0.1:${PUERTO_API}/up`,
      env: { E2E_API_PORT: PUERTO_API, E2E_WEB_PORT: PUERTO_WEB },
      timeout: 240_000,
      reuseExistingServer: false,
      stdout: 'pipe',
      stderr: 'pipe',
    },
    {
      command: `npm run dev -- --port ${PUERTO_WEB}`,
      cwd: '../frontend',
      url: ORIGEN,
      timeout: 120_000,
      reuseExistingServer: false,
      env: { API_PORT: PUERTO_API },
    },
  ],
})

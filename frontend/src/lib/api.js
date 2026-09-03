import axios from 'axios'

const baseURL = (import.meta.env.VITE_API_URL || 'http://localhost:8000').replace(/\/$/, '')

const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

async function prepareCsrfProtection() {
  await api.get('/sanctum/csrf-cookie')
}

export async function getCurrentUser() {
  const { data } = await api.get('/api/me')

  return data.user
}

export async function login(credentials) {
  await prepareCsrfProtection()

  const { data } = await api.post('/api/login', credentials)

  return data.user
}

export async function logout() {
  await prepareCsrfProtection()
  await api.post('/api/logout')
}

export function isUnauthenticated(error) {
  return axios.isAxiosError(error) && error.response?.status === 401
}

export function getLoginError(error) {
  if (!axios.isAxiosError(error)) {
    return 'Ocurrió algo inesperado. Inténtalo nuevamente.'
  }

  if (!error.response) {
    return 'No pudimos conectar con el servidor. Revisa que el backend esté en ejecución.'
  }

  if (error.response.status === 429) {
    return 'Demasiados intentos. Espera un minuto antes de volver a intentar.'
  }

  const validationMessage = Object.values(error.response.data?.errors ?? {})
    .flat()
    .find(Boolean)

  return validationMessage ?? error.response.data?.message ?? 'No fue posible iniciar sesión.'
}

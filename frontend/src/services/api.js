import axios from 'axios'
import { disconnectEcho } from './echo'

/**
 * INTERNTRACK API Service
 * Centralized Axios instance for all Laravel backend communication.
 * React never communicates with the MISD API directly.
 *
 * Set VITE_API_BASE_URL in frontend/.env to match your local Laravel host/port.
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8001/api/v1',
  headers: {
    'Content-Type': 'application/json; charset=UTF-8',
    'Accept': 'application/json',
  },
  withCredentials: false,
})

// ── Request Interceptor: Attach Sanctum Token ─────────────────────────────────
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('interntrack_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // FormData uploads must let the browser set multipart boundaries.
  // Do not force Content-Type: application/json or multipart/form-data here.
  if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
    if (typeof config.headers?.set === 'function') {
      config.headers.set('Content-Type', false)
    } else if (config.headers) {
      delete config.headers['Content-Type']
      delete config.headers['content-type']
    }
  }

  return config
})

// ── Response Interceptor: Handle Global Errors ────────────────────────────────
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const requestUrl = String(error.config?.url || '')

    // Let AuthContext/ConfirmLogoutModal handle logout 401s (token already gone).
    if (status === 401 && !requestUrl.includes('/auth/logout')) {
      disconnectEcho()
      sessionStorage.removeItem('interntrack_token')
      sessionStorage.removeItem('interntrack_session')
      window.location.href = '/'
    }

    return Promise.reject(error)
  }
)

export default api

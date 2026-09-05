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
    'Content-Type': 'application/json',
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

  const selectedInternshipId = sessionStorage.getItem('interntrack_active_internship')
  if (selectedInternshipId) {
    config.headers['X-Internship-Id'] = selectedInternshipId
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

    if (status === 403 && !requestUrl.includes('/files/download')) {
      const detail = error.response?.data?.message || 'Access denied — different department'
      window.dispatchEvent(new CustomEvent('access-denied', { detail }))
    }

    return Promise.reject(error)
  }
)

export default api

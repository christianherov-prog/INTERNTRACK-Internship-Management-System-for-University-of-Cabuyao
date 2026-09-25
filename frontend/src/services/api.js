import axios from 'axios'
import { disconnectEcho } from './echo'
import { resolveApiBaseUrl } from '../utils/apiBase'
import { clearUserScopedState, getActiveInternshipId } from '../utils/sessionState'

/**
 * INTERNTRACK API Service
 * Centralized Axios instance for all Laravel backend communication.
 * React never communicates with the MISD API directly.
 *
 * Host follows the page (localhost vs LAN). Port/path from VITE_API_BASE_URL.
 */
const api = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    // Marks SPA requests: the API only accepts the session cookie with this header.
    'X-Requested-With': 'XMLHttpRequest',
  },
  // The Sanctum token lives in an HttpOnly cookie set by /auth/login. Every tab
  // of the browser shares it, and JavaScript never reads or stores the token.
  withCredentials: true,
})

// ── Request Interceptor ───────────────────────────────────────────────────────
api.interceptors.request.use((config) => {
  // Only an internship selected by the account signed in on this tab is sent;
  // another account's leftover selection is discarded, never forwarded.
  const selectedInternshipId = getActiveInternshipId()
  if (selectedInternshipId && config.headers?.['X-Internship-Id'] === undefined) {
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

    // Session ended (logout in another tab, expiry, deactivation, or lockout).
    // Let AuthContext handle /auth/logout and the initial /auth/user probe itself.
    const isAuthProbe = requestUrl.includes('/auth/logout') || requestUrl.includes('/auth/user') || requestUrl.includes('/auth/login')
    if (status === 401 && !isAuthProbe) {
      disconnectEcho()
      clearUserScopedState()
      if (window.location.pathname !== '/' && !window.location.pathname.startsWith('/supervisor/login')) {
        window.location.href = '/'
      }
    }

    if (status === 403 && !requestUrl.includes('/files/download')) {
      const message = error.response?.data?.message || ''
      const isAttendanceWorkflow =
        requestUrl.includes('/student/attendance')
        || /HTE Supervisor is approved/i.test(message)
      if (!isAttendanceWorkflow) {
        const detail = message || 'Access denied — different department'
        window.dispatchEvent(new CustomEvent('access-denied', { detail }))
      }
    }

    return Promise.reject(error)
  }
)

export default api

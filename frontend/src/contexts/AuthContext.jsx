import { createContext, useContext, useState, useEffect } from 'react'
import api from '../services/api'
import { withAvatarCacheBust } from '../utils/avatar'
import { disconnectEcho } from '../services/echo'
import { cacheClear } from '../utils/pageCache'
import { broadcastAuthEvent, onAuthEvent } from '../utils/authSync'

const AuthContext = createContext()

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}

export function AuthProvider({ children }) {
  const [user, setUser]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)

  // ── Restore session on load / new tab — the HttpOnly cookie is shared by all
  // tabs, so always ask the server who is signed in (role + account status are
  // revalidated there on every request).
  useEffect(() => {
    // Optimistic paint from this tab's cache while the server confirms.
    const storedUser = sessionStorage.getItem('interntrack_session')
    if (storedUser) {
      try {
        setUser(JSON.parse(storedUser))
        setLoading(false)
      } catch {
        sessionStorage.removeItem('interntrack_session')
      }
    }

    const controller = new AbortController()

    api.get('/auth/user', { signal: controller.signal })
      .then(({ data }) => {
        setUser(data.user)
        sessionStorage.setItem('interntrack_session', JSON.stringify(data.user))
      })
      .catch((err) => {
        if (err.name === 'CanceledError' || err.name === 'AbortError') return
        if (err.response?.status === 401) {
          setUser(null)
          sessionStorage.removeItem('interntrack_session')
        }
      })
      .finally(() => setLoading(false))

    return () => controller.abort()
  }, [])

  // ── Keep tabs consistent: logout (or a different login) in one tab resets the others.
  useEffect(() => onAuthEvent((event) => {
    if (event.type === 'logout') {
      disconnectEcho()
      cacheClear()
      setUser(null)
      sessionStorage.removeItem('interntrack_session')
      sessionStorage.removeItem('interntrack_staff_workspace')
      if (window.location.pathname !== '/') window.location.replace('/')
      return
    }
    if (event.type === 'login') {
      // Another tab signed in (maybe as someone else): reload so this tab uses
      // the same account and never shows the previous user's data.
      cacheClear()
      sessionStorage.removeItem('interntrack_session')
      window.location.reload()
    }
  }), [])

  // ── Login: authenticate against Laravel Sanctum API ─────────────────────────
  const login = async (username, password) => {
    setError(null)
    try {
      const { data } = await api.post('/auth/login', { username, password })

      // The token is kept by the browser as an HttpOnly cookie (never in JS storage).
      sessionStorage.setItem('interntrack_session', JSON.stringify(data.user))
      // Fresh login always starts in the account's own workspace.
      sessionStorage.removeItem('interntrack_staff_workspace')

      cacheClear()
      setUser(data.user)
      broadcastAuthEvent('login', data.user?.id ?? null)
      return { success: true, user: data.user }
    } catch (err) {
      const apiMessage = err.response?.data?.message
      let message
      if (!err.response) {
        message = 'Cannot reach the InternTrack server. Make sure MySQL and the API are running, then try again.'
      } else if (err.response?.status === 429) {
        message = (typeof apiMessage === 'string' && apiMessage)
          ? apiMessage
          : 'Too many login attempts. Please try again in a moment.'
      } else {
        message =
          err.response?.data?.errors?.username?.[0] ||
          apiMessage ||
          'Login failed. Please check your credentials.'
      }
      setError(message)
      return { success: false, error: message }
    }
  }

  const clearSession = () => {
    disconnectEcho()
    cacheClear()
    setUser(null)
    sessionStorage.removeItem('interntrack_session')
    sessionStorage.removeItem('interntrack_staff_workspace')
    broadcastAuthEvent('logout')
  }

  // ── Logout: revoke Sanctum token, then clear local session ──────────────────
  // Only clears frontend auth after the API succeeds (or the token is already 401).
  // Network / 5xx failures leave the session intact so a still-valid token isn't orphaned.
  const logout = async () => {
    try {
      await api.post('/auth/logout')
      clearSession()
      return { success: true }
    } catch (err) {
      // Token already invalid/revoked — safe to clear local state.
      if (err.response?.status === 401) {
        clearSession()
        return { success: true }
      }

      return {
        success: false,
        error:
          err.response?.data?.message ||
          'Logout failed. Please check your connection and try again.',
      }
    }
  }

  // ── Refresh user from API (e.g. after profile update) ───────────────────────
  const refreshUser = async () => {
    try {
      const { data } = await api.get('/auth/user')
      setUser(data.user)
      sessionStorage.setItem('interntrack_session', JSON.stringify(data.user))
    } catch {
      // silently fail
    }
  }

  // ── Update shared AuthContext user (navbar + settings read from this) ───────
  const updateUserLocal = (updates) => {
    const next = { ...user, ...updates }

    // Cache-bust whenever a new avatar URL (or explicit version) is provided.
    if (updates?.avatarUrl && (updates.avatarUrl !== user?.avatarUrl || updates.avatarVersion != null)) {
      next.avatarVersion = updates.avatarVersion ?? Date.now()
      next.avatarUrl = withAvatarCacheBust(updates.avatarUrl, next.avatarVersion)
    }

    setUser(next)
    sessionStorage.setItem('interntrack_session', JSON.stringify(next))
  }

  return (
    <AuthContext.Provider value={{ 
      user, 
      login, 
      logout, 
      loading, 
      error, 
      refreshUser,
      updateUserLocal
    }}>
      {children}
    </AuthContext.Provider>
  )
}

import { createContext, useContext, useState, useEffect } from 'react'
import api from '../services/api'
import { withAvatarCacheBust } from '../utils/avatar'
import { disconnectEcho } from '../services/echo'

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

  // ── Restore session on page reload — revalidate via GET /auth/user ──────────
  useEffect(() => {
    const token = sessionStorage.getItem('interntrack_token')

    if (!token) {
      setLoading(false)
      return
    }

    // Optimistic paint from cache while the token is verified.
    const storedUser = sessionStorage.getItem('interntrack_session')
    if (storedUser) {
      try {
        setUser(JSON.parse(storedUser))
      } catch {
        sessionStorage.removeItem('interntrack_session')
      }
    }

    api.get('/auth/user')
      .then(({ data }) => {
        setUser(data.user)
        sessionStorage.setItem('interntrack_session', JSON.stringify(data.user))
      })
      .catch((err) => {
        if (err.response?.status === 401) {
          setUser(null)
          sessionStorage.removeItem('interntrack_token')
          sessionStorage.removeItem('interntrack_session')
        }
      })
      .finally(() => setLoading(false))
  }, [])

  // ── Login: authenticate against Laravel Sanctum API ─────────────────────────
  const login = async (username, password) => {
    setError(null)
    try {
      const { data } = await api.post('/auth/login', { username, password })

      // Persist token and user in session storage
      sessionStorage.setItem('interntrack_token', data.token)
      sessionStorage.setItem('interntrack_session', JSON.stringify(data.user))

      setUser(data.user)
      return { success: true, user: data.user }
    } catch (err) {
      console.error("Login error:", err)
      const apiMessage = err.response?.data?.message
      const message =
        err.response?.status === 429
          ? (typeof apiMessage === 'string' && apiMessage
              ? apiMessage
              : 'Too many login attempts. Please try again in a moment.')
          : (err.response?.data?.errors?.username?.[0] ||
              apiMessage ||
              'Login failed. Please check your credentials.')
      setError(message)
      return { success: false, error: message }
    }
  }

  const clearSession = () => {
    disconnectEcho()
    setUser(null)
    sessionStorage.removeItem('interntrack_token')
    sessionStorage.removeItem('interntrack_session')
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

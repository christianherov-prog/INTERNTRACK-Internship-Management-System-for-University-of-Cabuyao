import { useAuth } from '../contexts/AuthContext'

/** Academic term from /auth/user (config interntrack.current_term). */
export function useCurrentTerm(fallback = 'AY 2025-2026, Sem 2') {
  const { user } = useAuth()
  return user?.term || fallback
}

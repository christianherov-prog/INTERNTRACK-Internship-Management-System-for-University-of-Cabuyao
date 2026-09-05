import { useAuth } from '../contexts/AuthContext'

/** Academic term from /auth/user (config interntrack.current_term). */
export function useCurrentTerm(fallback = '') {
  const { user } = useAuth()
  return user?.term || fallback
}

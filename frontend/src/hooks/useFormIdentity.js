import { useAuth } from '../contexts/AuthContext'
import { useCurrentTerm } from './useCurrentTerm'
import { resolveFormIdentity } from '../utils/formIdentity'

/**
 * Supplies official-form identity/reference fields from the internship
 * assignment plus the signed-in user / current term — one shared source.
 */
export function useFormIdentity(internship, extras = {}) {
  const { user } = useAuth()
  const term = useCurrentTerm()
  return resolveFormIdentity(internship, { user, term, ...extras })
}

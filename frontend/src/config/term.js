/**
 * Frontend mirror of backend config('interntrack.current_term').
 * Keep in sync with INTERNTRACK_CURRENT_TERM / VITE_INTERNTRACK_CURRENT_TERM.
 */
export const CURRENT_TERM =
  import.meta.env.VITE_INTERNTRACK_CURRENT_TERM || 'AY 2025-2026, Sem 2'

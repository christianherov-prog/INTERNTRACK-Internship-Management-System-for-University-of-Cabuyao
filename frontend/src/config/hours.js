/**
 * Frontend mirror of backend config('interntrack.target_hours').
 * CCS OJT requirement (BSIT / BSCS): uniform 500 hours.
 * Keep in sync with INTERNTRACK_TARGET_HOURS / VITE_INTERNTRACK_TARGET_HOURS.
 */
export const DEFAULT_TARGET_HOURS = Number(
  import.meta.env.VITE_INTERNTRACK_TARGET_HOURS || 500
)

export default DEFAULT_TARGET_HOURS

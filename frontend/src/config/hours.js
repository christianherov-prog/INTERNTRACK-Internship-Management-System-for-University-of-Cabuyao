/**
 * Display helper for required internship hours.
 * The API is the source of truth (program_hte_requirements).
 * Do not invent a campus-wide 500-hour default here.
 */
export function resolveTargetHours(value) {
  const n = Number(value)
  return Number.isFinite(n) && n > 0 ? n : 0
}

/** @deprecated Use resolveTargetHours() with the API value. Kept so older imports do not crash. */
export const DEFAULT_TARGET_HOURS = 0

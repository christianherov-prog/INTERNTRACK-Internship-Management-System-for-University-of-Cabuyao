/**
 * Display helpers for Asia/Manila. Explicit IANA zone — not the browser timezone.
 */
export const MANILA_TZ = 'Asia/Manila'

/**
 * Extract YYYY-MM-DD from a date-only string or ISO midnight timestamp
 * without applying timezone day-shift (e.g. 2026-09-13T00:00:00.000000Z → 2026-09-13).
 */
export function toDateOnlyString(value) {
  if (value == null || value === '') return ''
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    // Prefer calendar parts in Manila for true Date instances from datetime fields.
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: MANILA_TZ,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(value)
    const y = parts.find((p) => p.type === 'year')?.value
    const m = parts.find((p) => p.type === 'month')?.value
    const d = parts.find((p) => p.type === 'day')?.value
    if (y && m && d) return `${y}-${m}-${d}`
  }
  const raw = String(value).trim()
  const match = raw.match(/^(\d{4}-\d{2}-\d{2})/)
  return match ? match[1] : ''
}

/**
 * Human-readable date for date-only fields (attendance.date, journal dates, etc.).
 * Preserves calendar day when API sends ISO midnight UTC.
 *
 * Example: "2026-09-13T00:00:00.000000Z" → "September 13, 2026"
 */
export function formatDisplayDate(value, options = { month: 'long', day: 'numeric', year: 'numeric' }) {
  const iso = toDateOnlyString(value)
  if (!iso) return ''
  const [year, month, day] = iso.split('-').map(Number)
  if (!year || !month || !day) return iso
  // Construct a local calendar date from Y-M-D parts — do not parse as UTC.
  return new Date(year, month - 1, day).toLocaleDateString('en-PH', options)
}

/**
 * Asia/Manila wall-clock "HH:mm" / "HH:mm:ss" → "8:00 AM", "1:00 PM".
 * Use for values the API already resolved to Manila (clock_in_display,
 * *_display correction fields, schedule start/end). No timezone is applied
 * here, so a time is never converted twice.
 */
export function formatClock12(value, fallback = '—') {
  if (value == null || value === '') return fallback
  const match = String(value).trim().match(/^(\d{1,2}):(\d{2})/)
  if (!match) return fallback
  const hour = Number(match[1])
  if (hour > 23) return fallback
  return `${hour % 12 || 12}:${match[2]} ${hour >= 12 ? 'PM' : 'AM'}`
}

export function formatManilaTime(value = new Date(), options = { hour: 'numeric', minute: '2-digit', hour12: true }) {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleTimeString('en-PH', { timeZone: MANILA_TZ, ...options })
}

/**
 * Calendar date of a real timestamp (created_at, submitted_at, …) in Asia/Manila.
 * Use formatDisplayDate for date-only fields; this one converts the instant first,
 * so 2026-09-25T17:30:00Z correctly reads as September 26, 2026 in Manila.
 */
export function formatManilaDate(value, options = { month: 'short', day: 'numeric', year: 'numeric' }) {
  if (!value) return '—'
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('en-PH', { timeZone: MANILA_TZ, ...options })
}

export function formatManilaDateTime(value) {
  if (!value) return '—'
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('en-PH', {
    timeZone: MANILA_TZ,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

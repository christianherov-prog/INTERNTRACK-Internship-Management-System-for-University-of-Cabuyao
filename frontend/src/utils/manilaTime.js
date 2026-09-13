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

export function formatManilaTime(value = new Date(), options = { hour: 'numeric', minute: '2-digit', hour12: true }) {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleTimeString('en-PH', { timeZone: MANILA_TZ, ...options })
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

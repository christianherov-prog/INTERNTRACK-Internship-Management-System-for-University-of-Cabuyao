/**
 * Display helpers for Asia/Manila. Explicit IANA zone — not the browser timezone.
 */
export const MANILA_TZ = 'Asia/Manila'

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

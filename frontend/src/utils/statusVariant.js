/**
 * Shared status → visual variant mapping for reports and status chips.
 *
 *   success  → green  (Approved, Completed, Satisfied, Active …)
 *   warning  → amber  (Pending, Pending Review, Submitted, Awaiting …)
 *   danger   → red    (Missing, Rejected, Returned, Incomplete …)
 *   neutral  → gray   (Not Applicable, Not Yet Required, unknown)
 *
 * Only the display changes; the underlying status value is never rewritten.
 */
const VARIANTS = {
  success: [
    'approved', 'completed', 'complete', 'satisfied', 'validated', 'finalized',
    'active', 'ongoing', 'placed', 'absorbed',
  ],
  warning: [
    'pending', 'pending_review', 'pending review', 'submitted', 'awaiting_review',
    'awaiting review', 'under_review', 'under review', 'pending_faculty', 'resubmitted',
    'for_evaluation', 'pending_placement', 'expiring', 'expiring_soon',
  ],
  danger: [
    'missing', 'rejected', 'returned', 'incomplete', 'declined', 'expired',
    'terminated', 'failed', 'expelled', 'cancelled', 'withdrawn', 'not_hired',
  ],
  neutral: [
    'not_applicable', 'not applicable', 'n/a', 'not_yet_required', 'not yet required',
    'suspended', 'deferred', 'inactive',
  ],
}

const LOOKUP = Object.entries(VARIANTS).reduce((map, [variant, keys]) => {
  keys.forEach((key) => { map[key] = variant })
  return map
}, {})

function normalizeStatus(status) {
  return String(status ?? '').trim().toLowerCase()
}

/** @returns {'success'|'warning'|'danger'|'neutral'} */
export function getStatusVariant(status) {
  const key = normalizeStatus(status)
  if (!key) return 'neutral'
  return LOOKUP[key] ?? LOOKUP[key.replace(/\s+/g, '_')] ?? 'neutral'
}

/** "pending_placement" → "Pending Placement"; already-readable labels pass through. */
export function formatStatusLabel(status, fallback = '—') {
  const raw = String(status ?? '').trim()
  if (!raw) return fallback
  if (/[A-Z]/.test(raw) && !raw.includes('_')) return raw
  return raw
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

export const STATUS_VARIANT_ICON = {
  success: 'fa-circle-check',
  warning: 'fa-clock',
  danger: 'fa-circle-xmark',
  neutral: 'fa-circle-minus',
}

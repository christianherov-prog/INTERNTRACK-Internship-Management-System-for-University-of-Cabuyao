import { getStatusVariant, formatStatusLabel, STATUS_VARIANT_ICON } from '../utils/statusVariant'

/**
 * Compact status chip for report tables. Color follows the shared variant map,
 * and the status is always written out so it reads without color (print / B&W).
 *
 *   <StatusChip status="pending_placement" />               → [◷ Pending Placement]
 *   <StatusChip name="Consent Form" status="rejected" />    → [✕ Consent Form · Rejected]
 */
export default function StatusChip({ status, label, name, className = '' }) {
  const variant = getStatusVariant(status)
  const statusText = label || formatStatusLabel(status)

  return (
    <span className={`it-status-chip it-status-chip--${variant} ${className}`.trim()} title={name ? `${name}: ${statusText}` : statusText}>
      <i className={`fa ${STATUS_VARIANT_ICON[variant]}`} aria-hidden="true"></i>
      {name ? (
        <>
          <span className="it-status-chip__name">{name}</span>
          <span className="it-status-chip__sep" aria-hidden="true">·</span>
          <span className="it-status-chip__status">{statusText}</span>
        </>
      ) : (
        <span className="it-status-chip__status">{statusText}</span>
      )}
    </span>
  )
}

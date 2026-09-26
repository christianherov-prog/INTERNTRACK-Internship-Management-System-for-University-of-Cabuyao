import StatusChip from './StatusChip'

/**
 * Requirements Status for Document Compliance reports: one compact chip per
 * requirement ("Consent Form · Rejected"). Color follows the shared status
 * variants (green / amber / red / gray) and the status word is always printed,
 * so the column stays readable in black-and-white print.
 *
 * Statuses come from the compliance resolver (DocumentComplianceService):
 * approved (labelled Approved or Completed), pending, rejected, missing.
 */
const STATUS_LABELS = {
  approved: 'Approved',
  pending: 'Pending Review',
  rejected: 'Rejected',
  missing: 'Missing',
}

export const ALL_REQUIREMENTS_APPROVED_MESSAGE = 'All Applicable Requirements Approved'

/**
 * Prefer authoritative `requirements` from the compliance resolver.
 * Fall back to legacy name lists so older payloads still render.
 */
export function normalizeRequirementStatuses(row = {}) {
  if (Array.isArray(row.requirements) && row.requirements.length > 0) {
    return row.requirements.map((item) => {
      const status = String(item.status || 'missing').toLowerCase()
      return {
        template_id: item.template_id ?? null,
        name: item.name,
        status,
        status_label: item.status_label || STATUS_LABELS[status] || STATUS_LABELS.missing,
        source: item.source || null,
      }
    })
  }

  const out = []
  const pushAll = (names, status) => {
    ;(names || []).forEach((name) => {
      out.push({ template_id: null, name, status, status_label: STATUS_LABELS[status], source: null })
    })
  }

  pushAll(row.satisfied_docs, 'approved')
  pushAll(row.pending_doc_types, 'pending')
  pushAll(row.rejected_docs, 'rejected')
  pushAll(row.missing_docs, 'missing')
  return out
}

export function isAllRequirementsApproved(items = []) {
  return items.length > 0 && items.every((item) => item.status === 'approved')
}

/** CSV keeps explicit plain-text labels (never markup). */
export function formatRequirementsStatusCsv(row) {
  const items = normalizeRequirementStatuses(row)
  if (isAllRequirementsApproved(items)) {
    return ALL_REQUIREMENTS_APPROVED_MESSAGE
  }
  return items.map((item) => `${item.name}: ${item.status_label}`).join('; ')
}

/**
 * Inverse of formatRequirementsStatusCsv for on-screen previews: turns the
 * exported plain text back into { name, status_label } items so the preview can
 * show chips while the downloaded CSV keeps the plain string.
 */
export function parseRequirementsStatusCsv(value) {
  const text = String(value ?? '').trim()
  if (!text || text === ALL_REQUIREMENTS_APPROVED_MESSAGE) return []
  return text.split(/;\s*/).filter(Boolean).map((part) => {
    const cut = part.lastIndexOf(':')
    if (cut === -1) return { name: part.trim(), status_label: '' }
    return { name: part.slice(0, cut).trim(), status_label: part.slice(cut + 1).trim() }
  })
}

export function RequirementChipList({ items }) {
  return (
    <ul className="it-status-chip-list" aria-label="Requirements status">
      {items.map((item, idx) => (
        <li key={`${item.template_id ?? item.name}-${idx}`}>
          <StatusChip name={item.name} status={item.status || item.status_label} label={item.status_label} />
        </li>
      ))}
    </ul>
  )
}

export default function ComplianceRequirementsStatus({ row }) {
  const items = normalizeRequirementStatuses(row)

  if (items.length === 0 || isAllRequirementsApproved(items)) {
    return (
      <span className="it-status-chip it-status-chip--success compliance-req-status-complete">
        <i className="fa fa-circle-check" aria-hidden="true"></i>
        <span className="it-status-chip__status">{ALL_REQUIREMENTS_APPROVED_MESSAGE}</span>
      </span>
    )
  }

  return <RequirementChipList items={items} />
}

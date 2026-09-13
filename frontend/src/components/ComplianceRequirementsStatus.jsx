/**
 * Simplified Requirements Status list for Document Compliance reports.
 * Visual: colored requirement name + small indicator (no repeated status words).
 * Screen readers still receive an accessible status via aria-label.
 */
const STATUS_META = {
  approved: {
    icon: 'fa-check',
    label: 'Approved',
    className: 'req-status-approved',
  },
  pending: {
    icon: 'fa-clock',
    label: 'Pending Review',
    className: 'req-status-pending',
  },
  rejected: {
    icon: 'fa-xmark',
    label: 'Rejected',
    className: 'req-status-rejected',
  },
  missing: {
    icon: 'fa-circle',
    label: 'Missing',
    className: 'req-status-missing',
  },
  not_applicable: {
    icon: 'fa-minus',
    label: 'Not Applicable',
    className: 'req-status-na',
  },
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
      const meta = STATUS_META[status] || STATUS_META.missing
      return {
        template_id: item.template_id ?? null,
        name: item.name,
        status,
        status_label: item.status_label || meta.label,
        source: item.source || null,
      }
    })
  }

  const out = []
  const pushAll = (names, status) => {
    ;(names || []).forEach((name) => {
      const meta = STATUS_META[status] || STATUS_META.missing
      out.push({
        template_id: null,
        name,
        status,
        status_label: meta.label,
        source: null,
      })
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

/** CSV keeps explicit labels (non-visual export). */
export function formatRequirementsStatusCsv(row) {
  const items = normalizeRequirementStatuses(row)
  if (isAllRequirementsApproved(items)) {
    return ALL_REQUIREMENTS_APPROVED_MESSAGE
  }
  return items.map((item) => `${item.name}: ${item.status_label}`).join('; ')
}

export default function ComplianceRequirementsStatus({ row }) {
  const items = normalizeRequirementStatuses(row)

  if (items.length === 0) {
    return (
      <span className="compliance-req-status-complete">
        {ALL_REQUIREMENTS_APPROVED_MESSAGE}
      </span>
    )
  }

  if (isAllRequirementsApproved(items)) {
    return (
      <span className="compliance-req-status-complete">
        {ALL_REQUIREMENTS_APPROVED_MESSAGE}
      </span>
    )
  }

  return (
    <ul className="compliance-req-status-list mb-0">
      {items.map((item) => {
        const meta = STATUS_META[item.status] || STATUS_META.missing
        const key = `${item.template_id ?? item.name}-${item.status}`
        return (
          <li
            key={key}
            className={`compliance-req-status-item ${meta.className}`}
            aria-label={`${item.name}: ${item.status_label}`}
          >
            <span className="compliance-req-status-icon" aria-hidden="true">
              <i className={`fa ${meta.icon}`} />
            </span>
            <span className="compliance-req-status-name">{item.name}</span>
          </li>
        )
      })}
    </ul>
  )
}

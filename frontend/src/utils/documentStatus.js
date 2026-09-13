/** Shared document workflow status labels (keep in sync with App\Support\DocumentStatuses). */
export const DOCUMENT_STATUS = {
  not_submitted:   { badge: 'badge-secondary text-dark', label: 'Not Submitted',           icon: 'fa-circle-xmark' },
  pending:         { badge: 'badge-pending',  label: 'Pending Review',          icon: 'fa-clock' },
  pending_review:  { badge: 'badge-pending',  label: 'Pending Review',          icon: 'fa-clock' },
  under_review:    { badge: 'badge-pending',  label: 'Under Review',            icon: 'fa-magnifying-glass' },
  pending_faculty: { badge: 'badge-pending',  label: 'Pending Faculty Approval',icon: 'fa-user-check' },
  completed:       { badge: 'badge-active',   label: 'Completed',               icon: 'fa-circle-check' },
  approved:        { badge: 'badge-active',   label: 'Approved',                icon: 'fa-circle-check' },
  rejected:        { badge: 'badge-danger',   label: 'Rejected',                icon: 'fa-triangle-exclamation' },
  resubmitted:     { badge: 'badge-pending',  label: 'Pending Faculty Approval',icon: 'fa-rotate' },
  no_submission:   { badge: 'badge-inactive', label: 'No Submission',           icon: 'fa-ban' },
}

export function documentStatusConfig(status) {
  return DOCUMENT_STATUS[status] ?? DOCUMENT_STATUS.not_submitted
}

export function documentStatusLabel(status) {
  return documentStatusConfig(status).label
}

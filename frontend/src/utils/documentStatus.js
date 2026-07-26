/** Shared document workflow status labels (keep in sync with App\Support\DocumentStatuses). */
export const DOCUMENT_STATUS = {
  not_submitted: { badge: 'badge-inactive', label: 'Not Submitted', icon: 'fa-circle-xmark' },
  pending_review: { badge: 'badge-pending', label: 'Coordinator Review', icon: 'fa-clock' },
  under_review: { badge: 'badge-pending', label: 'Under Review', icon: 'fa-magnifying-glass' },
  pending_faculty: { badge: 'badge-pending', label: 'Faculty Verification', icon: 'fa-user-check' },
  approved: { badge: 'badge-active', label: 'Fully Approved', icon: 'fa-circle-check' },
  rejected: { badge: 'badge-inactive', label: 'Rejected', icon: 'fa-triangle-exclamation' },
  resubmitted: { badge: 'badge-pending', label: 'Resubmitted', icon: 'fa-rotate' },
}

export function documentStatusConfig(status) {
  return DOCUMENT_STATUS[status] ?? DOCUMENT_STATUS.not_submitted
}

export function documentStatusLabel(status) {
  return documentStatusConfig(status).label
}

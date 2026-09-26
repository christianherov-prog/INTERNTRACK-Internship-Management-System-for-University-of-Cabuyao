import { useEffect, useState } from 'react'
import api from '../services/api'
import InternTrackLoader from './InternTrackLoader'
import AppModal from './modals/AppModal'
import { formatManilaDateTime } from '../utils/manilaTime'

/**
 * Simple timeline of internship status changes.
 * apiBase: 'coordinator' | 'director'
 */
function StatusHistoryModal({ internshipId, studentName, apiBase = 'coordinator', onClose }) {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [internship, setInternship] = useState(null)
  const [history, setHistory] = useState([])

  useEffect(() => {
    setLoading(true)
    setError(null)
    api.get(`/${apiBase}/internships/${internshipId}/status-history`)
      .then((res) => {
        setInternship(res.data.internship ?? null)
        setHistory(res.data.data ?? [])
      })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load status history.'))
      .finally(() => setLoading(false))
  }, [internshipId, apiBase])

  return (
    <AppModal
      onClose={onClose}
      size="lg"
      title={`Status History — ${studentName || internship?.student_name || 'Intern'}`}
      icon="fa-clock-rotate-left"
      closeOnBackdrop
      footer={<button type="button" className="btn btn-secondary" onClick={onClose}>Close</button>}
    >
      {loading && (
        <div className="text-center py-4">
          <InternTrackLoader />
        </div>
      )}
      {error && <div className="alert alert-danger">{error}</div>}
      {!loading && !error && (
        <>
          {internship && (
            <p className="text-muted small mb-3">
              Current: <strong>{internship.status_label || internship.status}</strong>
              {internship.company_name ? ` · ${internship.company_name}` : ''}
              {internship.status_reason ? ` — ${internship.status_reason}` : ''}
            </p>
          )}
          {history.length === 0 ? (
            <div className="it-modal__empty">No status changes recorded yet.</div>
          ) : (
            <ul className="list-group list-group-flush">
              {history.map((h) => (
                <li key={h.id} className="list-group-item px-0">
                  <div className="d-flex flex-wrap justify-content-between gap-2">
                    <div className="min-w-0" style={{ overflowWrap: 'anywhere' }}>
                      <div className="fw-semibold">
                        {(h.from_label || h.from_status || '—')}
                        {' → '}
                        {(h.to_label || h.to_status || '—')}
                      </div>
                      {h.reason && <div className="small text-muted mt-1">{h.reason}</div>}
                      {h.changed_by && (
                        <div className="small text-muted">By {h.changed_by}</div>
                      )}
                    </div>
                    <div className="text-muted small text-nowrap">
                      {formatManilaDateTime(h.changed_at)}
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </>
      )}
    </AppModal>
  )
}

export default StatusHistoryModal

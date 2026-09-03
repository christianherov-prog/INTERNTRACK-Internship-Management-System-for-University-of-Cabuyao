import { useEffect, useState } from 'react'
import api from '../services/api'

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
    <div className="modal show d-block" style={{ background: 'rgba(0,0,0,0.45)' }}>
      <div className="modal-dialog modal-dialog-centered modal-lg">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">
              Status History — {studentName || internship?.student_name || 'Intern'}
            </h5>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            {loading && (
              <div className="text-center py-4">
                <i className="fa fa-spinner fa-spin fa-2x text-muted"></i>
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
                  <p className="text-muted text-center py-3">No status changes recorded yet.</p>
                ) : (
                  <ul className="list-group list-group-flush">
                    {history.map((h) => (
                      <li key={h.id} className="list-group-item px-0">
                        <div className="d-flex justify-content-between gap-3">
                          <div>
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
                            {h.changed_at ? new Date(h.changed_at).toLocaleString() : '—'}
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </>
            )}
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>Close</button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default StatusHistoryModal

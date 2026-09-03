import { useState, useEffect } from 'react'
import api from '../services/api'

const SCOPE_STATUSES = [
  { value: 'active', label: 'Active' },
  { value: 'completed', label: 'Completed' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'deferred', label: 'Deferred' },
  { value: 'expelled', label: 'Expelled' },
]

function formatChangedAt(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

/**
 * Director/Coordinator: change internship status with mandatory reason.
 * Also shows the existing status-history timeline from the API.
 * apiBase: 'coordinator' | 'director'
 */
function StatusChangeModal({ internshipId, studentName, currentStatus, apiBase = 'coordinator', onClose, onSaved }) {
  const [status, setStatus] = useState(
    currentStatus === 'ongoing' ? 'active' : (SCOPE_STATUSES.some(s => s.value === currentStatus) ? currentStatus : 'active')
  )
  const [reason, setReason] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [history, setHistory] = useState([])
  const [historyLoading, setHistoryLoading] = useState(true)
  const [historyError, setHistoryError] = useState(null)

  const loadHistory = async () => {
    setHistoryLoading(true)
    setHistoryError(null)
    try {
      const res = await api.get(`/${apiBase}/internships/${internshipId}/status-history`)
      setHistory(Array.isArray(res.data?.data) ? res.data.data : [])
    } catch {
      setHistoryError('Could not load status history.')
      setHistory([])
    } finally {
      setHistoryLoading(false)
    }
  }

  useEffect(() => {
    if (!internshipId) return undefined
    loadHistory()
    return undefined
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [internshipId, apiBase])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (reason.trim().length < 5) {
      setError('A reason of at least 5 characters is required for every status change.')
      return
    }
    setSaving(true)
    setError(null)
    try {
      const res = await api.patch(`/${apiBase}/internships/${internshipId}/status`, {
        status,
        reason: reason.trim(),
      })
      await loadHistory()
      onSaved?.(res.data.internship)
    } catch (err) {
      const msg = err.response?.data?.message
        || (err.response?.data?.errors?.reason?.[0])
        || 'Failed to update status.'
      setError(msg)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Change Internship Status</h5>
              <button type="button" className="btn-close" onClick={onClose} disabled={saving}></button>
            </div>
            <div className="modal-body">
              <p className="mb-3 text-muted" style={{ fontSize: '0.9rem' }}>
                Student: <strong>{studentName}</strong>
                {currentStatus ? <> · Current: <strong>{String(currentStatus).replace(/_/g, ' ')}</strong></> : null}
              </p>

              <div className="mb-4">
                <div className="fw-semibold mb-2">Status history</div>
                {historyLoading && <div className="text-muted small">Loading timeline…</div>}
                {!historyLoading && historyError && <div className="alert alert-warning py-2 mb-0">{historyError}</div>}
                {!historyLoading && !historyError && history.length === 0 && (
                  <div className="text-muted small">No status changes recorded yet.</div>
                )}
                {!historyLoading && !historyError && history.length > 0 && (
                  <ul className="list-unstyled mb-0 border rounded px-3 py-2" style={{ maxHeight: 220, overflowY: 'auto' }}>
                    {history.map((h) => (
                      <li key={h.id} className="py-2 border-bottom border-light" style={{ fontSize: '0.875rem' }}>
                        <div className="d-flex flex-wrap justify-content-between gap-2">
                          <span>
                            <strong>{h.from_label || h.from_status || '—'}</strong>
                            {' → '}
                            <strong>{h.to_label || h.to_status}</strong>
                          </span>
                          <span className="text-muted">{formatChangedAt(h.changed_at)}</span>
                        </div>
                        <div className="text-muted">{h.changed_by || '—'}</div>
                        {h.reason ? <div className="mt-1">{h.reason}</div> : null}
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              {error && <div className="alert alert-danger py-2">{error}</div>}
              <div className="mb-3">
                <label className="form-label fw-semibold">New status <span className="text-danger">*</span></label>
                <select className="form-select" value={status} onChange={e => setStatus(e.target.value)} required>
                  {SCOPE_STATUSES.map(s => (
                    <option key={s.value} value={s.value}>{s.label}</option>
                  ))}
                </select>
              </div>
              <div className="mb-1">
                <label className="form-label fw-semibold">Reason for change <span className="text-danger">*</span></label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={reason}
                  onChange={e => setReason(e.target.value)}
                  placeholder="Required — explain why this status is being applied…"
                  required
                  minLength={5}
                />
                <div className="form-text">Minimum 5 characters. Recorded in status history.</div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose} disabled={saving}>Cancel</button>
              <button type="submit" className="btn btn-primary" disabled={saving || reason.trim().length < 5}>
                {saving ? 'Saving…' : 'Update Status'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default StatusChangeModal

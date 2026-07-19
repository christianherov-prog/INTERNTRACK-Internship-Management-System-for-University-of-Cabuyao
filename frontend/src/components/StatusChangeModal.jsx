import { useState } from 'react'
import api from '../services/api'

const SCOPE_STATUSES = [
  { value: 'active', label: 'Active' },
  { value: 'completed', label: 'Completed' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'deferred', label: 'Deferred' },
  { value: 'expelled', label: 'Expelled' },
]

/**
 * Director/Coordinator: change internship status with mandatory reason.
 * apiBase: 'coordinator' | 'director'
 */
function StatusChangeModal({ internshipId, studentName, currentStatus, apiBase = 'coordinator', onClose, onSaved }) {
  const [status, setStatus] = useState(
    currentStatus === 'ongoing' ? 'active' : (SCOPE_STATUSES.some(s => s.value === currentStatus) ? currentStatus : 'active')
  )
  const [reason, setReason] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

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
      <div className="modal-dialog modal-dialog-centered">
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

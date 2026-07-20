import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'

function SupervisorAttendanceValidation() {
  const [attendance, setAttendance] = useState([])
  const [loading, setLoading]       = useState(true)
  const [processing, setProcessing] = useState(null) // row id, or 'bulk'
  const [message, setMessage]       = useState(null)
  const [rejectModal, setRejectModal] = useState(null) // { id, date, studentName } | { bulk: true }
  const [remark, setRemark]         = useState('')
  const [selected, setSelected]     = useState([])

  const fetchAttendance = () => {
    setLoading(true)
    api.get('/supervisor/attendance')
      .then(res => setAttendance(unwrapList(res.data).items))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAttendance() }, [])

  // Only fully clocked-out records are eligible for validation/selection.
  const selectable = attendance.filter(a => a.clock_out)
  const allSelected = selectable.length > 0 && selected.length === selectable.length

  const toggleAll = () => setSelected(allSelected ? [] : selectable.map(a => a.id))
  const toggleOne = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])

  const validate = async (id, action, remarks = '') => {
    setProcessing(id)
    try {
      await api.patch(`/supervisor/attendance/${id}/validate`, { action, remarks })
      setMessage({ type: action === 'validated' ? 'success' : 'warning', text: `Attendance ${action} successfully.` })
      setRejectModal(null)
      setSelected(prev => prev.filter(x => x !== id))
      fetchAttendance()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Action failed.' })
    } finally { setProcessing(null) }
  }

  const bulkValidate = async (action, remarks = '') => {
    if (selected.length === 0) return
    setProcessing('bulk')
    try {
      const res = await api.patch('/supervisor/attendance/bulk-validate', { ids: selected, action, remarks })
      setMessage({ type: action === 'validated' ? 'success' : 'warning', text: res.data.message ?? `${selected.length} record(s) ${action}.` })
      setRejectModal(null)
      setSelected([])
      fetchAttendance()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Bulk action failed.' })
    } finally { setProcessing(null) }
  }

  const openBulkRejectModal = () => { setRejectModal({ bulk: true }); setRemark('') }

  return (
    <Layout title="Attendance Validation" subtitle={CURRENT_TERM} icon="fa-user-check" bodyClass="supervisor-page">
      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

      {/* Reject Modal (single or bulk) */}
      {rejectModal && (
        <div className="modal show d-block" tabIndex="-1" style={{background:'rgba(0,0,0,0.4)'}}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">{rejectModal.bulk ? `Reject ${selected.length} Record(s)` : 'Reject Attendance'}</h5>
                <button className="btn-close" onClick={() => setRejectModal(null)}></button>
              </div>
              <div className="modal-body">
                {!rejectModal.bulk && (
                  <p className="text-muted mb-2" style={{fontSize:'0.88rem'}}>Date: <strong>{rejectModal.date}</strong> · Student: <strong>{rejectModal.studentName}</strong></p>
                )}
                <label className="form-label fw-semibold">Reason for Rejection</label>
                <textarea className="form-control" rows={3} value={remark} onChange={e => setRemark(e.target.value)} placeholder="Provide a reason…"></textarea>
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setRejectModal(null)}>Cancel</button>
                <button
                  className="btn btn-danger"
                  onClick={() => rejectModal.bulk ? bulkValidate('rejected', remark) : validate(rejectModal.id, 'rejected', remark)}
                  disabled={processing === (rejectModal.bulk ? 'bulk' : rejectModal.id)}
                >
                  <i className="fa fa-times me-2"></i>Reject {rejectModal.bulk ? 'Selected' : ''}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-clock"></i><h6>Pending Attendance Records</h6>
          <span className="ms-auto badge bg-warning text-dark">{attendance.length} pending</span>
        </div>

        {/* Bulk Action Bar */}
        {selected.length > 0 && (
          <div className="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style={{ background: '#f0f9ff' }}>
            <span className="fw-semibold" style={{ fontSize: '0.85rem' }}>{selected.length} selected</span>
            <button className="btn btn-sm btn-success ms-auto" onClick={() => bulkValidate('validated')} disabled={processing === 'bulk'}>
              <i className={`fa fa-${processing === 'bulk' ? 'spinner fa-spin' : 'check'} me-1`}></i>Validate Selected
            </button>
            <button className="btn btn-sm btn-danger" onClick={openBulkRejectModal} disabled={processing === 'bulk'}>
              <i className="fa fa-times me-1"></i>Reject Selected
            </button>
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setSelected([])}>Clear</button>
          </div>
        )}

        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : attendance.length === 0 ? (
            <div className="text-center py-4 text-muted"><i className="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>All attendance records are validated!</div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style={{ width: '36px' }}>
                      <input type="checkbox" className="form-check-input" checked={allSelected} onChange={toggleAll} aria-label="Select all" />
                    </th>
                    <th>Student</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {attendance.map(log => {
                    const profile = log.internship?.student?.studentProfile
                    const name = profile ? `${profile.first_name} ${profile.last_name}` : '—'
                    return (
                      <tr key={log.id} className={selected.includes(log.id) ? 'table-active' : ''}>
                        <td>
                          <input
                            type="checkbox"
                            className="form-check-input"
                            checked={selected.includes(log.id)}
                            onChange={() => toggleOne(log.id)}
                            disabled={!log.clock_out}
                            aria-label={`Select ${name}`}
                          />
                        </td>
                        <td className="fw-semibold">{name}</td>
                        <td>{new Date(log.date).toLocaleDateString('en-PH', {weekday:'short',month:'short',day:'numeric'})}</td>
                        <td>{log.clock_in ?? '—'}</td>
                        <td>{log.clock_out ?? <span className="badge bg-warning text-dark">Still In</span>}</td>
                        <td>{log.hours_rendered ? `${log.hours_rendered} hrs` : '—'}</td>
                        <td className="text-center">
                          <button className="btn btn-sm btn-success me-2" onClick={() => validate(log.id, 'validated')} disabled={processing === log.id || processing === 'bulk' || !log.clock_out}>
                            <i className="fa fa-check me-1"></i>Validate
                          </button>
                          <button className="btn btn-sm btn-danger" onClick={() => { setRejectModal({ id: log.id, date: log.date, studentName: name }); setRemark('') }} disabled={processing === log.id || processing === 'bulk'}>
                            <i className="fa fa-times me-1"></i>Reject
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default SupervisorAttendanceValidation

import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { formatStudentName } from '../../utils/formatName'

function fmtTime(t) {
  if (!t) return '—'
  return String(t).slice(0, 5)
}

function SupervisorAttendanceValidation() {
  const currentTerm = useCurrentTerm()
  const [attendance, setAttendance] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(null)
  const [message, setMessage] = useState(null)
  const [selected, setSelected] = useState([])
  const [rejectModal, setRejectModal] = useState(null)
  const [remark, setRemark] = useState('')
  const [search, setSearch] = useState('')

  const fetchAttendance = () => {
    setLoading(true)
    setError(null)
    api.get('/supervisor/attendance')
      .then((res) => setAttendance(unwrapList(res.data).items))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load attendance.')
        setAttendance([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAttendance() }, [])

  const selectable = attendance.filter((a) => a.clock_out)
  const allSelected = selectable.length > 0 && selected.length === selectable.length
  const toggleAll = () => setSelected(allSelected ? [] : selectable.map((a) => a.id))
  const toggleOne = (id) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))

  const validate = async (id, action, remarks = '') => {
    setProcessing(id)
    try {
      await api.patch(`/supervisor/attendance/${id}/validate`, { action, remarks })
      setMessage({ type: action === 'validated' ? 'success' : 'warning', text: `Attendance ${action} successfully.` })
      setRejectModal(null)
      setSelected((prev) => prev.filter((x) => x !== id))
      fetchAttendance()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Action failed.' })
    } finally {
      setProcessing(null)
    }
  }

  const bulkValidate = async (action, remarks = '') => {
    if (selected.length === 0) return
    setProcessing('bulk')
    try {
      const res = await api.patch('/supervisor/attendance/bulk-validate', { ids: selected, action, remarks })
      setMessage({ type: action === 'validated' ? 'success' : 'warning', text: res.data.message })
      setRejectModal(null)
      setSelected([])
      fetchAttendance()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Bulk action failed.' })
    } finally {
      setProcessing(null)
    }
  }

  const profileName = (log) => formatStudentName(log.internship)

  return (
    <Layout title="Attendance Validation" subtitle={currentTerm} icon="fa-user-check" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={fetchAttendance} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {rejectModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.4)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">{rejectModal.bulk ? `Reject ${selected.length} Record(s)` : 'Reject Attendance'}</h5>
                <button type="button" className="btn-close" onClick={() => setRejectModal(null)}></button>
              </div>
              <div className="modal-body">
                {!rejectModal.bulk && (
                  <p className="text-muted mb-2" style={{ fontSize: '0.88rem' }}>
                    Date: <strong>{rejectModal.date}</strong> · Student: <strong>{rejectModal.studentName}</strong>
                  </p>
                )}
                <label className="form-label fw-semibold">Reason for Rejection</label>
                <textarea className="form-control" rows={3} value={remark} onChange={(e) => setRemark(e.target.value)} placeholder="Provide a reason…" />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setRejectModal(null)}>Cancel</button>
                <button
                  type="button"
                  className="btn btn-danger"
                  onClick={() => (rejectModal.bulk ? bulkValidate('rejected', remark) : validate(rejectModal.id, 'rejected', remark))}
                  disabled={processing === (rejectModal.bulk ? 'bulk' : rejectModal.id)}
                >
                  <i className="fa fa-times me-2"></i>Reject {rejectModal.bulk ? 'Selected' : ''}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search by student name…" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-clock"></i>
          <h6>Pending Attendance Records</h6>
          <span className="ms-auto badge bg-warning text-dark">{attendance.length} pending</span>
        </div>

        {selected.length > 0 && (
          <div className="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style={{ background: '#f0f9ff' }}>
            <span className="fw-semibold" style={{ fontSize: '0.85rem' }}>{selected.length} selected</span>
            <button type="button" className="btn btn-sm btn-success ms-auto" onClick={() => bulkValidate('validated')} disabled={processing === 'bulk'}>
              <i className="fa fa-check me-1"></i>Validate Selected
            </button>
            <button type="button" className="btn btn-sm btn-danger" onClick={() => { setRejectModal({ bulk: true }); setRemark('') }} disabled={processing === 'bulk'}>
              <i className="fa fa-times me-1"></i>Reject Selected
            </button>
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setSelected([])}>Clear</button>
          </div>
        )}

        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : attendance.length === 0 && !error ? (
            <EmptyState icon="fa-check-circle" title="No pending attendance" message="All clock records for your interns are validated." />
          ) : (() => {
            const filtered = attendance.filter(a => {
              if (!search) return true
              return profileName(a).toLowerCase().includes(search.toLowerCase())
            })

            if (attendance.length > 0 && filtered.length === 0) {
              return <div className="text-center py-4 text-muted">No attendance matches your search.</div>
            }
            if (filtered.length === 0) return null

            return (
              <div className="table-responsive">
              <table className="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th style={{ width: 36 }}>
                      <input type="checkbox" className="form-check-input" checked={allSelected} onChange={toggleAll} aria-label="Select all" />
                    </th>
                    <th>Student</th>
                    <th>Date</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Hours</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((log) => {
                    const name = profileName(log)
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
                        <td>{new Date(log.date).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' })}</td>
                        <td>{fmtTime(log.clock_in)}</td>
                        <td>{log.clock_out ? fmtTime(log.clock_out) : <span className="badge bg-warning text-dark">Still In</span>}</td>
                        <td>{log.hours_rendered != null ? `${log.hours_rendered} hrs` : '—'}</td>
                        <td className="text-center">
                          <button
                            type="button"
                            className="btn btn-sm btn-success me-2"
                            disabled={processing === log.id || processing === 'bulk' || !log.clock_out}
                            onClick={() => validate(log.id, 'validated')}
                          >
                            <i className="fa fa-check me-1"></i>Validate
                          </button>
                          <button
                            type="button"
                            className="btn btn-sm btn-danger"
                            disabled={processing === log.id || processing === 'bulk'}
                            onClick={() => { setRejectModal({ id: log.id, date: log.date, studentName: name }); setRemark('') }}
                          >
                            <i className="fa fa-times me-1"></i>Reject
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            )
          })()}
        </div>
      </div>
    </Layout>
  )
}

export default SupervisorAttendanceValidation

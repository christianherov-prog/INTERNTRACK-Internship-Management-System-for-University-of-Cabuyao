import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { formatStudentName } from '../../utils/formatName'
import { useCachedPage } from '../../hooks/useCachedPage'
import { invalidateStudentPortfolio } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'
import { useConfirm } from '../../contexts/ConfirmContext'
import AsyncButton from '../../components/AsyncButton'
import { formatDisplayDate, formatClock12 } from '../../utils/manilaTime'
import AppModal from '../../components/modals/AppModal'

// Manila wall-clock values (already resolved by the API) in 12-hour form.
const fmtTime = (t) => formatClock12(t)

function SupervisorAttendanceValidation() {
  const confirm = useConfirm()
  const currentTerm = useCurrentTerm()
  const { loading, seed, run } = useCachedPage('supervisor:attendance')
  const [attendance, setAttendance] = useState(() => seed?.attendance ?? [])
  const [schedules, setSchedules] = useState(() => seed?.schedules ?? [])
  const [overtime, setOvertime] = useState(() => seed?.overtime ?? [])
  const [corrections, setCorrections] = useState(() => seed?.corrections ?? [])
  const [history, setHistory] = useState(() => seed?.history ?? [])
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(null)
  const [message, setMessage] = useState(null)
  const [selected, setSelected] = useState([])
  const [rejectModal, setRejectModal] = useState(null)
  const [remark, setRemark] = useState('')
  const [search, setSearch] = useState('')

  const fetchAttendance = () => {
    setError(null)
    run(() => Promise.all([
      api.get('/supervisor/attendance'),
      api.get('/supervisor/dtr/schedules').catch(() => ({ data: { data: [] } })),
      api.get('/supervisor/dtr/overtime').catch(() => ({ data: { data: [] } })),
      api.get('/supervisor/dtr/corrections').catch(() => ({ data: { data: [] } })),
      api.get('/supervisor/dtr/history').catch(() => ({ data: { data: [] } })),
    ]).then(([attRes, schedRes, otRes, corrRes, histRes]) => ({
      attendance: unwrapList(attRes.data).items,
      schedules: unwrapList(schedRes.data).items,
      overtime: unwrapList(otRes.data).items,
      corrections: unwrapList(corrRes.data).items,
      history: unwrapList(histRes.data).items,
    })))
      .then((next) => {
        if (next) {
          setAttendance(next.attendance)
          setSchedules(next.schedules)
          setOvertime(next.overtime)
          setCorrections(next.corrections)
          setHistory(next.history)
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load attendance.')
        setAttendance([])
      })
  }

  useEffect(() => { fetchAttendance() }, [])

  const selectable = attendance.filter((a) => a.clock_out)
  const allSelected = selectable.length > 0 && selected.length === selectable.length
  const toggleAll = () => setSelected(allSelected ? [] : selectable.map((a) => a.id))
  const toggleOne = (id) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))

  const validate = async (log, action, remarks = '') => {
    const id = typeof log === 'object' ? log.id : log
    const studentName = typeof log === 'object' ? (profileName(log) || 'this student') : 'this student'
    const dateLabel = typeof log === 'object' ? formatDisplayDate(log.date) : ''
    const isValidate = action === 'validated'
    const proceed = isValidate
      ? await confirm({
          title: 'Validate attendance?',
          message: `Validate attendance for ${studentName}${dateLabel ? ` on ${dateLabel}` : ''}?`,
          confirmLabel: 'Validate',
          variant: 'primary',
        })
      : true
    if (!proceed) return

    setProcessing(id)
    try {
      await api.patch(`/supervisor/attendance/${id}/validate`, { action, remarks })
      invalidateStudentPortfolio()
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
    if (action === 'validated') {
      const ok = await confirm({
        title: 'Validate selected attendance?',
        message: `Validate ${selected.length} selected attendance record(s)?`,
        confirmLabel: 'Validate Selected',
        variant: 'primary',
      })
      if (!ok) return
    }
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

  const reviewDtr = async (path, row, action, remarks = '') => {
    const id = row.id
    const student = formatStudentName(row.internship) || 'this student'
    const kind =
      path === 'schedules' ? 'working-hours schedule'
        : path === 'overtime' ? 'overtime entry'
          : 'attendance correction'
    const verb = action === 'approved' ? 'Approve' : 'Reject'
    const detail =
      path === 'schedules' ? `${fmtTime(row.start_time)}–${fmtTime(row.end_time)}`
        : path === 'overtime' ? formatDisplayDate(row.date)
          : formatDisplayDate(row.date)
    await confirm({
      title: `${verb} ${kind}?`,
      message: `${verb} the ${kind} for ${student}${detail ? ` (${detail})` : ''}?`,
      confirmLabel: verb,
      variant: action === 'approved' ? 'primary' : 'danger',
      run: async () => {
        setProcessing(`${path}-${id}`)
        try {
          const res = await api.patch(`/supervisor/dtr/${path}/${id}`, { action, remarks })
          setMessage({ type: action === 'approved' || action === 'validated' ? 'success' : 'warning', text: res.data.message })
          fetchAttendance()
        } catch (err) {
          setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Action failed.' })
          throw err
        } finally {
          setProcessing(null)
        }
      },
    })
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

      <AppModal
        open={!!rejectModal}
        onClose={() => setRejectModal(null)}
        size="md"
        title={rejectModal?.bulk ? `Reject ${selected.length} Record(s)` : 'Reject Attendance'}
        icon="fa-circle-xmark"
        busy={!!rejectModal && processing === (rejectModal.bulk ? 'bulk' : rejectModal.id)}
        footer={rejectModal && (
          <>
            <button type="button" className="btn btn-secondary" onClick={() => setRejectModal(null)}>Cancel</button>
            <button
              type="button"
              className="btn btn-danger"
              onClick={() => (rejectModal.bulk ? bulkValidate('rejected', remark) : validate({ id: rejectModal.id, date: rejectModal.date }, 'rejected', remark))}
              disabled={processing === (rejectModal.bulk ? 'bulk' : rejectModal.id)}
            >
              {processing === (rejectModal.bulk ? 'bulk' : rejectModal.id) ? (
                <><i className="fa fa-spinner fa-spin me-2"></i>Rejecting…</>
              ) : (
                <><i className="fa fa-times me-2"></i>Reject {rejectModal.bulk ? 'Selected' : ''}</>
              )}
            </button>
          </>
        )}
      >
        {rejectModal && !rejectModal.bulk && (
          <p className="text-muted mb-2" style={{ fontSize: '0.88rem' }}>
            Date: <strong>{formatDisplayDate(rejectModal.date) || rejectModal.date || '—'}</strong> · Student: <strong>{rejectModal.studentName}</strong>
          </p>
        )}
        <label className="form-label fw-semibold" htmlFor="attendance-reject-reason">Reason for Rejection</label>
        <textarea maxLength={500} id="attendance-reject-reason" className="form-control" rows={3} value={remark} onChange={(e) => setRemark(e.target.value)} placeholder="Reason" />
      </AppModal>

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input maxLength={100} className="form-control border-start-0 ps-0" placeholder="Search Students" value={search} onChange={e => setSearch(e.target.value)} />
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
            <AsyncButton type="button" className="btn btn-sm btn-success ms-auto" busy={processing === 'bulk'} busyLabel="Validating…" onClick={() => bulkValidate('validated')} disabled={processing === 'bulk'}>
              <i className="fa fa-check me-1"></i>Validate Selected
            </AsyncButton>
            <button type="button" className="btn btn-sm btn-danger" onClick={() => { setRejectModal({ bulk: true }); setRemark('') }} disabled={processing === 'bulk'}>
              <i className="fa fa-times me-1"></i>Reject Selected
            </button>
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setSelected([])}>Clear</button>
          </div>
        )}

        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><InternTrackLoader /></div>
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
                        <td>{formatDisplayDate(log.date, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) || '—'}</td>
                        <td>{fmtTime(log.clock_in_display || log.clock_in)}</td>
                        <td>{(log.clock_out_display || log.clock_out) ? fmtTime(log.clock_out_display || log.clock_out) : <span className="badge bg-warning text-dark">Still In</span>}</td>
                        <td>{log.hours_rendered != null ? `${log.hours_rendered} hrs` : '—'}</td>
                        <td className="text-center">
                          <AsyncButton
                            type="button"
                            className="btn btn-sm btn-success me-2"
                            busy={processing === log.id}
                            busyLabel="…"
                            disabled={processing === log.id || processing === 'bulk' || !log.clock_out}
                            onClick={() => validate(log, 'validated')}
                          >
                            <i className="fa fa-check me-1"></i>Validate
                          </AsyncButton>
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

      {schedules.length > 0 && (
        <div className="content-card mt-4">
          <div className="content-card-header">
            <i className="fa fa-calendar-week"></i>
            <h6>Pending Working Hours Schedules</h6>
          </div>
          <div className="table-responsive">
            <table className="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>Student</th><th>Proposed hours</th><th className="text-center">Actions</th></tr>
              </thead>
              <tbody>
                {schedules.map((s) => (
                  <tr key={s.id}>
                    <td className="fw-semibold">{formatStudentName(s.internship)}</td>
                    <td>{fmtTime(s.start_time)}–{fmtTime(s.end_time)}</td>
                    <td className="text-center">
                      <AsyncButton type="button" className="btn btn-sm btn-success me-2" busy={processing === `schedules-${s.id}`} busyLabel="…" onClick={() => reviewDtr('schedules', s, 'approved')}>Approve</AsyncButton>
                      <AsyncButton type="button" className="btn btn-sm btn-danger" busy={processing === `schedules-${s.id}`} busyLabel="…" onClick={() => reviewDtr('schedules', s, 'rejected')}>Reject</AsyncButton>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {overtime.length > 0 && (
        <div className="content-card mt-4">
          <div className="content-card-header">
            <i className="fa fa-hourglass-half"></i>
            <h6>Pending Overtime Entries</h6>
          </div>
          <div className="table-responsive">
            <table className="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>Student</th><th>Date</th><th>Excess</th><th>Original hours</th><th className="text-center">Actions</th></tr>
              </thead>
              <tbody>
                {overtime.map((o) => (
                  <tr key={o.id}>
                    <td className="fw-semibold">{formatStudentName(o.internship)}</td>
                    <td>{formatDisplayDate(o.attendance_log?.date || o.date) || '—'}</td>
                    <td>{o.excess_minutes} min</td>
                    <td>{o.original_hours_rendered ?? '—'} hrs</td>
                    <td className="text-center">
                      <AsyncButton type="button" className="btn btn-sm btn-success me-2" busy={processing === `overtime-${o.id}`} busyLabel="…" onClick={() => reviewDtr('overtime', o, 'approved')}>Approve</AsyncButton>
                      <AsyncButton type="button" className="btn btn-sm btn-danger" busy={processing === `overtime-${o.id}`} busyLabel="…" onClick={() => reviewDtr('overtime', o, 'rejected')}>Reject</AsyncButton>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {corrections.length > 0 && (
        <div className="content-card mt-4">
          <div className="content-card-header">
            <i className="fa fa-clipboard-list"></i>
            <h6>Correction Requests (Supervisor Review)</h6>
          </div>
          <div className="table-responsive">
            <table className="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>Student</th><th>Date</th><th>Original</th><th>Requested</th><th>Status</th><th className="text-center">Actions</th></tr>
              </thead>
              <tbody>
                {corrections.map((c) => (
                  <tr key={c.id}>
                    <td className="fw-semibold">{c.student_name || formatStudentName(c.internship)}</td>
                    <td>{formatDisplayDate(c.date) || '—'}</td>
                    <td>{fmtTime(c.original_clock_in_display)}–{fmtTime(c.original_clock_out_display)}</td>
                    <td>{fmtTime(c.requested_clock_in_display)}–{fmtTime(c.requested_clock_out_display)}</td>
                    <td>{c.status_label || c.status}</td>
                    <td className="text-center">
                      <AsyncButton type="button" className="btn btn-sm btn-success me-2" busy={processing === `corrections-${c.id}`} busyLabel="…" onClick={() => reviewDtr('corrections', c, 'approved')}>Approve</AsyncButton>
                      <AsyncButton type="button" className="btn btn-sm btn-danger" busy={processing === `corrections-${c.id}`} busyLabel="…" onClick={() => reviewDtr('corrections', c, 'rejected')}>Reject</AsyncButton>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <div className="content-card mt-4">
        <div className="content-card-header">
          <i className="fa fa-history"></i>
          <h6>Clock-in / Clock-out History</h6>
        </div>
        <div className="table-card">
          {history.length === 0 ? (
            <div className="text-center py-4 text-muted">No attendance history yet.</div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Date</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Hours</th>
                    <th>DTR status</th>
                    <th>Correction</th>
                    <th>Overtime</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map((log) => (
                    <tr key={log.id}>
                      <td className="fw-semibold">{profileName(log)}</td>
                      <td>{formatDisplayDate(log.date) || '—'}</td>
                      <td>{fmtTime(log.clock_in_display || log.clock_in)}</td>
                      <td>{fmtTime(log.clock_out_display || log.clock_out)}</td>
                      <td>{log.hours_rendered != null ? `${log.hours_rendered} hrs` : '—'}</td>
                      <td>{log.status}</td>
                      <td>{log.correction_status_label || '—'}</td>
                      <td>{log.overtime_status || 'none'}</td>
                    </tr>
                  ))}
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

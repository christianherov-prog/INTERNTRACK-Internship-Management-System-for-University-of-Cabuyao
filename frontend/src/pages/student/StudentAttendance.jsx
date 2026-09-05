import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { useConfirm } from '../../contexts/ConfirmContext'

function fmtTime(t) {
  if (!t) return '—'
  return String(t).slice(0, 5)
}

function fmtHours(value) {
  if (value == null || value === '') return '—'
  const n = Number(value)
  return Number.isNaN(n) ? '—' : `${n} hrs`
}

function overtimeLabel(status) {
  if (status === 'pending') return 'Pending'
  if (status === 'approved') return 'Approved'
  if (status === 'rejected') return 'Rejected'
  return 'None'
}

function StudentAttendance({ embedded = false }) {
  const currentTerm = useCurrentTerm()
  const confirm = useConfirm()
  const [data, setData] = useState(null)
  const [corrections, setCorrections] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [clocking, setClocking] = useState(false)
  const [message, setMessage] = useState(null)
  const [startTime, setStartTime] = useState('07:00')
  const [endTime, setEndTime] = useState('17:00')
  const [savingSchedule, setSavingSchedule] = useState(false)
  const [correctionForm, setCorrectionForm] = useState(null)
  const [savingCorrection, setSavingCorrection] = useState(false)
  const [nowTick, setNowTick] = useState(Date.now())

  const fetchAttendance = () => {
    setLoading(true)
    setError(null)
    Promise.all([
      api.get('/student/attendance'),
      api.get('/student/attendance/corrections').catch(() => ({ data: { data: [] } })),
    ])
      .then(([attRes, corrRes]) => {
        setData(attRes.data)
        setCorrections(unwrapList(corrRes.data).items)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load attendance.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAttendance() }, [])

  useEffect(() => {
    if (!data?.can_undo_clock_out || !data?.undo_expires_at) return undefined
    const id = window.setInterval(() => setNowTick(Date.now()), 15000)
    return () => window.clearInterval(id)
  }, [data?.can_undo_clock_out, data?.undo_expires_at])

  const submitOvertimeDecision = async (logId, accept) => {
    try {
      await api.post('/student/attendance/overtime-decision', {
        attendance_log_id: logId,
        accept,
      })
      setMessage({
        type: accept ? 'success' : 'info',
        text: accept
          ? 'Overtime submitted for supervisor approval.'
          : 'Excess time discarded. It was not added to your DTR.',
      })
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Could not save overtime decision.' })
    }
  }

  const handleClockIn = async () => {
    setClocking(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/clock-in')
      setMessage({ type: 'success', text: 'Clocked in successfully!' })
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-in failed.' })
    } finally {
      setClocking(false)
    }
  }

  const handleClockOut = async () => {
    const ok = await confirm({
      title: 'Clock out?',
      message: 'Are you sure you want to clock out?',
      confirmLabel: 'Clock Out',
      cancelLabel: 'Cancel',
      variant: 'danger',
    })
    if (!ok) return

    setClocking(true)
    setMessage(null)
    try {
      const res = await api.post('/student/attendance/clock-out')
      setMessage({ type: 'success', text: 'Clocked out successfully!' })
      if (res.data?.overtime_detected) {
        const yes = await confirm({
          title: 'Is this overtime?',
          message: `You stayed ${res.data.excess_minutes} minute(s) past your scheduled end. Submit this excess as overtime for supervisor approval? Choosing No discards it.`,
          confirmLabel: 'Yes',
          cancelLabel: 'No',
          variant: 'primary',
        })
        await api.post('/student/attendance/overtime-decision', {
          attendance_log_id: res.data.record.id,
          accept: yes,
        })
        setMessage({
          type: yes ? 'success' : 'info',
          text: yes
            ? 'Clocked out. Overtime submitted for supervisor approval.'
            : 'Clocked out. Excess time was discarded.',
        })
      }
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-out failed.' })
    } finally {
      setClocking(false)
    }
  }

  const handleUndo = async () => {
    setClocking(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/undo-clock-out')
      setMessage({ type: 'success', text: 'Clock-out undone. You are clocked in again.' })
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Undo failed.' })
    } finally {
      setClocking(false)
    }
  }

  const handleProposeSchedule = async (e) => {
    e.preventDefault()
    setSavingSchedule(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/schedules', { start_time: startTime, end_time: endTime })
      setMessage({ type: 'success', text: 'Schedule proposal submitted for supervisor approval.' })
      fetchAttendance()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Could not submit schedule.' })
    } finally {
      setSavingSchedule(false)
    }
  }

  const openCorrection = (day) => {
    setCorrectionForm({
      date: day?.date || '',
      requested_clock_in: '08:00',
      requested_clock_out: '17:00',
      reason: '',
    })
  }

  const submitCorrection = async (e) => {
    e.preventDefault()
    setSavingCorrection(true)
    try {
      await api.post('/student/attendance/corrections', correctionForm)
      setMessage({ type: 'success', text: 'Correction request submitted. Supervisor review comes first, then faculty.' })
      setCorrectionForm(null)
      fetchAttendance()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Could not submit correction request.' })
    } finally {
      setSavingCorrection(false)
    }
  }

  const todayStatus = data?.today_status ?? 'not_clocked_in'
  const logs = data?.attendance?.data ?? []
  const uniquePlacements = new Set(logs.map(log => log.placement?.label).filter(Boolean))
  const showPlacementColumn = uniquePlacements.size > 1
  const undoStillOpen = data?.can_undo_clock_out && data?.undo_expires_at && new Date(data.undo_expires_at).getTime() > nowTick
  const incompleteDays = data?.incomplete_dtr_days ?? []

  const statusBadge = (s) => {
    if (s === 'validated') return <span className="badge-status badge-active">Validated</span>
    if (s === 'pending') return <span className="badge-status badge-pending">Pending</span>
    if (s === 'rejected') return <span className="badge-status badge-inactive">Rejected</span>
    return <span className="badge-status">{s}</span>
  }

  const Wrapper = embedded ? 'div' : Layout
  const wrapperProps = embedded ? { className: 'embedded-view' } : { title: 'Attendance & Time Log', subtitle: currentTerm, icon: 'fa-clock', bodyClass: 'student-page' }

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={fetchAttendance} />}

      {incompleteDays.length > 0 && (
        <div className="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3">
          <span><i className="fa fa-exclamation-triangle me-2"></i><strong>Incomplete entry — action needed.</strong> File a correction for missing or incomplete days.</span>
          <button type="button" className="btn btn-sm btn-outline-dark ms-auto" onClick={() => openCorrection(incompleteDays[0])}>Request correction</button>
        </div>
      )}

      <div className="content-card mb-4">
        <div className="content-card-header flex-wrap">
          <i className="fa fa-calendar-week"></i>
          <h6>Working Hours Schedule</h6>
        </div>
        <div className="p-3">
          {data?.active_schedule ? (
            <p className="mb-2">
              <strong>Active Schedule:</strong> {fmtTime(data.active_schedule.start_time)}–{fmtTime(data.active_schedule.end_time)}
              {data.active_schedule.effective_from && (
                <span className="text-muted ms-2" style={{ fontSize: '0.85rem' }}>
                  since {data.active_schedule.effective_from}
                </span>
              )}
            </p>
          ) : (
            <p className="text-muted mb-2">No active schedule yet. Propose your working hours for supervisor approval.</p>
          )}
          {data?.pending_schedule && (
            <div className="alert alert-info py-2 mb-3">
              Pending proposal: {fmtTime(data.pending_schedule.start_time)}–{fmtTime(data.pending_schedule.end_time)}. Your current active schedule stays in effect until this is approved.
            </div>
          )}
          {!data?.pending_schedule && (
            <form className="row g-2 align-items-end" onSubmit={handleProposeSchedule}>
              <div className="col-auto">
                <label className="form-label mb-1" style={{ fontSize: '0.8rem' }}>Start</label>
                <input type="time" className="form-control form-control-sm" value={startTime} onChange={(e) => setStartTime(e.target.value)} required />
              </div>
              <div className="col-auto">
                <label className="form-label mb-1" style={{ fontSize: '0.8rem' }}>End</label>
                <input type="time" className="form-control form-control-sm" value={endTime} onChange={(e) => setEndTime(e.target.value)} required />
              </div>
              <div className="col-auto">
                <button type="submit" className="btn btn-sm btn-primary" disabled={savingSchedule}>
                  {savingSchedule ? 'Submitting…' : (data?.active_schedule ? 'Propose new schedule' : 'Submit proposal')}
                </button>
              </div>
            </form>
          )}
          {Array.isArray(data?.schedule_history) && data.schedule_history.length > 0 && (
            <p className="text-muted mt-3 mb-0" style={{ fontSize: '0.8rem' }}>
              History:{' '}
              {data.schedule_history.map((s) => (
                <span key={s.id} className="me-2">
                  {fmtTime(s.start_time)}–{fmtTime(s.end_time)}
                  {s.effective_from ? ` (${s.effective_from}${s.effective_to ? ` to ${s.effective_to}` : ''})` : ''}
                </span>
              ))}
            </p>
          )}
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header flex-wrap">
          <i className="fa fa-fingerprint"></i>
          <h6>Daily Time Record</h6>
          <span className="ms-auto" style={{ fontSize: '0.85rem', color: 'var(--text-light)', fontWeight: 600, whiteSpace: 'nowrap' }}>
            {new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
          </span>
        </div>
        <div className="p-4 text-center">
          {message && <div className={`alert alert-${message.type} mb-3`}>{message.text}</div>}

          {todayStatus === 'not_clocked_in' && (
            <button type="button" className="btn btn-success px-5 py-2" onClick={handleClockIn} disabled={clocking}>
              <i className="fa fa-play-circle me-2"></i>{clocking ? 'Processing…' : 'Clock In'}
            </button>
          )}
          {todayStatus === 'clocked_in' && (
            <button type="button" className="btn btn-danger px-5 py-2" onClick={handleClockOut} disabled={clocking}>
              <i className="fa fa-stop-circle me-2"></i>{clocking ? 'Processing…' : 'Clock Out'}
            </button>
          )}
          {todayStatus === 'clocked_out' && (
            <div className="text-success">
              <i className="fa fa-check-circle fa-2x mb-2 d-block"></i>
              You have completed today's attendance.
              {undoStillOpen && (
                <div className="mt-3">
                  <button type="button" className="btn btn-outline-secondary btn-sm" onClick={handleUndo} disabled={clocking}>
                    Undo Clock-Out
                  </button>
                  <div className="text-muted mt-1" style={{ fontSize: '0.8rem' }}>Self-service undo is available for a few minutes after clock-out.</div>
                </div>
              )}
              {data?.overtime_prompt && (
                <div className="alert alert-warning text-start mt-3 mb-0">
                  <strong>Is this overtime?</strong>
                  <p className="mb-2 mt-1" style={{ fontSize: '0.88rem' }}>
                    You stayed {data.overtime_prompt.excess_minutes} minute(s) past your scheduled end. Yes sends it to your supervisor. No discards the excess.
                  </p>
                  <button type="button" className="btn btn-sm btn-primary me-2" onClick={() => submitOvertimeDecision(data.overtime_prompt.attendance_log_id, true)}>Yes</button>
                  <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => submitOvertimeDecision(data.overtime_prompt.attendance_log_id, false)}>No</button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-history"></i>
          <h6>Attendance History</h6>
          <button type="button" className="btn btn-sm btn-outline-primary ms-auto" onClick={() => openCorrection(incompleteDays[0] || { date: '' })}>
            Request time correction
          </button>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : logs.length === 0 && !error ? (
            <EmptyState icon="fa-clock" title="No attendance yet" message="Use Clock In when you start your shift." />
          ) : logs.length === 0 ? null : (
            <div className="table-responsive">
              <table className="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    {showPlacementColumn && <th>Placement</th>}
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Scheduled</th>
                    <th>Actual</th>
                    <th>Overtime</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.map((log) => (
                    <tr key={log.id}>
                      <td>{new Date(log.date).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}</td>
                      {showPlacementColumn && (
                        <td>
                          <span className="badge bg-light text-dark border" style={{ fontSize: '0.75rem' }}>
                            {log.placement?.label || 'N/A'}
                          </span>
                        </td>
                      )}
                      <td>{fmtTime(log.clock_in)}</td>
                      <td>{log.clock_out ? fmtTime(log.clock_out) : <span className="badge bg-warning text-dark">Still In</span>}</td>
                      <td>{fmtHours(log.scheduled_hours)}</td>
                      <td>{fmtHours(log.actual_hours ?? log.hours_rendered)}</td>
                      <td>{overtimeLabel(log.overtime_status)}{log.correction_status_label ? <div className="text-muted" style={{ fontSize: '0.75rem' }}>{log.correction_status_label}</div> : null}</td>
                      <td>{statusBadge(log.status)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {corrections.length > 0 && (
        <div className="content-card">
          <div className="content-card-header">
            <i className="fa fa-clipboard-list"></i>
            <h6>Correction Requests</h6>
          </div>
          <div className="table-responsive">
            <table className="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Original</th>
                  <th>Requested</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {corrections.map((c) => (
                  <tr key={c.id}>
                    <td>{c.date}</td>
                    <td>{fmtTime(c.original_clock_in)}–{fmtTime(c.original_clock_out)}</td>
                    <td>{fmtTime(c.requested_clock_in)}–{fmtTime(c.requested_clock_out)}</td>
                    <td>{c.status_label || c.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {correctionForm && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.4)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <form className="modal-content" onSubmit={submitCorrection}>
              <div className="modal-header">
                <h5 className="modal-title">Correction request</h5>
                <button type="button" className="btn-close" onClick={() => setCorrectionForm(null)}></button>
              </div>
              <div className="modal-body">
                <p className="text-muted" style={{ fontSize: '0.85rem' }}>
                  For missing or incomplete days only (within the last 3 days). This does not change the official DTR until supervisor and faculty both approve.
                </p>
                <label className="form-label">Date</label>
                <input type="date" className="form-control mb-2" value={correctionForm.date} onChange={(e) => setCorrectionForm({ ...correctionForm, date: e.target.value })} required />
                <div className="row g-2">
                  <div className="col-6">
                    <label className="form-label">Clock in</label>
                    <input type="time" className="form-control" value={correctionForm.requested_clock_in} onChange={(e) => setCorrectionForm({ ...correctionForm, requested_clock_in: e.target.value })} />
                  </div>
                  <div className="col-6">
                    <label className="form-label">Clock out</label>
                    <input type="time" className="form-control" value={correctionForm.requested_clock_out} onChange={(e) => setCorrectionForm({ ...correctionForm, requested_clock_out: e.target.value })} />
                  </div>
                </div>
                <label className="form-label mt-2">Reason</label>
                <textarea className="form-control" rows={2} value={correctionForm.reason} onChange={(e) => setCorrectionForm({ ...correctionForm, reason: e.target.value })} />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setCorrectionForm(null)}>Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={savingCorrection}>{savingCorrection ? 'Submitting…' : 'Submit request'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </Wrapper>
  )
}

export default StudentAttendance

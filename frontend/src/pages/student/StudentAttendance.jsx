import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { useConfirm } from '../../contexts/ConfirmContext'
import { useCachedPage } from '../../hooks/useCachedPage'
import { cacheDelete, invalidateStudentAttendance } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'
import { formatManilaTime, formatDisplayDate, formatClock12 } from '../../utils/manilaTime'
import AppModal from '../../components/modals/AppModal'

// Manila wall-clock values (already resolved by the API) in 12-hour form.
const fmtTime = (t) => formatClock12(t)

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

const CORRECTION_TYPES = [
  { value: 'clock_in', label: 'Clock In', field: 'requested_clock_in' },
  { value: 'clock_out', label: 'Clock Out', field: 'requested_clock_out' },
  { value: 'break_start', label: 'Break Start', field: 'requested_break_start' },
  { value: 'break_end', label: 'Break End', field: 'requested_break_end' },
]

function StudentAttendance({ embedded = false }) {
  const currentTerm = useCurrentTerm()
  const confirm = useConfirm()
  const { loading, seed, run } = useCachedPage('student:attendance')
  const [data, setData] = useState(() => seed?.data ?? null)
  const [corrections, setCorrections] = useState(() => seed?.corrections ?? [])
  const [error, setError] = useState(null)
  const [clocking, setClocking] = useState(false)
  const [message, setMessage] = useState(null)
  const [startTime, setStartTime] = useState('07:00')
  const [endTime, setEndTime] = useState('17:00')
  const [savingSchedule, setSavingSchedule] = useState(false)
  const [correctionForm, setCorrectionForm] = useState(null)
  const [savingCorrection, setSavingCorrection] = useState(false)
  const [nowTick, setNowTick] = useState(Date.now())
  const [clockOutOpen, setClockOutOpen] = useState(false)
  const [clockOutError, setClockOutError] = useState(null)

  const fetchAttendance = () => {
    setError(null)
    run(async () => {
      const [attRes, corrRes] = await Promise.all([
        api.get('/student/attendance'),
        api.get('/student/attendance/corrections').catch(() => ({ data: { data: [] } })),
      ])
      return {
        data: attRes.data,
        corrections: unwrapList(corrRes.data).items,
      }
    })
      .then((next) => {
        if (next) {
          setData(next.data)
          setCorrections(next.corrections)
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load attendance.')
      })
  }

  useEffect(() => { fetchAttendance() }, [])

  useEffect(() => {
    if (!data?.can_undo_clock_out || !data?.undo_expires_at) return undefined
    const id = window.setInterval(() => setNowTick(Date.now()), 15000)
    return () => window.clearInterval(id)
  }, [data?.can_undo_clock_out, data?.undo_expires_at])

  useEffect(() => {
    if (!clockOutOpen) return undefined
    const id = window.setInterval(() => setNowTick(Date.now()), 1000)
    return () => window.clearInterval(id)
  }, [clockOutOpen])

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
      invalidateStudentAttendance()
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
      invalidateStudentAttendance()
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-in failed.' })
    } finally {
      setClocking(false)
    }
  }

  const handleClockOut = async () => {
    if (clocking) return
    setClockOutOpen(true)
    setClockOutError(null)
  }

  const cancelClockOut = () => {
    if (clocking) return
    setClockOutOpen(false)
    setClockOutError(null)
  }

  const confirmTakeBreak = async () => {
    if (clocking) return
    setClocking(true)
    setClockOutError(null)
    setMessage(null)
    try {
      await api.post('/student/attendance/break-start')
      setClockOutOpen(false)
      setMessage({ type: 'success', text: 'Break started. Resume when you return.' })
      invalidateStudentAttendance()
      fetchAttendance()
    } catch (e) {
      setClockOutError(e.response?.data?.message ?? 'Could not start break.')
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Could not start break.' })
    } finally {
      setClocking(false)
    }
  }

  const confirmEndDay = async () => {
    if (clocking) return
    setClocking(true)
    setClockOutError(null)
    setMessage(null)
    try {
      const res = await api.post('/student/attendance/clock-out', { action: 'end_day' })
      setClockOutOpen(false)
      invalidateStudentAttendance()
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
      setClockOutError(e.response?.data?.message ?? 'Clock-out failed.')
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-out failed.' })
    } finally {
      setClocking(false)
    }
  }

  const handleResumeAttendance = async () => {
    if (clocking) return
    setClocking(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/break-end')
      setMessage({ type: 'success', text: 'Break ended. Attendance resumed.' })
      invalidateStudentAttendance()
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Could not resume attendance.' })
    } finally {
      setClocking(false)
    }
  }

  const handleUndo = async () => {
    setClocking(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/undo-clock-out')
      invalidateStudentAttendance()
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
    if (savingSchedule || data?.pending_schedule) return
    if (!startTime || !endTime) {
      setMessage({ type: 'danger', text: 'Start and end times are required.' })
      return
    }
    if (endTime <= startTime) {
      setMessage({ type: 'danger', text: 'End time must be later than start time.' })
      return
    }
    setSavingSchedule(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/schedules', { start_time: startTime, end_time: endTime })
      setMessage({ type: 'success', text: 'Schedule proposal submitted for supervisor approval.' })
      cacheDelete('student:attendance')
      fetchAttendance()
    } catch (err) {
      const field = err.response?.data?.errors?.end_time?.[0]
        || err.response?.data?.errors?.start_time?.[0]
        || err.response?.data?.errors?.schedule?.[0]
      setMessage({ type: 'danger', text: field || err.response?.data?.message || 'Could not submit schedule.' })
    } finally {
      setSavingSchedule(false)
    }
  }

  const openCorrection = (day) => {
    setCorrectionForm({
      date: day?.date || '',
      correction_type: 'clock_in',
      requested_clock_in: '08:00',
      requested_clock_out: '17:00',
      requested_break_start: '12:00',
      requested_break_end: '13:00',
      reason: '',
    })
  }

  const submitCorrection = async (e) => {
    e.preventDefault()
    setSavingCorrection(true)
    try {
      const typeMeta = CORRECTION_TYPES.find((t) => t.value === correctionForm.correction_type)
      const payload = {
        date: correctionForm.date,
        correction_type: correctionForm.correction_type,
        reason: correctionForm.reason,
        [typeMeta.field]: correctionForm[typeMeta.field],
      }
      await api.post('/student/attendance/corrections', payload)
      setMessage({ type: 'success', text: 'Correction request submitted. Supervisor review comes first, then faculty.' })
      setCorrectionForm(null)
      fetchAttendance()
    } catch (err) {
      const fieldErr = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat()[0]
        : null
      setMessage({ type: 'danger', text: fieldErr || err.response?.data?.message || 'Could not submit correction request.' })
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
  const activeCorrectionType = CORRECTION_TYPES.find((t) => t.value === correctionForm?.correction_type)
  // Authoritative completion comes from the internship status (backend also
  // refuses new attendance with 409); history, schedule and FO-30 stay readable.
  const internshipCompleted = data?.internship_completed === true

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
                  since {formatDisplayDate(data.active_schedule.effective_from, { month: 'short', day: 'numeric', year: 'numeric' })}
                </span>
              )}
            </p>
          ) : internshipCompleted ? (
            <p className="text-muted mb-2">No active schedule on record.</p>
          ) : (
            <p className="text-muted mb-2">No active schedule yet. Propose your working hours for supervisor approval.</p>
          )}
          {!internshipCompleted && data?.pending_schedule && (
            <div className="alert alert-info py-2 mb-3">
              Pending proposal: {fmtTime(data.pending_schedule.start_time)}–{fmtTime(data.pending_schedule.end_time)}. Your current active schedule stays in effect until this is approved.
            </div>
          )}
          {!internshipCompleted && !data?.pending_schedule && (
            <form className="wh-schedule-row" onSubmit={handleProposeSchedule}>
              <div className="wh-schedule-field">
                <label className="form-label mb-1" htmlFor="wh-start" style={{ fontSize: '0.8rem' }}>Start</label>
                <input
                  id="wh-start"
                  type="time"
                  className="form-control form-control-sm"
                  value={startTime}
                  onChange={(e) => setStartTime(e.target.value)}
                  required
                  disabled={savingSchedule}
                />
              </div>
              <div className="wh-schedule-field">
                <label className="form-label mb-1" htmlFor="wh-end" style={{ fontSize: '0.8rem' }}>End</label>
                <input
                  id="wh-end"
                  type="time"
                  className="form-control form-control-sm"
                  value={endTime}
                  onChange={(e) => setEndTime(e.target.value)}
                  required
                  disabled={savingSchedule}
                />
              </div>
              <div className="wh-schedule-field wh-schedule-action">
                <label className="form-label mb-1" style={{ fontSize: '0.8rem' }}>Action</label>
                <button type="submit" className="btn btn-sm btn-primary wh-schedule-submit" disabled={savingSchedule}>
                  {savingSchedule ? 'Submitting…' : (data?.active_schedule ? 'Propose new schedule' : 'Submit Proposal')}
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
                  {s.effective_from ? ` (${formatDisplayDate(s.effective_from, { month: 'short', day: 'numeric', year: 'numeric' })}${s.effective_to ? ` to ${formatDisplayDate(s.effective_to, { month: 'short', day: 'numeric', year: 'numeric' })}` : ''})` : ''}
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
            {(data?.today_date
              ? new Date(`${data.today_date}T12:00:00+08:00`)
              : new Date()
            ).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Manila' })}
          </span>
        </div>
        <div className="p-4 text-center">
          {message && <div className={`alert alert-${message.type} mb-3`}>{message.text}</div>}

          {internshipCompleted ? (
            <div className="attendance-completed-state" role="status">
              <span className="attendance-completed-state__icon" aria-hidden="true">
                <i className="fa fa-flag-checkered"></i>
              </span>
              <h6 className="attendance-completed-state__title">Internship Completed</h6>
              <p className="attendance-completed-state__text mb-2">
                You have completed your required internship hours. New attendance entries are no longer available.
              </p>
              {data?.target_hours > 0 && (
                <span className="attendance-completed-state__hours">
                  {Number(data.hours_rendered ?? 0)} / {Number(data.target_hours)} hours rendered
                </span>
              )}
            </div>
          ) : (
          <>
          {todayStatus === 'not_clocked_in' && (
            <button type="button" className="btn btn-success px-5 py-2" onClick={handleClockIn} disabled={clocking}>
              <i className="fa fa-play-circle me-2"></i>{clocking ? 'Processing…' : 'Clock In'}
            </button>
          )}
          {todayStatus === 'clocked_in' && (
            <button type="button" className="btn btn-danger px-5 py-2" onClick={handleClockOut} disabled={clocking}>
              <i className="fa fa-stop-circle me-2"></i>{clocking && clockOutOpen ? 'Processing…' : 'Clock Out'}
            </button>
          )}
          {todayStatus === 'on_break' && (
            <div>
              <p className="text-warning mb-3"><i className="fa fa-coffee me-2"></i>You are currently on a break.</p>
              <button type="button" className="btn btn-primary px-5 py-2" onClick={handleResumeAttendance} disabled={clocking}>
                <i className="fa fa-play me-2"></i>{clocking ? 'Resuming…' : 'Resume Attendance'}
              </button>
            </div>
          )}
          {todayStatus === 'clocked_out' && (
            <div className="text-success">
              <i className="fa fa-check-circle fa-2x mb-2 d-block"></i>
              You have completed today&apos;s attendance.
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
          </>
          )}
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-history"></i>
          <h6>Attendance History</h6>
          {!internshipCompleted && (
            <button type="button" className="btn btn-sm btn-outline-primary ms-auto" onClick={() => openCorrection(incompleteDays[0] || { date: '' })}>
              Request time correction
            </button>
          )}
        </div>
        <div className="table-card">
          {loading && !data ? (
            <div className="text-center py-4"><InternTrackLoader /></div>
          ) : logs.length === 0 && !error ? (
            <EmptyState icon="fa-clock" title="No attendance yet" message={internshipCompleted ? 'No attendance records were logged for this internship.' : 'Use Clock In when you start your shift.'} />
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
                      <td>{new Date(`${log.date_display || String(log.date).slice(0, 10)}T00:00:00+08:00`).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' })}</td>
                      {showPlacementColumn && (
                        <td>
                          <span className="badge bg-light text-dark border" style={{ fontSize: '0.75rem' }}>
                            {log.placement?.label || 'N/A'}
                          </span>
                        </td>
                      )}
                      <td>{fmtTime(log.clock_in_display || log.clock_in)}</td>
                      <td>{(log.clock_out_display || log.clock_out) ? fmtTime(log.clock_out_display || log.clock_out) : (log.on_break ? <span className="badge bg-info text-dark">On Break</span> : <span className="badge bg-warning text-dark">Still In</span>)}</td>
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
                  <th>Type</th>
                  <th>Original</th>
                  <th>Requested</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {corrections.map((c) => (
                  <tr key={c.id}>
                    <td>{formatDisplayDate(c.date, { month: 'short', day: 'numeric', year: 'numeric' }) || '—'}</td>
                    <td>{c.correction_type || '—'}</td>
                    <td>{fmtTime(c.original_clock_in_display)}–{fmtTime(c.original_clock_out_display)}</td>
                    <td>{fmtTime(c.requested_clock_in_display)}–{fmtTime(c.requested_clock_out_display)}</td>
                    <td>{c.status_label || c.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <AppModal
        open={!!correctionForm}
        onClose={() => setCorrectionForm(null)}
        size="md"
        title="Correction request"
        icon="fa-pen-to-square"
        busy={savingCorrection}
        onSubmit={submitCorrection}
        footer={(
          <>
            <button type="button" className="btn btn-secondary" onClick={() => setCorrectionForm(null)} disabled={savingCorrection}>Cancel</button>
            <button type="submit" className="btn btn-primary" disabled={savingCorrection}>{savingCorrection ? 'Submitting…' : 'Submit request'}</button>
          </>
        )}
      >
        {correctionForm && (
          <>
            <p className="text-muted" style={{ fontSize: '0.85rem' }}>
              Choose one correction type. This does not change the official DTR until supervisor and faculty both approve.
            </p>
            <div className="it-form-grid">
              <div>
                <label className="form-label" htmlFor="correction-date">Date</label>
                <input id="correction-date" type="date" className="form-control" value={correctionForm.date} onChange={(e) => setCorrectionForm({ ...correctionForm, date: e.target.value })} required />
              </div>
              <div>
                <label className="form-label" htmlFor="correction-type">Correction Type</label>
                <select
                  id="correction-type"
                  className="form-select"
                  value={correctionForm.correction_type}
                  onChange={(e) => setCorrectionForm({ ...correctionForm, correction_type: e.target.value })}
                  required
                >
                  {CORRECTION_TYPES.map((t) => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                  ))}
                </select>
              </div>
              {activeCorrectionType && (
                <div>
                  <label className="form-label" htmlFor="correction-time">{activeCorrectionType.label} time</label>
                  <input
                    id="correction-time"
                    type="time"
                    className="form-control"
                    value={correctionForm[activeCorrectionType.field]}
                    onChange={(e) => setCorrectionForm({ ...correctionForm, [activeCorrectionType.field]: e.target.value })}
                    required
                  />
                </div>
              )}
              <div className="it-span-2">
                <label className="form-label" htmlFor="correction-reason">Reason</label>
                <textarea maxLength={500} id="correction-reason" className="form-control" rows={2} value={correctionForm.reason} onChange={(e) => setCorrectionForm({ ...correctionForm, reason: e.target.value })} />
              </div>
            </div>
          </>
        )}
      </AppModal>

      <AppModal
        open={clockOutOpen}
        onClose={cancelClockOut}
        size="sm"
        title="Clock Out"
        icon="fa-right-from-bracket"
        busy={clocking}
        className="text-center"
        footer={(
          <>
            <button type="button" className="btn btn-secondary" onClick={cancelClockOut} disabled={clocking}>Cancel</button>
            <button type="button" className="btn btn-warning" onClick={confirmTakeBreak} disabled={clocking}>
              {clocking ? 'Working…' : 'Take a Break'}
            </button>
            <button type="button" className="btn btn-danger" onClick={confirmEndDay} disabled={clocking}>
              {clocking ? 'Working…' : 'End Attendance for the Day'}
            </button>
          </>
        )}
      >
        <p className="mb-3">Choose what you want to do.</p>
        <div className="text-muted" style={{ fontSize: '0.82rem' }}>Current Time</div>
        <div className="fw-semibold mb-3" style={{ fontSize: '1.15rem' }}>{formatManilaTime(nowTick)}</div>
        {clockOutError && <div className="alert alert-danger py-2">{clockOutError}</div>}
      </AppModal>

      <style>{`
        .attendance-completed-state {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 0.35rem;
          max-width: 34rem;
          margin: 0 auto;
          padding: 1.25rem 1rem;
          border: 1px solid var(--green-tint, #d0f0dc);
          border-radius: 14px;
          background: var(--green-pale, #e8f7ee);
        }
        .attendance-completed-state__icon {
          display: grid;
          place-items: center;
          width: 48px;
          height: 48px;
          border-radius: 50%;
          background: var(--green-main, #1a7a3f);
          color: #fff;
          font-size: 1.2rem;
        }
        .attendance-completed-state__title {
          margin: 0.35rem 0 0;
          color: var(--green-dark, #0a5c2e);
          font-weight: 800;
        }
        .attendance-completed-state__text {
          color: var(--text-mid, #3d5c46);
          font-size: 0.9rem;
        }
        .attendance-completed-state__hours {
          display: inline-block;
          padding: 0.2rem 0.7rem;
          border-radius: 999px;
          background: #fff;
          color: var(--green-dark, #0a5c2e);
          font-size: 0.8rem;
          font-weight: 700;
        }
        .wh-schedule-row {
          display: grid;
          grid-template-columns: minmax(8.5rem, 1fr) minmax(8.5rem, 1fr) auto;
          gap: 0.75rem 1rem;
          align-items: end;
          max-width: 36rem;
        }
        .wh-schedule-field {
          display: flex;
          flex-direction: column;
          min-width: 0;
        }
        .wh-schedule-action {
          min-width: 10.5rem;
        }
        .wh-schedule-submit {
          white-space: nowrap;
          height: calc(1.5em + 0.5rem + 2px);
        }
        @media (max-width: 576px) {
          .wh-schedule-row {
            grid-template-columns: 1fr;
            max-width: none;
          }
          .wh-schedule-action,
          .wh-schedule-submit {
            width: 100%;
          }
        }
      `}</style>
    </Wrapper>
  )
}

export default StudentAttendance

import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

function fmtTime(t) {
  if (!t) return '—'
  return String(t).slice(0, 5)
}

function StudentAttendance({ embedded = false }) {
  const currentTerm = useCurrentTerm()
  const [data, setData]           = useState(null)
  const [loading, setLoading]     = useState(true)
  const [error, setError]         = useState(null)
  const [clocking, setClocking]   = useState(false)
  const [message, setMessage]     = useState(null)

  const fetchAttendance = () => {
    setLoading(true)
    setError(null)
    api.get('/student/attendance')
      .then((res) => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load attendance.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAttendance() }, [])

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
    setClocking(true)
    setMessage(null)
    try {
      await api.post('/student/attendance/clock-out')
      setMessage({ type: 'success', text: 'Clocked out successfully!' })
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-out failed.' })
    } finally {
      setClocking(false)
    }
  }

  const todayStatus = data?.today_status ?? 'not_clocked_in'
  const logs = data?.attendance?.data ?? []
  
  // Check if student has multiple placements (to show placement column)
  const uniquePlacements = new Set(logs.map(log => log.placement?.label).filter(Boolean))
  const showPlacementColumn = uniquePlacements.size > 1

  

  const statusBadge = (s) => {
    if (s === 'validated') return <span className="badge-status badge-active">Validated</span>
    if (s === 'pending') return <span className="badge-status badge-pending">Pending</span>
    if (s === 'rejected') return <span className="badge-status badge-inactive">Rejected</span>
    return <span className="badge-status">{s}</span>
  }

  const Wrapper = embedded ? 'div' : Layout;
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "Attendance & Time Log", subtitle: currentTerm, icon: "fa-clock", bodyClass: "student-page" };

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={fetchAttendance} />}

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
            </div>
          )}
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-history"></i>
          <h6>Attendance History</h6>
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
                    <th>Hours</th>
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
                      <td>{log.hours_rendered != null ? `${log.hours_rendered} hrs` : '—'}</td>
                      <td>{statusBadge(log.status)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Wrapper>
  )
}

export default StudentAttendance

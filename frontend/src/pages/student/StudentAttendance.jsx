import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import api from '../../services/api'

function StudentAttendance() {
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [clocking, setClocking] = useState(false)
  const [message, setMessage] = useState(null)

  const fetchAttendance = () => {
    setLoading(true)
    api.get('/student/attendance')
      .then(res => setData(res.data))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAttendance() }, [])

  const handleClockIn = async () => {
    setClocking(true); setMessage(null)
    try {
      await api.post('/student/attendance/clock-in')
      setMessage({ type: 'success', text: 'Clocked in successfully!' })
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-in failed.' })
    } finally { setClocking(false) }
  }

  const handleClockOut = async () => {
    setClocking(true); setMessage(null)
    try {
      await api.post('/student/attendance/clock-out')
      setMessage({ type: 'success', text: 'Clocked out successfully!' })
      fetchAttendance()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Clock-out failed.' })
    } finally { setClocking(false) }
  }

  const todayStatus = data?.today_status ?? 'not_clocked_in'
  const logs        = data?.attendance?.data ?? []

  const statusBadge = (s) => {
    if (s === 'validated')  return <span className="badge-status badge-active">Validated</span>
    if (s === 'pending')    return <span className="badge-status badge-pending">Pending</span>
    if (s === 'rejected')   return <span className="badge-status badge-inactive">Rejected</span>
    return <span className="badge-status badge-pending">{s}</span>
  }

  return (
    <Layout title="Attendance & Time Log" subtitle="AY 2024-2025, Sem 2" icon="fa-clock" bodyClass="student-page">
      {/* Clock In / Out Card */}
      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-fingerprint"></i>
          <h6>Daily Time Record</h6>
          <span className="ms-auto text-muted" style={{fontSize:'0.85rem'}}>{new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'})}</span>
        </div>
        <div className="p-4 text-center">
          {message && <div className={`alert alert-${message.type} mb-3`}>{message.text}</div>}

          {todayStatus === 'not_clocked_in' && (
            <button className="btn btn-success px-5 py-2" onClick={handleClockIn} disabled={clocking}>
              <i className="fa fa-play-circle me-2"></i>{clocking ? 'Processing…' : 'Clock In'}
            </button>
          )}
          {todayStatus === 'clocked_in' && (
            <div>
              <div className="mb-3">
                <span className="badge bg-success fs-6 px-3 py-2">
                  <i className="fa fa-circle me-2" style={{color:'#86efac'}}></i>Currently Clocked In
                </span>
              </div>
              <button className="btn btn-danger px-5 py-2" onClick={handleClockOut} disabled={clocking}>
                <i className="fa fa-stop-circle me-2"></i>{clocking ? 'Processing…' : 'Clock Out'}
              </button>
            </div>
          )}
          {todayStatus === 'clocked_out' && (
            <span className="badge bg-secondary fs-6 px-3 py-2">
              <i className="fa fa-check-circle me-2"></i>Completed for Today
            </span>
          )}
        </div>
      </div>

      {/* Attendance History */}
      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-list"></i>
          <h6>Attendance History</h6>
        </div>
        <div className="table-card">
          <div className="table-responsive">
            {loading ? (
              <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            ) : (
              <table className="table table-hover">
                <thead>
                  <tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th></tr>
                </thead>
                <tbody>
                  {logs.length === 0 ? (
                    <tr><td colSpan={5} className="text-center text-muted py-4">No attendance records yet.</td></tr>
                  ) : logs.map(log => (
                    <tr key={log.id}>
                      <td>{new Date(log.date).toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'})}</td>
                      <td>{log.clock_in ?? '—'}</td>
                      <td>{log.clock_out ?? '—'}</td>
                      <td>{log.hours_rendered ? `${log.hours_rendered} hrs` : '—'}</td>
                      <td>{statusBadge(log.status)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </Layout>
  )
}

export default StudentAttendance

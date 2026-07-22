import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'

function studentName(log) {
  const p = log?.internship?.student?.student_profile || log?.internship?.student?.studentProfile
  if (p) return `${p.first_name || ''} ${p.last_name || ''}`.trim()
  return log?.internship?.student?.username || '—'
}

function statusBadge(status) {
  const map = {
    pending: 'bg-warning text-dark',
    validated: 'bg-success',
    rejected: 'bg-danger',
    flagged: 'bg-secondary',
  }
  return map[status] || 'bg-secondary'
}

function FacultyAttendance() {
  const [rows, setRows] = useState([])
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [statusFilter, setStatusFilter] = useState('all')
  const [internshipId, setInternshipId] = useState('')

  const fetchAttendance = () => {
    setLoading(true)
    setError(null)
    const params = {}
    if (statusFilter && statusFilter !== 'all') params.status = statusFilter
    if (internshipId) params.internship_id = internshipId
    api.get('/faculty/attendance', { params })
      .then((res) => setRows(unwrapList(res.data).items))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load attendance.')
        setRows([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    api.get('/faculty/assigned-students')
      .then((res) => setStudents(unwrapList(res.data).items))
      .catch(() => setStudents([]))
  }, [])

  useEffect(() => { fetchAttendance() }, [statusFilter, internshipId])

  return (
    <Layout title="Attendance Monitoring" subtitle={CURRENT_TERM} icon="fa-calendar-check" bodyClass="faculty-page">
      {error && <PageError message={error} onRetry={fetchAttendance} />}

      <div className="content-card mb-3">
        <div className="content-card-header">
          <i className="fa fa-filter"></i>
          <h6>Filters</h6>
        </div>
        <div className="p-3 row g-3">
          <div className="col-md-4">
            <label className="form-label fw-semibold">Student</label>
            <select
              className="form-select"
              value={internshipId}
              onChange={(e) => setInternshipId(e.target.value)}
            >
              <option value="">All assigned students</option>
              {students.map((s) => {
                const p = s.student?.student_profile || s.student?.studentProfile
                const name = p ? `${p.first_name || ''} ${p.last_name || ''}`.trim() : (s.student?.username || `Internship #${s.id}`)
                return <option key={s.id} value={s.id}>{name}</option>
              })}
            </select>
          </div>
          <div className="col-md-4">
            <label className="form-label fw-semibold">Status</label>
            <select
              className="form-select"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="validated">Validated</option>
              <option value="rejected">Rejected</option>
              <option value="flagged">Flagged</option>
            </select>
          </div>
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-clock"></i>
          <h6>Attendance &amp; Logged Hours</h6>
          <span className="ms-auto badge bg-secondary">{rows.length} record{rows.length === 1 ? '' : 's'}</span>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : rows.length === 0 ? (
            <div className="text-center py-4 text-muted">No attendance records for the selected filters.</div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Company</th>
                    <th>Date</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((log) => (
                    <tr key={log.id}>
                      <td className="fw-semibold">{studentName(log)}</td>
                      <td>{log.internship?.company?.company_name || '—'}</td>
                      <td>{log.date ? String(log.date).slice(0, 10) : '—'}</td>
                      <td>{log.clock_in || '—'}</td>
                      <td>{log.clock_out || '—'}</td>
                      <td>{log.hours_rendered != null ? Number(log.hours_rendered).toFixed(2) : '—'}</td>
                      <td>
                        <span className={`badge ${statusBadge(log.status)}`}>{log.status}</span>
                      </td>
                      <td className="text-muted" style={{ fontSize: '0.85rem', maxWidth: 180 }}>
                        {log.remarks || '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
        <p className="text-muted px-3 pb-3 mb-0" style={{ fontSize: '0.8rem' }}>
          Read-only monitoring. Industry supervisors validate or reject attendance; faculty can review logged hours here.
        </p>
      </div>
    </Layout>
  )
}

export default FacultyAttendance

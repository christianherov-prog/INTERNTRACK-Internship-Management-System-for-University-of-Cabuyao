import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { DEFAULT_TARGET_HOURS } from '../../config/hours'

function statusBadge(status) {
  const s = status === 'ongoing' ? 'active' : status
  if (s === 'active' || s === 'placed') return 'badge-active'
  if (s === 'completed') return 'badge-completed'
  if (s === 'pending_placement') return 'badge-pending'
  if (s === 'suspended' || s === 'deferred' || s === 'expelled') return 'badge-inactive'
  return 'badge-pending'
}

function SupervisorAssignedInterns() {
  const [interns, setInterns] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/supervisor/assigned-students')
      .then(res => setInterns(unwrapList(res.data).items))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load assigned students.')
        setInterns([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  return (
    <Layout title="Assigned Students" subtitle="Industry Supervisor" icon="fa-users" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={load} />}

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>My Assigned Interns</h6>
          <span className="ms-auto badge bg-primary">{interns.length}</span>
        </div>
        <p className="px-3 pt-3 mb-0 text-muted" style={{ fontSize: '0.85rem' }}>
          Status is official (set by Coordinator/Director). You can view it and submit evaluations — you cannot change it.
        </p>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : interns.length === 0 ? (
            <div className="text-center py-5 text-muted">
              <i className="fa fa-user-slash fa-3x mb-3 d-block"></i>
              No students assigned yet. A student invite + coordinator approval is required.
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Term</th>
                    <th>Hours</th>
                    <th>Official Status</th>
                  </tr>
                </thead>
                <tbody>
                  {interns.map((i) => {
                    const profile = i.student?.student_profile || i.student?.studentProfile
                    const name = profile ? `${profile.first_name} ${profile.last_name}` : i.student?.username
                    const hours = parseFloat(i.total_hours_rendered || 0)
                    const target = parseInt(i.target_hours || DEFAULT_TARGET_HOURS, 10)
                    return (
                      <tr key={i.id}>
                        <td className="fw-semibold">{name}</td>
                        <td>{profile?.student_number || i.student?.username}</td>
                        <td style={{ fontSize: '0.85rem' }}>{profile?.course_name || profile?.program || '—'}</td>
                        <td style={{ fontSize: '0.85rem' }}>{i.company?.company_name || '—'}</td>
                        <td style={{ fontSize: '0.85rem' }}>{i.term || '—'}</td>
                        <td style={{ fontSize: '0.85rem' }}>{hours} / {target}</td>
                        <td>
                          <span className={`badge-status ${statusBadge(i.status)}`}>
                            {i.status_label || i.status}
                          </span>
                          {i.status_reason && (
                            <div className="text-muted mt-1" style={{ fontSize: '0.72rem', maxWidth: 160 }} title={i.status_reason}>
                              {i.status_reason.length > 40 ? `${i.status_reason.slice(0, 40)}…` : i.status_reason}
                            </div>
                          )}
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

export default SupervisorAssignedInterns

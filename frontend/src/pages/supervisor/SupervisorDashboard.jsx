import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import QuickActionsPanel from '../../components/QuickActionsPanel'
import PageError from '../../components/PageError'
import api from '../../services/api'

const SUPERVISOR_QUICK_ACTIONS = [
  {
    to: '/supervisor/assigned-interns',
    title: 'View Assigned Students',
    description: 'See placement and official internship status',
    icon: 'fa-users',
    tone: 'blue',
  },
  {
    to: '/supervisor/attendance-validation',
    title: 'Validate Attendance',
    description: 'Review and approve student DTRs',
    icon: 'fa-clock',
    tone: 'teal',
  },
  {
    to: '/supervisor/journal-validation',
    title: 'Review Journals',
    description: 'Approve weekly narrative reports',
    icon: 'fa-book-open',
    tone: 'green',
  },
  {
    to: '/supervisor/feedback',
    title: 'Give Feedback',
    description: 'Narrative feedback for assigned interns',
    icon: 'fa-comment-dots',
    tone: 'gray',
  },
  {
    to: '/supervisor/performance-evaluation',
    title: 'Submit Evaluations',
    description: 'Midterm and final performance ratings',
    icon: 'fa-star',
    tone: 'amber',
  },
]

function SupervisorDashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/supervisor/dashboard')
      .then(res => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load supervisor dashboard.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const activity = data?.recent_activity ?? []

  return (
    <Layout title="Supervisor Dashboard" subtitle="Industry Supervisor" icon="fa-chart-line" bodyClass="supervisor-page">
      <RoleSummaryPanel />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <div className="row">
          <div className="col-lg-6 mb-4">
            <QuickActionsPanel actions={SUPERVISOR_QUICK_ACTIONS} />
          </div>

          <div className="col-lg-6 mb-4">
            <div className="content-card h-100">
              <div className="content-card-header bg-light">
                <i className="fa fa-history"></i>
                <h6 className="mb-0">Recent Activity</h6>
              </div>
              <div className="p-0">
                {activity.length === 0 ? (
                  <div className="text-center py-5 text-muted">
                    <i className="fa fa-inbox fa-3x mb-3"></i>
                    <p>No recent validation activity.</p>
                  </div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {activity.map((act, idx) => (
                      <li key={idx} className="list-group-item p-3 d-flex align-items-start">
                        <div className="bg-light text-success rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: 35, height: 35 }}>
                          <i className={`fa fa-${act.type === 'attendance' ? 'check' : 'book'}`}></i>
                        </div>
                        <div>
                          <p className="mb-1" style={{ fontSize: '0.9rem' }}>
                            {act.type === 'attendance'
                              ? <>Validated <strong>{act.hours} hrs</strong> for <strong>{act.student}</strong></>
                              : <>Approved Week {act.week} Journal for <strong>{act.student}</strong></>}
                          </p>
                          <small className="text-muted">
                            {act.action_at
                              ? new Date(act.action_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
                              : '—'}
                          </small>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </Layout>
  )
}

export default SupervisorDashboard

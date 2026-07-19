import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import PageError from '../../components/PageError'
import api from '../../services/api'

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
            <div className="content-card h-100">
              <div className="content-card-header bg-light">
                <i className="fa fa-bolt"></i>
                <h6 className="mb-0">Quick Actions</h6>
              </div>
              <div className="p-4">
                <div className="d-grid gap-3">
                  <Link to="/supervisor/assigned-interns" className="btn btn-outline-primary text-start p-3 d-flex align-items-center">
                    <div className="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: 40, height: 40 }}>
                      <i className="fa fa-users"></i>
                    </div>
                    <div>
                      <h6 className="mb-1 fw-bold">Assigned Students</h6>
                      <small className="text-muted">View placement and official internship status</small>
                    </div>
                    <i className="fa fa-chevron-right ms-auto text-muted"></i>
                  </Link>
                  <Link to="/supervisor/attendance-validation" className="btn btn-outline-primary text-start p-3 d-flex align-items-center">
                    <div className="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: 40, height: 40 }}>
                      <i className="fa fa-clock"></i>
                    </div>
                    <div>
                      <h6 className="mb-1 fw-bold">Validate Attendance</h6>
                      <small className="text-muted">Review and approve student DTRs</small>
                    </div>
                    <i className="fa fa-chevron-right ms-auto text-muted"></i>
                  </Link>
                  <Link to="/supervisor/journal-validation" className="btn btn-outline-success text-start p-3 d-flex align-items-center">
                    <div className="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: 40, height: 40 }}>
                      <i className="fa fa-book-open"></i>
                    </div>
                    <div>
                      <h6 className="mb-1 fw-bold">Review Journals</h6>
                      <small className="text-muted">Approve weekly narrative reports</small>
                    </div>
                    <i className="fa fa-chevron-right ms-auto text-muted"></i>
                  </Link>
                  <Link to="/supervisor/feedback" className="btn btn-outline-secondary text-start p-3 d-flex align-items-center">
                    <div className="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: 40, height: 40 }}>
                      <i className="fa fa-comment-dots"></i>
                    </div>
                    <div>
                      <h6 className="mb-1 fw-bold">Submit Feedback</h6>
                      <small className="text-muted">Narrative feedback for assigned interns</small>
                    </div>
                    <i className="fa fa-chevron-right ms-auto text-muted"></i>
                  </Link>
                  <Link to="/supervisor/performance-evaluation" className="btn btn-outline-warning text-start p-3 d-flex align-items-center">
                    <div className="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: 40, height: 40 }}>
                      <i className="fa fa-star"></i>
                    </div>
                    <div>
                      <h6 className="mb-1 fw-bold text-dark">Performance Evaluation</h6>
                      <small className="text-muted">Submit midterm / final ratings</small>
                    </div>
                    <i className="fa fa-chevron-right ms-auto text-muted"></i>
                  </Link>
                </div>
              </div>
            </div>
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

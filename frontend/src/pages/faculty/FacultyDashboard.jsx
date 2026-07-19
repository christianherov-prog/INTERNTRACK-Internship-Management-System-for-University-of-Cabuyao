import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import PageError from '../../components/PageError'
import api from '../../services/api'

function FacultyDashboard() {
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/faculty/dashboard')
      .then(res => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load faculty dashboard.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const activity = data?.recent_activity ?? []

  const timeAgo = (iso) => {
    if (!iso) return '—'
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
    if (diff < 60)    return `${diff}s ago`
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    return `${Math.floor(diff / 86400)}d ago`
  }

  return (
    <Layout title="Faculty Dashboard" subtitle="AY 2024-2025, Sem 2" icon="fa-chalkboard-teacher" bodyClass="faculty-page">
      <RoleSummaryPanel />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row">
            {/* Quick Actions Panel */}
            <div className="col-lg-6 mb-4">
              <div className="content-card h-100">
                <div className="content-card-header bg-light">
                  <i className="fa fa-bolt"></i>
                  <h6 className="mb-0">Quick Actions</h6>
                </div>
                <div className="p-4">
                  <div className="d-grid gap-3">
                    <Link to="/faculty/assigned-students" className="btn btn-outline-primary text-start p-3 d-flex align-items-center">
                      <div className="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: '40px', height: '40px' }}>
                        <i className="fa fa-users"></i>
                      </div>
                      <div>
                        <h6 className="mb-1 fw-bold">View Assigned Students</h6>
                        <small className="text-muted">See progress and status of all your students</small>
                      </div>
                      <i className="fa fa-chevron-right ms-auto text-muted"></i>
                    </Link>

                    <Link to="/faculty/journals" className="btn btn-outline-success text-start p-3 d-flex align-items-center">
                      <div className="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: '40px', height: '40px' }}>
                        <i className="fa fa-book-open"></i>
                      </div>
                      <div>
                        <h6 className="mb-1 fw-bold">Review Weekly Journals</h6>
                        <small className="text-muted">Read and approve student narrative reports</small>
                      </div>
                      <i className="fa fa-chevron-right ms-auto text-muted"></i>
                    </Link>

                    <Link to="/faculty/evaluations" className="btn btn-outline-warning text-start p-3 d-flex align-items-center">
                      <div className="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: '40px', height: '40px' }}>
                        <i className="fa fa-star"></i>
                      </div>
                      <div>
                        <h6 className="mb-1 fw-bold">Submit Evaluations</h6>
                        <small className="text-muted">Midterm and final performance evaluations</small>
                      </div>
                      <i className="fa fa-chevron-right ms-auto text-muted"></i>
                    </Link>

                    <Link to="/faculty/feedback" className="btn btn-outline-secondary text-start p-3 d-flex align-items-center">
                      <div className="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style={{ width: '40px', height: '40px' }}>
                        <i className="fa fa-comment-dots"></i>
                      </div>
                      <div>
                        <h6 className="mb-1 fw-bold">Give Feedback</h6>
                        <small className="text-muted">Send remarks and guidance to students</small>
                      </div>
                      <i className="fa fa-chevron-right ms-auto text-muted"></i>
                    </Link>
                  </div>
                </div>
              </div>
            </div>

            {/* Recent Activity Panel */}
            <div className="col-lg-6 mb-4">
              <div className="content-card h-100">
                <div className="content-card-header bg-light">
                  <i className="fa fa-clock-rotate-left"></i>
                  <h6 className="mb-0">Recent Activity</h6>
                </div>
                <div className="p-0">
                  {activity.length === 0 ? (
                    <div className="text-center text-muted py-5">
                      <i className="fa fa-inbox fa-2x mb-2 d-block"></i>
                      <p className="mb-0">No recent activity yet.</p>
                    </div>
                  ) : (
                    <div className="list-group list-group-flush">
                      {activity.map((a, idx) => (
                        <div key={idx} className="list-group-item d-flex align-items-start gap-3 px-4 py-3">
                          <div
                            className={`rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ${a.action === 'approved' ? 'bg-success' : 'bg-warning'} text-white`}
                            style={{ width: '32px', height: '32px', fontSize: '0.8rem' }}
                          >
                            <i className={`fa ${a.action === 'approved' ? 'fa-check' : 'fa-rotate'}`}></i>
                          </div>
                          <div className="flex-grow-1">
                            <div className="fw-semibold" style={{ fontSize: '0.88rem' }}>
                              {a.student} — Week {a.week}
                            </div>
                            <div className="text-muted" style={{ fontSize: '0.8rem' }}>
                              Journal {a.action === 'approved' ? 'approved' : 'sent back for revision'}
                            </div>
                          </div>
                          <small className="text-muted flex-shrink-0">{timeAgo(a.action_at)}</small>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default FacultyDashboard

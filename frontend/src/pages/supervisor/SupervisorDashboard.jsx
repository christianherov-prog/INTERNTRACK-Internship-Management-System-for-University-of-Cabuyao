import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import PageError from '../../components/PageError'
import api from '../../services/api'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'

function SupervisorDashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [previewData, setPreviewData] = useState(null)

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
  const profile = data?.profile ?? {}
  const company = data?.company ?? {}
  const interns = data?.assigned_interns ?? []
  const evals = data?.completed_evaluations ?? []

  return (
    <Layout title="Supervisor Dashboard" subtitle="Industry Supervisor" icon="fa-chart-line" bodyClass="supervisor-page">
      <RoleSummaryPanel />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          {/* Profile Summary Card */}
          <div className="card border-0 shadow-sm mb-4">
            <div className="card-body p-4 d-flex align-items-center">
              <div className="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-4" style={{ width: 60, height: 60, fontSize: '1.5rem' }}>
                {profile?.first_name?.charAt(0) || 'S'}
              </div>
              <div>
                <h4 className="fw-bold mb-1">{profile?.first_name} {profile?.last_name}</h4>
                <div className="text-muted">
                  <span className="me-3"><i className="fa fa-briefcase me-1"></i> {profile?.position || 'Supervisor'}</span>
                  <span><i className="fa fa-building me-1"></i> {company?.company_name || 'No Company Assigned'}</span>
                </div>
              </div>
            </div>
          </div>

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
                            <i className={`fa fa-check`}></i>
                          </div>
                          <div>
                            <p className="mb-1" style={{ fontSize: '0.9rem' }}>
                               Validated <strong>{act.hours} hrs</strong> for <strong>{act.student}</strong>
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

          {/* Assigned Interns and Evaluations Status Table */}
          <div className="card border-0 shadow-sm mb-4">
            <div className="card-header bg-white border-bottom py-3">
              <h6 className="mb-0 fw-bold"><i className="fa fa-users me-2 text-primary"></i>Assigned Interns & Evaluations Status</h6>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="ps-4">Student Name</th>
                      <th>Course</th>
                      <th>Internship Status</th>
                      <th>Hours Rendered</th>
                      <th>Midterm Eval</th>
                      <th>Final Eval</th>
                    </tr>
                  </thead>
                  <tbody>
                    {interns.length === 0 ? (
                      <tr><td colSpan="6" className="text-center py-4 text-muted">No assigned interns found.</td></tr>
                    ) : interns.map(intern => (
                      <tr key={intern.id}>
                        <td className="ps-4 fw-semibold">{intern.student}</td>
                        <td><small className="text-muted">{intern.course}</small></td>
                        <td>
                          <span className={`badge bg-${intern.status === 'completed' ? 'success' : intern.status === 'ongoing' ? 'primary' : 'secondary'}`}>
                            {intern.status}
                          </span>
                        </td>
                        <td>{intern.hours_rendered} / {intern.target_hours}</td>
                        <td>
                          {intern.evaluation_status?.midterm 
                            ? <span className="badge bg-success"><i className="fa fa-check me-1"></i>Completed</span>
                            : <span className="badge bg-warning text-dark"><i className="fa fa-clock me-1"></i>Pending</span>}
                        </td>
                        <td>
                          {intern.evaluation_status?.final 
                            ? <span className="badge bg-success"><i className="fa fa-check me-1"></i>Completed</span>
                            : <span className="badge bg-warning text-dark"><i className="fa fa-clock me-1"></i>Pending</span>}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {/* Completed Evaluations */}
          <div className="card border-0 shadow-sm mb-4">
            <div className="card-header bg-white border-bottom py-3">
              <h6 className="mb-0 fw-bold"><i className="fa fa-file-contract me-2 text-success"></i>Completed Evaluation Records</h6>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="ps-4 py-3">Form / Period</th>
                      <th>Student</th>
                      <th>Average Score</th>
                      <th>Rating</th>
                      <th className="text-end pe-4">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {evals.length === 0 ? (
                      <tr><td colSpan="5" className="text-center py-4 text-muted">No evaluations submitted yet.</td></tr>
                    ) : evals.map(ev => {
                      const st = ev.internship?.student?.student_profile || ev.internship?.student?.studentProfile;
                      const name = st ? `${st.first_name} ${st.last_name}` : '—';
                      return (
                        <tr key={ev.id}>
                          <td className="ps-4">
                            <span className="fw-bold">{ev.form_type}</span>
                            {ev.evaluation_period && <span className="text-muted ms-2 text-capitalize">({ev.evaluation_period})</span>}
                          </td>
                          <td className="fw-semibold">{name}</td>
                          <td><strong>{ev.average_score || '0.00'}</strong> <span className="text-muted small">/ 5.00</span></td>
                          <td>
                            <span className={`badge ${ev.rating === 'Failed' ? 'bg-danger' : ev.rating === 'Excellent' ? 'bg-success' : 'bg-primary'}`}>
                              {ev.rating || '—'}
                            </span>
                          </td>
                          <td className="text-end pe-4">
                            <button className="btn btn-sm btn-outline-primary" onClick={() => setPreviewData(ev)}>
                              <i className="fa fa-print me-1"></i>View Form
                            </button>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <FormPreviewModal 
            isOpen={!!previewData} 
            onClose={() => setPreviewData(null)} 
            type={previewData?.form_type || 'FO-24'}
            data={{ evalData: previewData, internship: previewData?.internship }} 
          />
        </>
      )}
    </Layout>
  )
}

export default SupervisorDashboard

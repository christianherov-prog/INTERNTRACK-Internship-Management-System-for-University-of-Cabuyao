import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { AuthenticatedFileLink, AuthenticatedFilePreview } from '../../components/AuthenticatedFile'

function studentLabel(inv) {
  const studentP = inv.student?.student_profile || inv.student?.studentProfile
  return studentP ? `${studentP.last_name}, ${studentP.first_name}` : (inv.student?.username || '—')
}

function reviewerLabel(inv) {
  const reviewerP = inv.reviewer?.faculty_profile || inv.reviewer?.facultyProfile || inv.reviewer?.supervisor_profile
  return reviewerP ? `${reviewerP.last_name}, ${reviewerP.first_name}` : (inv.reviewer?.username || '—')
}

function CoordSupervisorApprovals({ apiBase = '/faculty', bodyClass = 'faculty-page' }) {
  const [pending, setPending] = useState([])
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [actionLoading, setActionLoading] = useState(null)
  const [message, setMessage] = useState(null)
  const [remarks, setRemarks] = useState('')
  const [reviewTarget, setReviewTarget] = useState(null)
  const [activeFormIndex, setActiveFormIndex] = useState(0)

  const fetchData = () => {
    setLoading(true)
    setLoadError(null)
    api.get(`${apiBase}/supervisor-approvals`)
      .then(res => {
        setPending(res.data.pending || [])
        setHistory(res.data.history || [])
      })
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load supervisor approvals.')
        setPending([])
        setHistory([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchData() }, [apiBase])

  const openReview = (inv) => {
    setReviewTarget(inv)
    setActiveFormIndex(0)
    setRemarks('')
    setMessage(null)
  }

  const closeReview = () => {
    if (actionLoading) return
    setReviewTarget(null)
    setRemarks('')
  }

  const handleApprove = async () => {
    if (!reviewTarget) return
    setActionLoading(reviewTarget.id)
    try {
      await api.patch(`${apiBase}/supervisor-approvals/${reviewTarget.id}/approve`, { remarks: remarks.trim() })
      setReviewTarget(null)
      setRemarks('')
      fetchData()
    } catch (err) {
      setMessage(err.response?.data?.message || 'Failed to approve.')
    } finally {
      setActionLoading(null)
    }
  }

  const handleReject = async () => {
    if (!reviewTarget) return
    if (!remarks.trim()) {
      setMessage('Please provide a reason for rejection.')
      return
    }
    setActionLoading(reviewTarget.id)
    try {
      await api.patch(`${apiBase}/supervisor-approvals/${reviewTarget.id}/reject`, { remarks: remarks.trim() })
      setReviewTarget(null)
      setRemarks('')
      setMessage(null)
      fetchData()
    } catch (err) {
      setMessage(err.response?.data?.message || 'Failed to reject.')
    } finally {
      setActionLoading(null)
    }
  }

  const forms = reviewTarget?.acceptance_forms || []
  const activeForm = forms[activeFormIndex] || null

  if (loading) {
    return (
      <Layout title="Supervisor Approvals" subtitle="Review Pending Registrations" icon="fa-user-check" bodyClass={bodyClass}>
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x"></i></div>
      </Layout>
    )
  }

  return (
    <Layout title="Supervisor Approvals" subtitle="Review Pending Registrations" icon="fa-user-check" bodyClass={bodyClass}>
      {loadError && <PageError message={loadError} onRetry={fetchData} />}
      {message && (
        <div className="alert alert-danger alert-dismissible mb-3">
          {message}
          <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      <div className="content-card mb-4">
        <div className="content-card-header bg-light d-flex justify-content-between align-items-center">
          <h6 className="mb-0"><i className="fa fa-clock me-2 text-warning"></i>Pending Supervisor Registrations</h6>
          {pending.length > 0 && <span className="badge bg-warning text-dark">{pending.length} pending</span>}
        </div>
        <div className="p-0">
          {pending.length === 0 ? (
            <div className="text-center text-muted py-5">
              <i className="fa fa-check-circle fa-2x mb-2 text-success"></i>
              <p className="mb-0">No pending supervisor registrations.</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead className="table-light">
                  <tr>
                    <th>Supervisor</th>
                    <th>Contact</th>
                    <th>Position</th>
                    <th>Company</th>
                    <th>Student</th>
                    <th>Acceptance form</th>
                    <th>Registered</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {pending.map(inv => {
                    const formCount = (inv.acceptance_forms || []).length
                    return (
                      <tr key={inv.id}>
                        <td>
                          <strong>{inv.first_name} {inv.last_name}</strong>
                          <br /><small className="text-muted">{inv.email}</small>
                        </td>
                        <td><small>{inv.contact_number || '—'}</small></td>
                        <td><small>{inv.position || '—'}</small></td>
                        <td><small>{inv.company?.company_name || '—'}</small></td>
                        <td><small>{studentLabel(inv)}</small></td>
                        <td>
                          {formCount > 0
                            ? <span className="badge bg-success-subtle text-success border">{formCount} file{formCount === 1 ? '' : 's'}</span>
                            : <small className="text-muted">None uploaded</small>}
                        </td>
                        <td><small>{new Date(inv.updated_at).toLocaleDateString('en-PH')}</small></td>
                        <td className="text-center">
                          <button
                            type="button"
                            className="btn btn-primary btn-sm"
                            onClick={() => openReview(inv)}
                          >
                            <i className="fa fa-file-alt me-1"></i>Review form
                          </button>
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

      {reviewTarget && (
        <div className="modal d-block" style={{ background: 'rgba(0,0,0,0.5)' }} onClick={closeReview}>
          <div className="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" onClick={e => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h6 className="modal-title">
                  <i className="fa fa-file-signature me-2 text-primary"></i>
                  Review acceptance form — {reviewTarget.first_name} {reviewTarget.last_name}
                </h6>
                <button type="button" className="btn-close" onClick={closeReview} disabled={!!actionLoading}></button>
              </div>
              <div className="modal-body">
                <div className="row g-3 mb-3">
                  <div className="col-md-4">
                    <div className="text-muted small">Supervisor</div>
                    <div className="fw-semibold">{reviewTarget.first_name} {reviewTarget.last_name}</div>
                    <div className="small">{reviewTarget.email}</div>
                    <div className="small text-muted">{reviewTarget.contact_number || '—'}</div>
                  </div>
                  <div className="col-md-4">
                    <div className="text-muted small">Placement</div>
                    <div className="fw-semibold">{reviewTarget.position || '—'}</div>
                    <div className="small">{reviewTarget.company?.company_name || '—'}</div>
                  </div>
                  <div className="col-md-4">
                    <div className="text-muted small">Inviting student</div>
                    <div className="fw-semibold">{studentLabel(reviewTarget)}</div>
                  </div>
                </div>

                <h6 className="fw-semibold mb-2">Acceptance form</h6>
                {forms.length === 0 ? (
                  <div className="alert alert-warning mb-3">No acceptance form was uploaded with this registration.</div>
                ) : (
                  <>
                    {forms.length > 1 && (
                      <div className="d-flex flex-wrap gap-2 mb-2">
                        {forms.map((form, idx) => (
                          <button
                            key={form.path || idx}
                            type="button"
                            className={`btn btn-sm ${idx === activeFormIndex ? 'btn-primary' : 'btn-outline-primary'}`}
                            onClick={() => setActiveFormIndex(idx)}
                          >
                            {form.name || `File ${idx + 1}`}
                          </button>
                        ))}
                      </div>
                    )}
                    <div className="mb-2 d-flex justify-content-between align-items-center">
                      <small className="text-muted">{activeForm?.name || 'Acceptance form'}</small>
                      {activeForm?.path && (
                        <AuthenticatedFileLink path={activeForm.path} className="btn btn-sm btn-outline-secondary">
                          <i className="fa fa-external-link-alt me-1"></i>Open in new tab
                        </AuthenticatedFileLink>
                      )}
                    </div>
                    <AuthenticatedFilePreview
                      path={activeForm?.path}
                      mime={activeForm?.mime}
                      name={activeForm?.name}
                      height={520}
                    />
                  </>
                )}

                <label className="form-label fw-semibold mt-3">Remarks {reviewTarget ? <span className="text-muted fw-normal">(required to reject)</span> : null}</label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={remarks}
                  onChange={e => setRemarks(e.target.value)}
                  placeholder="Optional for approval. Required if you reject the registration."
                />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={closeReview} disabled={!!actionLoading}>Close</button>
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  onClick={handleReject}
                  disabled={actionLoading === reviewTarget.id}
                >
                  {actionLoading === reviewTarget.id ? <i className="fa fa-spinner fa-spin"></i> : <><i className="fa fa-times me-1"></i>Reject</>}
                </button>
                <button
                  type="button"
                  className="btn btn-success btn-sm"
                  onClick={handleApprove}
                  disabled={actionLoading === reviewTarget.id}
                >
                  {actionLoading === reviewTarget.id ? <i className="fa fa-spinner fa-spin"></i> : <><i className="fa fa-check me-1"></i>Approve</>}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {history.length > 0 && (
        <div className="content-card">
          <div className="content-card-header bg-light">
            <h6 className="mb-0"><i className="fa fa-history me-2"></i>Review History</h6>
          </div>
          <div className="p-0">
            <div className="table-responsive">
              <table className="table table-sm mb-0">
                <thead className="table-light">
                  <tr>
                    <th>Supervisor</th>
                    <th>Company</th>
                    <th>Student</th>
                    <th>Acceptance form</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th>Date</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map(inv => (
                    <tr key={inv.id} className="small">
                      <td>{inv.first_name} {inv.last_name}</td>
                      <td>{inv.company?.company_name || '—'}</td>
                      <td>{studentLabel(inv)}</td>
                      <td>
                        {(inv.acceptance_forms || []).length > 0
                          ? (inv.acceptance_forms || []).map((form, idx) => (
                            <div key={form.path || idx}>
                              <AuthenticatedFileLink path={form.path} className="small">
                                {form.name || `Form ${idx + 1}`}
                              </AuthenticatedFileLink>
                            </div>
                          ))
                          : '—'}
                      </td>
                      <td>
                        {inv.status === 'approved'
                          ? <span className="badge bg-success">Approved</span>
                          : <span className="badge bg-danger">Rejected</span>
                        }
                      </td>
                      <td>{reviewerLabel(inv)}</td>
                      <td>{inv.reviewed_at ? new Date(inv.reviewed_at).toLocaleDateString('en-PH') : '—'}</td>
                      <td>{inv.review_remarks || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </Layout>
  )
}

export default CoordSupervisorApprovals

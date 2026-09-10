import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { AuthenticatedFileDownload, AuthenticatedFileLink, AuthenticatedFilePreview } from '../../components/AuthenticatedFile'
import { useCachedPage } from '../../hooks/useCachedPage'
import { cacheDelete } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'
import '../../assets/css/supervisor-registration-review.css'

function studentLabel(inv) {
  if (inv.inviting_student_name) return inv.inviting_student_name
  const studentP = inv.student?.student_profile || inv.student?.studentProfile
  return studentP ? `${studentP.last_name}, ${studentP.first_name}` : (inv.student?.username || '—')
}

function reviewerLabel(inv) {
  const reviewerP = inv.reviewer?.faculty_profile || inv.reviewer?.facultyProfile || inv.reviewer?.supervisor_profile
  return reviewerP ? `${reviewerP.last_name}, ${reviewerP.first_name}` : (inv.reviewer?.username || '—')
}

function supervisorFullName(inv) {
  return [inv.first_name, inv.middle_name, inv.last_name].filter(Boolean).join(' ').trim()
}

function SummaryField({ label, value }) {
  if (value == null || String(value).trim() === '') return null
  return (
    <div className="sup-reg-summary-field">
      <div className="sup-reg-summary-label">{label}</div>
      <div className="sup-reg-summary-value">{value}</div>
    </div>
  )
}

function statusBadge(inv) {
  const label = inv.status_label || (inv.status === 'registered' ? 'Pending Faculty Approval' : inv.status)
  if (inv.status === 'approved') return <span className="badge bg-success">{label}</span>
  if (inv.status === 'rejected') return <span className="badge bg-danger">{label}</span>
  return <span className="badge bg-warning text-dark">{label || 'Pending Faculty Approval'}</span>
}

function CoordSupervisorApprovals({ apiBase = '/faculty', bodyClass = 'faculty-page' }) {
  const cacheKey = `staff:supervisor-approvals:${apiBase}`
  const { loading, seed, run } = useCachedPage(cacheKey)
  const [pending, setPending] = useState(() => seed?.pending ?? [])
  const [history, setHistory] = useState(() => seed?.history ?? [])
  const [loadError, setLoadError] = useState(null)
  const [actionLoading, setActionLoading] = useState(null)
  const [message, setMessage] = useState(null)
  const [remarks, setRemarks] = useState('')
  const [reviewTarget, setReviewTarget] = useState(null)
  const [activeFormIndex, setActiveFormIndex] = useState(0)
  const [confirmAction, setConfirmAction] = useState(null)
  const [pdfHeight, setPdfHeight] = useState(500)

  useEffect(() => {
    const apply = () => {
      const width = window.innerWidth
      if (width < 430) setPdfHeight(280)
      else if (width < 768) setPdfHeight(360)
      else setPdfHeight(500)
    }
    apply()
    window.addEventListener('resize', apply)
    return () => window.removeEventListener('resize', apply)
  }, [])

  const fetchData = () => {
    setLoadError(null)
    run(() => api.get(`${apiBase}/supervisor-approvals`).then(res => ({
      pending: res.data.pending || [],
      history: res.data.history || [],
    })))
      .then((next) => {
        if (next) {
          setPending(next.pending)
          setHistory(next.history)
        }
      })
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load supervisor approvals.')
        setPending([])
        setHistory([])
      })
  }

  useEffect(() => { fetchData() }, [apiBase])

  const openReview = (inv) => {
    setReviewTarget(inv)
    setActiveFormIndex(0)
    setRemarks('')
    setMessage(null)
    setConfirmAction(null)
  }

  const closeReview = () => {
    if (actionLoading) return
    setReviewTarget(null)
    setRemarks('')
    setConfirmAction(null)
  }

  const requestApprove = () => {
    if (!reviewTarget || actionLoading) return
    setMessage(null)
    setConfirmAction('approve')
  }

  const requestReject = () => {
    if (!reviewTarget || actionLoading) return
    if (!remarks.trim()) {
      setMessage('Please provide a reason for rejection.')
      return
    }
    setMessage(null)
    setConfirmAction('reject')
  }

  const handleApprove = async () => {
    if (!reviewTarget) return
    setActionLoading(reviewTarget.id)
    try {
      await api.patch(`${apiBase}/supervisor-approvals/${reviewTarget.id}/approve`, { remarks: remarks.trim() })
      setReviewTarget(null)
      setRemarks('')
      setConfirmAction(null)
      cacheDelete(cacheKey)
      fetchData()
    } catch (err) {
      setMessage(err.response?.data?.message || 'Failed to approve.')
      setConfirmAction(null)
    } finally {
      setActionLoading(null)
    }
  }

  const handleReject = async () => {
    if (!reviewTarget) return
    if (!remarks.trim()) {
      setMessage('Please provide a reason for rejection.')
      setConfirmAction(null)
      return
    }
    setActionLoading(reviewTarget.id)
    try {
      await api.patch(`${apiBase}/supervisor-approvals/${reviewTarget.id}/reject`, { remarks: remarks.trim() })
      setReviewTarget(null)
      setRemarks('')
      setMessage(null)
      setConfirmAction(null)
      cacheDelete(cacheKey)
      fetchData()
    } catch (err) {
      setMessage(err.response?.data?.message || 'Failed to reject.')
      setConfirmAction(null)
    } finally {
      setActionLoading(null)
    }
  }

  const forms = reviewTarget?.acceptance_forms || []
  const activeForm = forms[activeFormIndex] || null
  const reviewName = reviewTarget ? supervisorFullName(reviewTarget) : ''

  if (loading && !seed) {
    return (
      <Layout title="Supervisor Approvals" subtitle="Review Pending Registrations" icon="fa-user-check" bodyClass={bodyClass}>
        <div className="text-center py-5"><InternTrackLoader /></div>
      </Layout>
    )
  }

  return (
    <Layout title="Supervisor Approvals" subtitle="Review Pending Registrations" icon="fa-user-check" bodyClass={bodyClass}>
      {loadError && <PageError message={loadError} onRetry={fetchData} />}
      {message && !reviewTarget && (
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
        <div className="sup-reg-review-overlay" role="dialog" aria-modal="true" aria-labelledby="sup-reg-review-title">
          <div className="sup-reg-review-dialog">
            <div className="sup-reg-review-header">
              <div className="sup-reg-review-title-wrap">
                <div className="sup-reg-review-icon" aria-hidden="true">
                  <i className="fa fa-file-signature"></i>
                </div>
                <div>
                  <h6 id="sup-reg-review-title" className="sup-reg-review-title">Review Supervisor Registration</h6>
                  {reviewName ? <p className="sup-reg-review-subtitle">Supervisor: {reviewName}</p> : null}
                </div>
              </div>
              <button type="button" className="btn-close" onClick={closeReview} disabled={!!actionLoading} aria-label="Close"></button>
            </div>

            <div className="sup-reg-review-body">
              {message && (
                <div className="alert alert-danger alert-dismissible mb-3">
                  {message}
                  <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
                </div>
              )}

              <div className="sup-reg-summary-grid">
                <div className="sup-reg-summary-group">
                  <h6>Supervisor</h6>
                  <SummaryField label="Name" value={reviewName} />
                  <SummaryField label="Email" value={reviewTarget.email} />
                  <SummaryField label="Contact" value={reviewTarget.contact_number} />
                  <SummaryField label="Position" value={reviewTarget.position} />
                </div>
                <div className="sup-reg-summary-group">
                  <h6>Supervision</h6>
                  <SummaryField label="HTE / Company" value={reviewTarget.company?.company_name} />
                  <SummaryField label="Inviting Student" value={studentLabel(reviewTarget)} />
                  <SummaryField label="Program" value={reviewTarget.student_program} />
                  <SummaryField label="Department" value={reviewTarget.student_department} />
                </div>
              </div>

              <div className="sup-reg-status-row">
                <span className="text-muted">Status:</span>
                {statusBadge(reviewTarget)}
              </div>

              <div className="sup-reg-form-section">
                <h6>Acceptance Form</h6>
                {forms.length === 0 ? (
                  <div className="alert alert-warning mb-0">No acceptance form was uploaded with this registration.</div>
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
                    <div className="sup-reg-form-actions">
                      <div className="small">
                        <span className="text-muted">File:</span>{' '}
                        <strong>{activeForm?.name || 'Acceptance form'}</strong>
                      </div>
                      {activeForm?.path && (
                        <div className="d-flex flex-wrap gap-2">
                          <AuthenticatedFileLink path={activeForm.path} className="btn btn-sm btn-outline-secondary">
                            <i className="fa fa-external-link-alt me-1"></i>Open in New Tab
                          </AuthenticatedFileLink>
                          <AuthenticatedFileDownload
                            path={activeForm.path}
                            filename={activeForm.name || 'acceptance-form.pdf'}
                            className="btn btn-sm btn-outline-primary"
                          >
                            <i className="fa fa-download me-1"></i>Download
                          </AuthenticatedFileDownload>
                        </div>
                      )}
                    </div>
                    <div className="sup-reg-pdf-shell">
                      <AuthenticatedFilePreview
                        path={activeForm?.path}
                        mime={activeForm?.mime}
                        name={activeForm?.name}
                        height={pdfHeight}
                        errorMessage="Unable to preview the acceptance form. Open the file in a new tab or try again."
                      />
                    </div>
                  </>
                )}
              </div>

              <div className="sup-reg-remarks-section">
                <label className="form-label fw-semibold mb-1" htmlFor="sup-reg-remarks">Remarks</label>
                <div className="small text-muted mb-2">Required when rejecting.</div>
                <textarea
                  id="sup-reg-remarks"
                  className="form-control"
                  rows={3}
                  value={remarks}
                  onChange={e => setRemarks(e.target.value)}
                  placeholder="Remarks"
                />
              </div>
            </div>

            <div className="sup-reg-review-footer">
              <button type="button" className="btn btn-secondary btn-sm" onClick={closeReview} disabled={!!actionLoading}>Close</button>
              <div className="sup-reg-review-footer-actions">
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  onClick={requestReject}
                  disabled={actionLoading === reviewTarget.id}
                >
                  <i className="fa fa-times me-1"></i>Reject
                </button>
                <button
                  type="button"
                  className="btn btn-success btn-sm"
                  onClick={requestApprove}
                  disabled={actionLoading === reviewTarget.id}
                >
                  <i className="fa fa-check me-1"></i>Approve
                </button>
              </div>
            </div>

            {confirmAction && (
              <div className="sup-reg-confirm-overlay">
                <div className="sup-reg-confirm-card">
                  <h6>{confirmAction === 'approve' ? 'Approve Supervisor Registration?' : 'Reject Supervisor Registration?'}</h6>
                  <SummaryField label="Supervisor" value={reviewName} />
                  <SummaryField label="Student" value={studentLabel(reviewTarget)} />
                  <SummaryField label="HTE" value={reviewTarget.company?.company_name} />
                  {confirmAction === 'reject' ? (
                    <p className="small text-muted mt-2 mb-0">This supervisor will not gain active student access.</p>
                  ) : null}
                  <div className="d-flex justify-content-end gap-2 mt-3">
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={() => setConfirmAction(null)}
                      disabled={!!actionLoading}
                    >
                      Cancel
                    </button>
                    {confirmAction === 'approve' ? (
                      <button
                        type="button"
                        className="btn btn-success btn-sm"
                        onClick={handleApprove}
                        disabled={actionLoading === reviewTarget.id}
                      >
                        {actionLoading === reviewTarget.id ? <i className="fa fa-spinner fa-spin"></i> : 'Approve'}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="btn btn-danger btn-sm"
                        onClick={handleReject}
                        disabled={actionLoading === reviewTarget.id}
                      >
                        {actionLoading === reviewTarget.id ? <i className="fa fa-spinner fa-spin"></i> : 'Reject'}
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )}
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
                      <td>{statusBadge(inv)}</td>
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

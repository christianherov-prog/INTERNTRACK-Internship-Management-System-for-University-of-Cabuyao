import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import ConfirmModal from '../../components/modals/ConfirmModal'
import PageError from '../../components/PageError'
import api from '../../services/api'

function CoordSupervisorApprovals({ apiBase = '/faculty', bodyClass = 'faculty-page' }) {
  const [pending, setPending] = useState([])
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [actionLoading, setActionLoading] = useState(null)
  const [message, setMessage] = useState(null)
  const [remarks, setRemarks] = useState('')
  const [rejectTarget, setRejectTarget] = useState(null)
  const [approveTarget, setApproveTarget] = useState(null)

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

  const handleApprove = async () => {
    if (!approveTarget) return
    setActionLoading(approveTarget)
    try {
      await api.patch(`${apiBase}/supervisor-approvals/${approveTarget}/approve`, { remarks: '' })
      setApproveTarget(null)
      fetchData()
    } catch (err) {
      setMessage(err.response?.data?.message || 'Failed to approve.')
    } finally {
      setActionLoading(null)
    }
  }

  const handleReject = async () => {
    if (!remarks.trim()) {
      setMessage('Please provide a reason for rejection.')
      return
    }
    setActionLoading(rejectTarget)
    try {
      await api.patch(`${apiBase}/supervisor-approvals/${rejectTarget}/reject`, { remarks })
      setRejectTarget(null)
      setRemarks('')
      setMessage(null)
      fetchData()
    } catch (err) {
      setMessage(err.response?.data?.message || 'Failed to reject.')
    } finally {
      setActionLoading(null)
    }
  }

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

      <ConfirmModal
        open={!!approveTarget}
        title="Approve supervisor?"
        message="Approve this supervisor and assign them to the student's internship?"
        confirmLabel="Approve"
        loading={!!actionLoading && actionLoading === approveTarget}
        onCancel={() => !actionLoading && setApproveTarget(null)}
        onConfirm={handleApprove}
      />
      {/* Pending Registrations */}
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
                    <th>Registered</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {pending.map(inv => {
                    const sup = inv.supervisor?.supervisor_profile || inv
                    const studentP = inv.student?.student_profile
                    return (
                      <tr key={inv.id}>
                        <td>
                          <strong>{inv.first_name} {inv.last_name}</strong>
                          <br /><small className="text-muted">{inv.email}</small>
                        </td>
                        <td><small>{inv.contact_number || '—'}</small></td>
                        <td><small>{inv.position || '—'}</small></td>
                        <td><small>{inv.company?.company_name || '—'}</small></td>
                        <td>
                          <small>
                            {studentP ? `${studentP.first_name} ${studentP.last_name}` : inv.student?.username || '—'}
                          </small>
                        </td>
                        <td><small>{new Date(inv.updated_at).toLocaleDateString('en-PH')}</small></td>
                        <td className="text-center">
                          <button
                            className="btn btn-success btn-sm me-1"
                            disabled={actionLoading === inv.id}
                            onClick={() => setApproveTarget(inv.id)}
                          >
                            {actionLoading === inv.id
                              ? <i className="fa fa-spinner fa-spin"></i>
                              : <><i className="fa fa-check me-1"></i>Approve</>
                            }
                          </button>
                          <button
                            className="btn btn-outline-danger btn-sm"
                            disabled={actionLoading === inv.id}
                            onClick={() => setRejectTarget(inv.id)}
                          >
                            <i className="fa fa-times me-1"></i>Reject
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

      {/* Reject Modal */}
      {rejectTarget && (
        <div className="modal d-block" style={{ background: 'rgba(0,0,0,0.5)' }} onClick={() => setRejectTarget(null)}>
          <div className="modal-dialog modal-dialog-centered" onClick={e => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h6 className="modal-title"><i className="fa fa-times-circle text-danger me-2"></i>Reject Supervisor Registration</h6>
                <button type="button" className="btn-close" onClick={() => setRejectTarget(null)}></button>
              </div>
              <div className="modal-body">
                <label className="form-label fw-semibold">Reason for Rejection <span className="text-danger">*</span></label>
                <textarea className="form-control" rows={3} value={remarks} onChange={e => setRemarks(e.target.value)} placeholder="Provide a reason..." />
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary btn-sm" onClick={() => setRejectTarget(null)}>Cancel</button>
                <button className="btn btn-danger btn-sm" onClick={handleReject} disabled={actionLoading === rejectTarget}>
                  {actionLoading === rejectTarget ? <i className="fa fa-spinner fa-spin"></i> : <><i className="fa fa-times me-1"></i>Reject</>}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* History */}
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
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th>Date</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map(inv => {
                    const studentP = inv.student?.student_profile
                    const reviewerP = inv.reviewer?.faculty_profile || inv.reviewer?.supervisor_profile
                    return (
                      <tr key={inv.id} className="small">
                        <td>{inv.first_name} {inv.last_name}</td>
                        <td>{inv.company?.company_name || '—'}</td>
                        <td>{studentP ? `${studentP.first_name} ${studentP.last_name}` : '—'}</td>
                        <td>
                          {inv.status === 'approved'
                            ? <span className="badge bg-success">Approved</span>
                            : <span className="badge bg-danger">Rejected</span>
                          }
                        </td>
                        <td>{reviewerP ? `${reviewerP.first_name} ${reviewerP.last_name}` : inv.reviewer?.username || '—'}</td>
                        <td>{inv.reviewed_at ? new Date(inv.reviewed_at).toLocaleDateString('en-PH') : '—'}</td>
                        <td>{inv.review_remarks || '—'}</td>
                      </tr>
                    )
                  })}
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

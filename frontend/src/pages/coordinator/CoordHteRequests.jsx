import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import ConfirmModal from '../../components/modals/ConfirmModal'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import api from '../../services/api'
import { formatStudentName } from '../../utils/formatName'
import { useCachedPage } from '../../hooks/useCachedPage'
import { cacheDelete } from '../../utils/pageCache'
import { formatYearSection } from '../../utils/formatSection'
import InternTrackLoader from '../../components/InternTrackLoader'

function CoordHteRequests({ embedded = false }) {
  const { pending, seed, run } = useCachedPage('coordinator:hte-requests')
  const [requests, setRequests] = useState(() => seed ?? [])
  const [error, setError] = useState(null)
  const [updatingId, setUpdatingId] = useState(null)
  const [successMsg, setSuccessMsg] = useState(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [review, setReview] = useState(null)
  const [rejectReason, setRejectReason] = useState('')

  const load = () => {
    setError(null)
    run(() => api.get('/coordinator/hte-requests').then(res => res.data.requests || []))
      .then((next) => { if (next) setRequests(next) })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load HTE requests.')
      })
  }

  useEffect(() => { load() }, [])

  const closeReview = () => {
    if (updatingId) return
    setReview(null)
    setRejectReason('')
  }

  const submitReview = async () => {
    if (!review) return
    if (review.action === 'rejected' && !rejectReason.trim()) {
      setError('A reason is required when rejecting an HTE request.')
      return
    }
    setUpdatingId(review.req.id)
    setSuccessMsg(null)
    setError(null)
    try {
      const payload = { status: review.action }
      if (review.action === 'rejected') payload.coordinator_remarks = rejectReason.trim()
      await api.patch(`/coordinator/hte-requests/${review.req.id}/status`, payload)
      setSuccessMsg(review.action === 'approved' ? 'HTE request approved.' : 'HTE request rejected.')
      setReview(null)
      setRejectReason('')
      cacheDelete('coordinator:hte-requests')
      cacheDelete('student:companies')
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update request.')
    } finally {
      setUpdatingId(null)
    }
  }

  const Wrapper = embedded ? 'div' : Layout
  const wrapperProps = embedded ? { className: 'embedded-view' } : { title: 'HTE Requests', subtitle: 'Review New Company Requests', icon: 'fa-handshake', bodyClass: 'coordinator-page' }

  const filtered = requests.filter(r => {
    const name = formatStudentName(r).toLowerCase()
    const status = (r.status || '').toLowerCase()
    return (!search || name.includes(search.toLowerCase()))
      && (statusFilter === 'all' || status === statusFilter)
  })

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={load} />}
      {successMsg && (
        <div className="alert alert-success alert-dismissible mb-3">
          {successMsg}
          <button className="btn-close" onClick={() => setSuccessMsg(null)}></button>
        </div>
      )}

      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input maxLength={100} className="form-control border-start-0 ps-0" placeholder="Search" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-handshake"></i>
          <h6>HTE Requests</h6>
          <span className="ms-auto badge bg-secondary">{filtered.length} request{filtered.length !== 1 ? 's' : ''}</span>
        </div>
        {pending && requests.length === 0 ? (
          <InternTrackLoader />
        ) : (
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Requested By</th>
                  <th>Company Name</th>
                  <th>Contact Info</th>
                  <th>Student Remarks</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr><td colSpan={6} className="text-center py-4 text-muted">No HTE requests match the selected filters.</td></tr>
                ) : filtered.map(r => (
                  <tr key={r.id}>
                    <td>
                      <div className="fw-semibold">{formatStudentName(r)}</div>
                      <div className="small text-muted">{r.student?.email}</div>
                    </td>
                    <td className="fw-semibold">{r.company_name}</td>
                    <td>
                      <div className="small"><strong>Address:</strong> {r.address || '—'}</div>
                      <div className="small"><strong>Person:</strong> {r.contact_person || '—'}</div>
                      <div className="small"><strong>Email:</strong> {r.contact_email || '—'}</div>
                      <div className="small"><strong>Phone:</strong> {r.contact_number || '—'}</div>
                    </td>
                    <td className="small">{r.remarks || '—'}</td>
                    <td>
                      <span className={`badge ${r.status === 'rejected' ? 'bg-danger' : r.status === 'pending' ? 'bg-warning text-dark' : 'bg-success'}`}>
                        {(r.status || '').toUpperCase()}
                      </span>
                    </td>
                    <td>
                      <div className="d-flex flex-wrap gap-2">
                        {r.moa_path && (
                          <AuthenticatedFileLink path={r.moa_path} className="btn btn-sm btn-outline-secondary">
                            View MOA
                          </AuthenticatedFileLink>
                        )}
                        {r.status === 'pending' && (
                          <>
                            <button className="btn btn-sm btn-success" onClick={() => { setReview({ req: r, action: 'approved' }); setError(null) }} disabled={updatingId === r.id}>
                              Approve
                            </button>
                            <button className="btn btn-sm btn-danger" onClick={() => { setReview({ req: r, action: 'rejected' }); setRejectReason(''); setError(null) }} disabled={updatingId === r.id}>
                              Reject
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <ConfirmModal
        open={!!review}
        title={review?.action === 'rejected' ? 'Reject HTE Request?' : 'Approve HTE Request?'}
        confirmLabel={review?.action === 'rejected' ? 'Reject' : 'Approve'}
        variant={review?.action === 'rejected' ? 'danger' : 'primary'}
        loading={!!updatingId}
        onCancel={closeReview}
        onConfirm={submitReview}
      >
        {review && (
          <>
            <div className="mb-1"><strong>Student:</strong> {formatStudentName(review.req)}</div>
            <div className="mb-1"><strong>Program:</strong> {review.req.student?.student_profile?.program?.name || review.req.student?.studentProfile?.program?.name || '—'}</div>
            <div className="mb-1"><strong>Section:</strong> {formatYearSection(review.req.student?.student_profile?.section || review.req.student?.studentProfile?.section) || '—'}</div>
            <div className="mb-1"><strong>Company:</strong> {review.req.company_name}</div>
            <div className="mb-1"><strong>Address:</strong> {review.req.address || '—'}</div>
            <div className="mb-1"><strong>Contact:</strong> {review.req.contact_person || '—'} · {review.req.contact_email || '—'} · {review.req.contact_number || '—'}</div>
            <div className="mb-1"><strong>Student remarks:</strong> {review.req.remarks || '—'}</div>
            <div className="mb-2">
              <strong>MOA:</strong>{' '}
              {review.req.moa_path
                ? <AuthenticatedFileLink path={review.req.moa_path}>View / Download</AuthenticatedFileLink>
                : 'None attached'}
            </div>
            {review.action === 'approved' && (
              <p className="text-muted small mb-0">Approving adds this HTE if it is not already in the company list (MOA status: on process).</p>
            )}
            {review.action === 'rejected' && (
              <>
                <label className="form-label fw-semibold mb-1">Reason for rejection</label>
                <textarea maxLength={2000} className="form-control" rows={3} value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} placeholder="Reason" />
              </>
            )}
          </>
        )}
      </ConfirmModal>
    </Wrapper>
  )
}

export default CoordHteRequests

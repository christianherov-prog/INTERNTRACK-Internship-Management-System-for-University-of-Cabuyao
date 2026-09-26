import { formatYearSection } from '../../utils/formatSection'
import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import ConfirmModal from '../../components/modals/ConfirmModal'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import api from '../../services/api'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { formatStudentName } from '../../utils/formatName'
import { useCachedPage } from '../../hooks/useCachedPage'
import { cacheDelete } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'

function programName(app) {
  const p = app.student?.student_profile?.program || app.student?.studentProfile?.program
  if (typeof p === 'string') return p
  return p?.name || p?.code || '—'
}

function CoordPlacements({ embedded = false }) {
  const currentTerm = useCurrentTerm()
  const { pending, seed, run } = useCachedPage('coordinator:applications')
  const [applications, setApplications] = useState(() => seed ?? [])
  const [error, setError] = useState(null)
  const [updatingId, setUpdatingId] = useState(null)
  const [successMsg, setSuccessMsg] = useState(null)
  const [search, setSearch] = useState('')
  const [programFilter, setProgramFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [sectionFilter, setSectionFilter] = useState('all')
  const [review, setReview] = useState(null)
  const [rejectReason, setRejectReason] = useState('')

  const load = () => {
    setError(null)
    run(() => api.get('/coordinator/applications').then(res => res.data.applications || []))
      .then((next) => { if (next) setApplications(next) })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load applications.')
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
      setError('A reason is required when rejecting an application.')
      return
    }
    setUpdatingId(review.app.id)
    setSuccessMsg(null)
    setError(null)
    try {
      const payload = { status: review.action }
      if (review.action === 'rejected') payload.coordinator_remarks = rejectReason.trim()
      await api.patch(`/coordinator/applications/${review.app.id}/status`, payload)
      setSuccessMsg(review.action === 'approved' ? 'Application approved.' : 'Application rejected.')
      setReview(null)
      setRejectReason('')
      cacheDelete('coordinator:applications')
      cacheDelete('student:companies')
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update application.')
    } finally {
      setUpdatingId(null)
    }
  }

  const Wrapper = embedded ? 'div' : Layout
  const wrapperProps = embedded
    ? { className: 'embedded-view' }
    : { title: 'Student Placements', subtitle: currentTerm, icon: 'fa-paper-plane', bodyClass: 'coordinator-page' }

  const programs = ['all', ...new Set(applications.map(a => programName(a)).filter(s => s !== '—'))]
  const sections = ['all', ...new Set(applications.map(a => formatYearSection(a.student?.student_profile?.section || a.student?.studentProfile?.section) || '—').filter(s => s !== '—'))]

  const filtered = applications.filter(app => {
    const name = formatStudentName(app).toLowerCase()
    const prog = programName(app)
    const sec = formatYearSection(app.student?.student_profile?.section || app.student?.studentProfile?.section) || '—'
    const status = (app.status || '').toLowerCase()
    return (!search || name.includes(search.toLowerCase()))
      && (programFilter === 'all' || prog === programFilter)
      && (sectionFilter === 'all' || sec === sectionFilter)
      && (statusFilter === 'all' || status.includes(statusFilter))
  })

  const isPendingStatus = (status) => status === 'pending' || status === 'pending_coordinator_approval'

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
        <select className="form-select form-select-sm text-secondary" style={{ width: 170 }} value={programFilter} onChange={e => setProgramFilter(e.target.value)}>
          {programs.map(p => <option key={p} value={p}>{p === 'all' ? 'All Programs' : p}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 150 }} value={sectionFilter} onChange={e => setSectionFilter(e.target.value)}>
          {sections.map(s => <option key={s} value={s}>{s === 'all' ? 'Sections' : s}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-paper-plane"></i>
          <h6>Student Placements</h6>
          <span className="ms-auto badge bg-secondary">{filtered.length} application{filtered.length !== 1 ? 's' : ''}</span>
        </div>
        {pending && applications.length === 0 ? (
          <InternTrackLoader />
        ) : (
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Program</th>
                  <th>Company</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr><td colSpan={5} className="text-center py-4 text-muted">No applications match the selected filters.</td></tr>
                ) : filtered.map(app => (
                  <tr key={app.id}>
                    <td>
                      <div className="fw-semibold">{formatStudentName(app)}</div>
                      <div className="small text-muted">{app.student?.email}</div>
                    </td>
                    <td>{programName(app)}</td>
                    <td className="fw-semibold">{app.company?.company_name || '—'}</td>
                    <td>
                      <span className={`badge ${(app.status || '').includes('rejected') ? 'bg-danger' : (app.status || '').includes('pending') ? 'bg-warning text-dark' : 'bg-success'}`}>
                        {(app.status || '').replace(/_/g, ' ').toUpperCase()}
                      </span>
                    </td>
                    <td>
                      <div className="d-flex flex-wrap gap-2">
                        {app.moa_path && (
                          <AuthenticatedFileLink path={app.moa_path} className="btn btn-sm btn-outline-secondary">
                            View MOA
                          </AuthenticatedFileLink>
                        )}
                        {isPendingStatus(app.status) && (
                          <>
                            <button className="btn btn-sm btn-success" onClick={() => { setReview({ app, action: 'approved' }); setError(null) }} disabled={updatingId === app.id}>
                              Approve
                            </button>
                            <button className="btn btn-sm btn-danger" onClick={() => { setReview({ app, action: 'rejected' }); setRejectReason(''); setError(null) }} disabled={updatingId === app.id}>
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
        title={review?.action === 'rejected' ? 'Reject Placement Application?' : 'Approve Placement Application?'}
        message=""
        confirmLabel={review?.action === 'rejected' ? 'Reject' : 'Approve'}
        variant={review?.action === 'rejected' ? 'danger' : 'primary'}
        loading={!!updatingId}
        onCancel={closeReview}
        onConfirm={submitReview}
      >
        {review && (
          <>
            <div className="mb-1"><strong>Student:</strong> {formatStudentName(review.app)}</div>
            <div className="mb-1"><strong>Program:</strong> {programName(review.app)}</div>
            <div className="mb-1"><strong>Section:</strong> {formatYearSection(review.app.student?.student_profile?.section || review.app.student?.studentProfile?.section) || '—'}</div>
            <div className="mb-1"><strong>Company:</strong> {review.app.company?.company_name || '—'}</div>
            <div className="mb-1"><strong>Submitted:</strong> {review.app.created_at || '—'}</div>
            <div className="mb-2">
              <strong>MOA:</strong>{' '}
              {review.app.moa_path
                ? <AuthenticatedFileLink path={review.app.moa_path}>View / Download</AuthenticatedFileLink>
                : 'None attached'}
            </div>
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

export default CoordPlacements

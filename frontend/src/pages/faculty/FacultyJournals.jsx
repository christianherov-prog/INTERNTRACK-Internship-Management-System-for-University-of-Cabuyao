import { useState, useEffect } from 'react'
import { createPortal } from 'react-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

function ReviewModal({ journal, onClose, onSubmit, processing }) {
  const [action, setAction]     = useState('approved')
  const [feedback, setFeedback] = useState('')
  const isImage = journal.file_path && !journal.file_path.endsWith('.pdf')
  const isPdf   = journal.file_path && journal.file_path.endsWith('.pdf')

  return createPortal(
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.45)' }}>
      <div className="modal-dialog modal-xl modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">
              <i className="fa fa-book me-2 text-primary"></i>
              Review Journal — Week {journal.week_number ?? journal.entry_number}
            </h5>
            <button className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            <div className="mb-3 p-3 rounded" style={{ background: '#f8fafc', fontSize: '0.88rem' }}>
              <div className="fw-semibold mb-1">Week {journal.week_number ?? journal.entry_number}</div>
              {journal.notes && <p className="mb-0 text-muted"><strong>Notes:</strong> {journal.notes}</p>}
              {journal.faculty_feedback && (
                <div className="mt-2 alert alert-info py-2 mb-0">
                  <strong>Previous Feedback:</strong> {journal.faculty_feedback}
                </div>
              )}
            </div>

            {journal.file_path ? (
              <div className="mb-3 text-center">
                {isImage ? (
                  <AuthenticatedFileImage
                    path={journal.file_path}
                    alt="Journal"
                    style={{ maxWidth: '100%', maxHeight: '500px', border: '1px solid #ddd', borderRadius: '4px' }}
                  />
                ) : isPdf ? (
                  <div>
                    <p className="text-muted small mb-2">Submitted PNC:AA-FO-31 Journal Form (PDF)</p>
                    <AuthenticatedFileLink path={journal.file_path} className="btn btn-outline-danger">
                      <i className="fa fa-file-pdf me-2"></i>Open PDF to Review
                    </AuthenticatedFileLink>
                  </div>
                ) : null}
              </div>
            ) : (
              <div className="alert alert-warning">No file uploaded for this journal entry.</div>
            )}

            <div className="mb-3">
              <label className="form-label fw-semibold">Action</label>
              <div className="d-flex gap-3">
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="fac_approve" checked={action === 'approved'} onChange={() => setAction('approved')} />
                  <label className="form-check-label" htmlFor="fac_approve">âœ… Approve</label>
                </div>
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="fac_revise" checked={action === 'needs_revision'} onChange={() => setAction('needs_revision')} />
                  <label className="form-check-label" htmlFor="fac_revise">ðŸ”„ Needs Revision</label>
                </div>
              </div>
            </div>
            <div>
              <label className="form-label fw-semibold">
                Feedback {action === 'needs_revision' && <span className="text-danger">*</span>}
              </label>
              <textarea
                className="form-control"
                rows={3}
                value={feedback}
                onChange={e => setFeedback(e.target.value)}
                placeholder="Write feedback for the student…"
              ></textarea>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button
              className="btn btn-green"
              onClick={() => onSubmit(journal.id, action, feedback)}
              disabled={processing || (action === 'needs_revision' && !feedback.trim())}
            >
              <i className={`fa fa-${processing ? 'spinner fa-spin' : 'check'} me-2`}></i>Submit Review
            </button>
          </div>
        </div>
      </div>
    </div>,
    document.body
  )
}

function FacultyJournals() {
  const currentTerm = useCurrentTerm()
  const [journals, setJournals]     = useState([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage]       = useState(null)
  const [modal, setModal]           = useState(null)

  const fetchJournals = () => {
    setLoading(true)
    setError(null)
    api.get('/faculty/journals')
      .then(res => setJournals(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load journals.')
        setJournals([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchJournals() }, [])

  const handleReview = async (id, action, feedback) => {
    setProcessing(true)
    try {
      await api.patch(`/faculty/journals/${id}/review`, { action, feedback })
      setMessage({ type: action === 'approved' ? 'success' : 'info', text: `Journal ${action === 'approved' ? 'approved' : 'returned for revision'}.` })
      setModal(null)
      fetchJournals()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Review failed.' })
    } finally { setProcessing(false) }
  }

  return (
    <Layout title="Journals" subtitle={currentTerm} icon="fa-book" bodyClass="faculty-page">
      {error && <PageError message={error} onRetry={fetchJournals} />}

      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}
      {modal && (
        <ReviewModal journal={modal} onClose={() => setModal(null)} onSubmit={handleReview} processing={processing} />
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-book"></i>
          <h6>Pending Journal Reviews</h6>
          <span className="ms-auto badge bg-warning text-dark">{journals.length} pending</span>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : journals.length === 0 && !error ? (
            <div className="text-center py-4 text-muted">
              <i className="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
              All journals reviewed!
            </div>
          ) : journals.length === 0 ? null : journals.map(j => {
            const profile = j.internship?.student?.studentProfile
            const name = profile ? `${profile.first_name} ${profile.last_name}` : '—'
            return (
              <div key={j.id} className="p-3 border-bottom d-flex align-items-start justify-content-between">
                <div>
                  <div className="fw-semibold mb-1">
                    {name} · <span className="text-primary">Week {j.week_number ?? j.entry_number}</span>
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.82rem' }}>{j.date}</div>
                  {j.notes && <p className="mt-1 mb-0 text-muted" style={{ fontSize: '0.85rem' }}>{j.notes?.substring(0, 100)}…</p>}
                  <span className={`badge mt-1 ${j.status === 'approved' ? 'bg-success' : j.status === 'needs_revision' ? 'bg-warning text-dark' : 'bg-secondary'}`}>
                    {j.status}
                  </span>
                </div>
                <button className="btn btn-sm btn-green ms-3 flex-shrink-0" onClick={() => setModal(j)}>
                  <i className="fa fa-pen me-1"></i>Review
                </button>
              </div>
            )
          })}
        </div>
      </div>
    </Layout>
  )
}

export default FacultyJournals

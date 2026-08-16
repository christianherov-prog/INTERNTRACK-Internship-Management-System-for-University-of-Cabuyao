import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'

function ReviewModal({ journal, onClose, onSubmit, onPreview, processing }) {
  const [action, setAction]   = useState('approved')
  const [feedback, setFeedback] = useState('')
  const isImage = journal.file_path && !journal.file_path.endsWith('.pdf')
  const isPdf   = journal.file_path && journal.file_path.endsWith('.pdf')
  return (
    <div className="modal show d-block" tabIndex="-1" style={{background:'rgba(0,0,0,0.4)'}}>
      <div className="modal-dialog modal-xl modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">
              Review Journal — Week {journal.week_number ?? journal.entry_number}
            </h5>
            <button className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            <div className="mb-3 p-3 rounded" style={{background:'#f8fafc',fontSize:'0.88rem'}}>
              <div className="fw-semibold mb-1">
                Week {journal.week_number ?? journal.entry_number}
              </div>
              {journal.notes && <p className="mb-0 text-muted"><strong>Notes:</strong> {journal.notes}</p>}
            </div>

            {/* File Preview */}
            <div className="mb-3 text-center">
              <button type="button" onClick={onPreview} className="btn btn-outline-primary">
                <i className="fa fa-eye me-2"></i>Preview Journal Form
              </button>
              {isPdf && (
                <AuthenticatedFileLink path={journal.file_path} className="btn btn-outline-danger ms-2">
                  <i className="fa fa-file-pdf me-2"></i>Open PDF
                </AuthenticatedFileLink>
              )}
            </div>

            {journal.file_path && isImage && (
              <div className="mb-3 text-center">
                <AuthenticatedFileImage path={journal.file_path} alt="Journal" style={{ maxWidth: '100%', maxHeight: '500px', border: '1px solid #ddd', borderRadius: '4px' }} />
              </div>
            )}

            {!journal.file_path && (
              <div className="alert alert-info">No supplementary file uploaded for this journal entry.</div>
            )}

            <div className="mb-3">
              <label className="form-label fw-semibold">Action</label>
              <div className="d-flex gap-3">
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="approve" checked={action === 'approved'} onChange={() => setAction('approved')} />
                  <label className="form-check-label" htmlFor="approve">✅ Approve</label>
                </div>
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="revise" checked={action === 'needs_revision'} onChange={() => setAction('needs_revision')} />
                  <label className="form-check-label" htmlFor="revise">🔄 Needs Revision</label>
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
                placeholder="Write your feedback to the student…"
              ></textarea>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button
              className="btn btn-primary"
              onClick={() => onSubmit(journal.id, action, feedback)}
              disabled={processing || (action === 'needs_revision' && !feedback.trim())}
            >
              <i className={`fa fa-${processing ? 'spinner fa-spin' : 'check'} me-2`}></i>Submit Review
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function SupervisorJournalValidation() {
  const [journals, setJournals]     = useState([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage]       = useState(null)
  const [modal, setModal]           = useState(null)
  const [previewModal, setPreviewModal] = useState(null)

  const fetchJournals = () => {
    setLoading(true)
    setError(null)
    api.get('/supervisor/journals')
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
      await api.patch(`/supervisor/journals/${id}/review`, { action, feedback })
      setMessage({ type: action === 'approved' ? 'success' : 'info', text: `Journal ${action === 'approved' ? 'approved' : 'returned for revision'} successfully.` })
      setModal(null)
      fetchJournals()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Review failed.' })
    } finally { setProcessing(false) }
  }

  const handlePreviewJournal = (j) => {
    const profile = j.internship?.student?.studentProfile || j.internship?.student?.student_profile
    const name = profile ? `${profile.first_name} ${profile.last_name}` : '—'
    setPreviewModal({
      type: 'journal',
      data: {
        studentName: name,
        program: profile?.program?.code || profile?.program?.name || profile?.program || '—',
        companyName: j.internship?.company?.company_name || '—',
        weekNumber: j.week_number ?? j.entry_number,
        date: j.date,
        endDate: j.end_date,
        accomplishment: j.activities_summary,
        difficulties: j.challenges,
        insights: j.learnings,
      }
    })
  }

  return (
    <Layout title="Journal Validation" subtitle="Weekly PNC:AA-FO-31 Review" icon="fa-book-open-reader" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={fetchJournals} />}

      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}
      {modal && (
        <ReviewModal
          journal={modal}
          onClose={() => setModal(null)}
          onSubmit={handleReview}
          onPreview={() => handlePreviewJournal(modal)}
          processing={processing}
        />
      )}
      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
      />

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-book"></i>
          <h6>Pending Journal Entries</h6>
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
            const hasFile = !!j.file_path
            return (
              <div key={j.id} className="p-3 border-bottom">
                <div className="d-flex align-items-start justify-content-between">
                  <div>
                    <div className="fw-semibold mb-1">
                      {name} · <span className="text-success">Week {j.week_number ?? j.entry_number}</span>
                    </div>
                    {j.date && <div className="text-muted" style={{fontSize:'0.82rem'}}>{j.date}</div>}
                    {j.notes && (
                      <p className="mt-1 mb-0 text-muted" style={{fontSize:'0.85rem'}}>
                        {j.notes?.substring(0, 120)}{j.notes?.length > 120 ? '…' : ''}
                      </p>
                    )}
                    <div className="mt-1 d-flex align-items-center gap-2">
                      {!hasFile && <span className="badge bg-warning text-dark">No file attached</span>}
                      {hasFile && (
                        <AuthenticatedFileLink
                          path={j.file_path}
                          className="btn btn-outline-secondary btn-sm"
                          style={{fontSize:'11px', padding:'2px 8px'}}
                        >
                          <i className="fa fa-eye me-1"></i>View File
                        </AuthenticatedFileLink>
                      )}
                    </div>
                  </div>
                  <button className="btn btn-sm btn-primary ms-3 flex-shrink-0" onClick={() => setModal(j)}>
                    <i className="fa fa-pen me-1"></i>Review
                  </button>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </Layout>
  )
}

export default SupervisorJournalValidation

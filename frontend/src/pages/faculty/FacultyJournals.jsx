import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
function ReviewModal({ journal, onClose, onSubmit, onPreview, processing }) {
  const [action, setAction]     = useState('approved')
  const [feedback, setFeedback] = useState('')
  const [score, setScore]       = useState(100)
  const isImage = journal.file_path && !journal.file_path.endsWith('.pdf')
  const isPdf   = journal.file_path && journal.file_path.endsWith('.pdf')

  return (
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
                <AuthenticatedFileImage
                  path={journal.file_path}
                  alt="Journal"
                  style={{ maxWidth: '100%', maxHeight: '500px', border: '1px solid #ddd', borderRadius: '4px' }}
                />
              </div>
            )}

            {!journal.file_path && (
              <div className="alert alert-info">No supplementary file uploaded for this journal entry.</div>
            )}

            <div className="mb-3">
              <label className="form-label fw-semibold">Action</label>
              <div className="d-flex gap-3">
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="fac_approve" checked={action === 'approved'} onChange={() => setAction('approved')} />
                  <label className="form-check-label" htmlFor="fac_approve">✅ Approve</label>
                </div>
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="fac_revise" checked={action === 'needs_revision'} onChange={() => setAction('needs_revision')} />
                  <label className="form-check-label" htmlFor="fac_revise">🔄 Needs Revision</label>
                </div>
              </div>
            </div>
            {action === 'approved' && (
              <div className="mb-3">
                <label className="form-label fw-semibold">Score (0-100) <span className="text-danger">*</span></label>
                <input type="number" className="form-control" min="0" max="100" value={score} onChange={e => setScore(e.target.value)} />
              </div>
            )}
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
              className="btn btn-primary"
              onClick={() => onSubmit(journal.id, action, feedback, score)}
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

function FacultyJournals() {
  const currentTerm = useCurrentTerm()
  const [journals, setJournals]     = useState([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage]       = useState(null)
  const [modal, setModal]           = useState(null)
  const [previewModal, setPreviewModal] = useState(null)
  const [historyModal, setHistoryModal] = useState(null)
  const [historyData, setHistoryData] = useState([])
  const [loadingHistory, setLoadingHistory] = useState(false)

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

  const handleReview = async (id, action, feedback, score) => {
    setProcessing(true)
    try {
      await api.patch(`/faculty/journals/${id}/review`, { action, feedback, score })
      setMessage({ type: action === 'approved' ? 'success' : 'info', text: `Journal ${action === 'approved' ? 'approved' : 'returned for revision'}.` })
      setModal(null)
      fetchJournals()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Review failed.' })
    } finally { setProcessing(false) }
  }

  const openHistory = (studentId, studentName) => {
    setHistoryModal({ studentId, studentName })
    setLoadingHistory(true)
    api.get(`/faculty/students/${studentId}/journals`)
      .then(res => setHistoryData(res.data))
      .catch(() => alert('Failed to load history'))
      .finally(() => setLoadingHistory(false))
  }

  const handlePreview = (j) => {
    const profile = j.internship?.student?.studentProfile
    const name = profile ? `${profile.first_name} ${profile.last_name}` : '—'
    setPreviewModal({
      type: 'journal',
      data: {
        studentName: name,
        program: profile?.program?.code || '—',
        companyName: j.internship?.company?.company_name,
        companyLogoPath: j.internship?.company?.company_logo_path,
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
    <Layout title="Journals" subtitle={currentTerm} icon="fa-book" bodyClass="faculty-page">
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
          onPreview={() => handlePreview(modal)}
          processing={processing} 
        />
      )}
      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
      />
      {historyModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.45)' }}>
          <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Journal History — {historyModal.studentName}</h5>
                <button className="btn-close" onClick={() => setHistoryModal(null)}></button>
              </div>
              <div className="modal-body p-0">
                {loadingHistory ? (
                  <div className="p-5 text-center"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                ) : historyData.length === 0 ? (
                  <div className="p-4 text-center text-muted">No past journals found.</div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {historyData.map(h => (
                      <li key={h.id} className="list-group-item p-3">
                        <div className="d-flex justify-content-between">
                          <div className="fw-semibold text-primary">Week {h.week_number ?? h.entry_number}</div>
                          <span className={`badge ${h.status === 'approved' ? 'bg-success' : h.status === 'needs_revision' ? 'bg-warning text-dark' : 'bg-secondary'}`}>
                            {h.status}
                          </span>
                        </div>
                        <div className="text-muted small mb-2">{h.date} — {h.end_date}</div>
                        {h.score != null && <div className="text-success small fw-bold"><i className="fa fa-check-circle me-1"></i>Score: {h.score}/100</div>}
                        {h.faculty_feedback && (
                          <div className="bg-light p-2 rounded small mt-2">
                            <strong>Feedback:</strong> {h.faculty_feedback}
                          </div>
                        )}
                        <button className="btn btn-sm btn-outline-secondary mt-2" onClick={() => handlePreview({ ...h, internship: modal?.internship || historyData[0]?.internship })}>
                          <i className="fa fa-eye me-1"></i>Preview Form
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
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
                <div className="d-flex align-items-center gap-2 ms-3 flex-shrink-0">
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => openHistory(j.internship?.student_id, name)}>
                    <i className="fa fa-history me-1"></i>History
                  </button>
                  <button className="btn btn-sm btn-primary" onClick={() => setModal(j)}>
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

export default FacultyJournals

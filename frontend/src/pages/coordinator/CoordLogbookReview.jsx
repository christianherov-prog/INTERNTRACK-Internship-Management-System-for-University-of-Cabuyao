import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import EmptyState from '../../components/EmptyState'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

function CoordLogbookReview() {
  const currentTerm = useCurrentTerm()
  const [journals, setJournals]   = useState([])
  const [loading, setLoading]     = useState(true)
  const [error, setError]         = useState(null)
  const [processing, setProcessing] = useState(null)
  const [message, setMessage]     = useState(null)
  const [modal, setModal]         = useState(null)
  const [feedback, setFeedback]   = useState('')
  const [action, setAction]       = useState('approved')

  const fetchJournals = () => {
    setLoading(true)
    setError(null)
    api.get('/coordinator/logbook')
      .then(res => setJournals(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load journals.')
        setJournals([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchJournals() }, [])

  const openModal = (j) => { setModal(j); setFeedback(''); setAction('approved') }

  const submitReview = async () => {
    setProcessing(modal.id)
    try {
      await api.patch(`/coordinator/logbook/${modal.id}/review`, { action, feedback })
      setMessage({ type: action === 'approved' ? 'success' : 'info', text: `Journal ${action} successfully.` })
      setModal(null)
      fetchJournals()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Review failed.' })
    } finally { setProcessing(null) }
  }

  return (
    <Layout title="Logbook Review" subtitle={currentTerm} icon="fa-book-open" bodyClass="coordinator-page">
      {error && <PageError message={error} onRetry={fetchJournals} />}

      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

      {/* Review Modal */}
      {modal && (
        <div className="modal show d-block" tabIndex="-1" style={{background:'rgba(0,0,0,0.4)'}}>
          <div className="modal-dialog modal-lg modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header"><h5 className="modal-title">Review Journal Entry #{modal.entry_number}</h5><button className="btn-close" onClick={() => setModal(null)}></button></div>
              <div className="modal-body">
                <div className="mb-3 p-3 rounded" style={{background:'#f8fafc',fontSize:'0.88rem'}}>
                  <div className="fw-semibold mb-2">
                    {modal.internship?.student?.studentProfile ? `${modal.internship.student.studentProfile.first_name} ${modal.internship.student.studentProfile.last_name}` : '—'} · {modal.date}
                  </div>
                  <p className="mb-1"><strong>Activities:</strong> {modal.activities_summary}</p>
                  {modal.learnings && <p className="mb-1 text-muted"><strong>Learnings:</strong> {modal.learnings}</p>}
                  {modal.supervisor_feedback && <div className="mt-2 p-2 rounded" style={{background:'#f0fdf4',fontSize:'0.82rem',color:'#15803d'}}><strong>Supervisor Feedback:</strong> {modal.supervisor_feedback}</div>}
                </div>
                <div className="mb-3">
                  <label className="form-label fw-semibold">Decision</label>
                  <div className="d-flex gap-3">
                    <div className="form-check"><input type="radio" className="form-check-input" id="coordApprove" checked={action==='approved'} onChange={()=>setAction('approved')}/><label className="form-check-label" htmlFor="coordApprove">✅ Approve</label></div>
                    <div className="form-check"><input type="radio" className="form-check-input" id="coordRevise" checked={action==='needs_revision'} onChange={()=>setAction('needs_revision')}/><label className="form-check-label" htmlFor="coordRevise">🔄 Needs Revision</label></div>
                  </div>
                </div>
                <div>
                  <label className="form-label fw-semibold">Remarks / Feedback</label>
                  <textarea className="form-control" rows={3} value={feedback} onChange={e=>setFeedback(e.target.value)} placeholder="Optional feedback for the student…"></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setModal(null)}>Cancel</button>
                <button className="btn btn-primary" onClick={submitReview} disabled={processing === modal.id}>
                  <i className={`fa fa-${processing === modal.id ? 'spinner fa-spin' : 'check'} me-2`}></i>Submit
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-list"></i><h6>Submitted Journal Entries</h6>
          <span className="ms-auto badge bg-warning text-dark">{journals.length} pending</span>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : journals.length === 0 && !error ? (
            <EmptyState icon="fa-check-circle" title="No pending journals" message="All submitted journal entries have been reviewed." />
          ) : journals.length === 0 ? null : journals.map(j => {
            const profile = j.internship?.student?.studentProfile
            const name = profile ? `${profile.first_name} ${profile.last_name}` : '—'
            return (
              <div key={j.id} className="p-3 border-bottom">
                <div className="d-flex align-items-center justify-content-between">
                  <div>
                    <div className="fw-semibold">{name} · Entry #{j.entry_number}</div>
                    <div className="text-muted" style={{fontSize:'0.82rem'}}>{new Date(j.date).toLocaleDateString('en-PH',{weekday:'long',month:'long',day:'numeric'})}</div>
                    <p className="mb-0 mt-1" style={{fontSize:'0.85rem'}}>{j.activities_summary?.substring(0,100)}…</p>
                  </div>
                  <button className="btn btn-sm btn-outline-primary ms-3 flex-shrink-0" onClick={() => openModal(j)}>
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

export default CoordLogbookReview

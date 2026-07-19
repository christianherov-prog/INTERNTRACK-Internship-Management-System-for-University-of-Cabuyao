import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function StudentLogbook() {
  const [journals, setJournals] = useState([])
  const [loading, setLoading]   = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage]   = useState(null)
  const [showForm, setShowForm] = useState(false)
  
  const [form, setForm] = useState({ week_number: '', hours_declared: '', notes: '', file: null })

  const fetchJournals = () => {
    setLoading(true)
    api.get('/student/logbook')
      .then(res => setJournals(unwrapList(res.data).items))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchJournals() }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.file) {
        setMessage({ type: 'danger', text: 'Please upload your completed PNC:AA-FO-31 document.' })
        return
    }
    
    setSubmitting(true); setMessage(null)
    try {
      const formData = new FormData()
      formData.append('week_number', form.week_number)
      formData.append('hours_declared', form.hours_declared)
      if (form.notes) formData.append('notes', form.notes)
      formData.append('file', form.file)

      await api.post('/student/logbook', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
      })
      
      setMessage({ type: 'success', text: 'Weekly journal submitted successfully!' })
      setShowForm(false)
      setForm({ week_number: '', hours_declared: '', notes: '', file: null })
      fetchJournals()
    } catch (e) {
      setMessage({ type: 'danger', text: e.response?.data?.message ?? 'Submission failed.' })
    } finally { setSubmitting(false) }
  }

  const statusBadge = (s) => {
    const map = { submitted: 'badge-pending', approved: 'badge-active', needs_revision: 'badge-inactive', draft: 'badge-pending' }
    const label = { submitted: 'Submitted', approved: 'Approved', needs_revision: 'Needs Revision', draft: 'Draft' }
    return <span className={`badge-status ${map[s] ?? 'badge-pending'}`}>{label[s] ?? s}</span>
  }

  return (
    <Layout title="Weekly Journals" subtitle="PNC:AA-FO-31 Uploads" icon="fa-folder-open" bodyClass="student-page">
      {message && <div className={`alert alert-${message.type} mb-3`}>{message.text}</div>}

      <div className="d-flex justify-content-between align-items-center mb-3">
        <p className="text-muted mb-0">Upload your completed <strong>PNC:AA-FO-31 Weekly Daily Journal</strong> per week.</p>
        <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
          <i className={`fa fa-${showForm ? 'times' : 'upload'} me-2`}></i>{showForm ? 'Cancel' : 'Upload Journal'}
        </button>
      </div>

      {/* Submit Form */}
      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header"><i className="fa fa-cloud-upload-alt"></i><h6>Upload Weekly Journal</h6></div>
          <form className="p-3" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label fw-semibold">WEEK: <span className="text-danger">*</span></label>
                <input type="number" className="form-control" placeholder="e.g. 1" value={form.week_number} min={1} max={52} onChange={e => setForm(p => ({...p, week_number: e.target.value}))} required />
              </div>
              <div className="col-md-6">
                <label className="form-label fw-semibold">Hours Rendered This Week: <span className="text-danger">*</span></label>
                <input type="number" className="form-control" placeholder="e.g. 40" value={form.hours_declared} min={1} max={60} step={0.5} onChange={e => setForm(p => ({...p, hours_declared: e.target.value}))} required />
              </div>
              <div className="col-12">
                <label className="form-label fw-semibold">PNC:AA-FO-31 Document <span className="text-danger">*</span></label>
                <input type="file" className="form-control" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" onChange={e => setForm(p => ({...p, file: e.target.files[0]}))} required />
                <small className="text-muted">Accepts PDF, Word, or Image files.</small>
              </div>
              <div className="col-12">
                <label className="form-label fw-semibold">Notes / Remarks (Optional)</label>
                <textarea className="form-control" rows={2} placeholder="Any notes regarding this week's journal..." value={form.notes} onChange={e => setForm(p => ({...p, notes: e.target.value}))}></textarea>
              </div>
            </div>
            <div className="mt-3">
              <button type="submit" className="btn btn-success me-2" disabled={submitting}>
                <i className="fa fa-check me-2"></i>{submitting ? 'Uploading…' : 'Submit Journal'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Journal List */}
      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-list"></i><h6>Submitted Weekly Journals</h6></div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : journals.length === 0 ? (
            <div className="text-center py-4 text-muted">No weekly journals uploaded yet.</div>
          ) : journals.map(j => (
            <div key={j.id} className="p-3 border-bottom">
              <div className="d-flex align-items-start justify-content-between">
                <div>
                  <div className="fw-semibold mb-1" style={{fontSize:'1.05rem'}}>
                    <i className="fa fa-calendar-week me-2 text-primary"></i>Week {j.week_number}
                  </div>
                  {j.notes && <p className="text-muted mb-2" style={{fontSize:'0.88rem'}}><strong>Notes:</strong> {j.notes}</p>}
                  
                  {j.file_path && (
                      <a href={`http://localhost:8001/storage/${j.file_path}`} target="_blank" rel="noreferrer" className="btn btn-sm btn-outline-secondary mt-1">
                          <i className="fa fa-file-download me-1"></i> View Document
                      </a>
                  )}

                  {j.supervisor_feedback && (
                    <div className="mt-2 p-2 rounded" style={{background:'#f0fdf4',fontSize:'0.82rem',color:'#15803d'}}>
                      <i className="fa fa-comment-dots me-1"></i><strong>Feedback:</strong> {j.supervisor_feedback}
                    </div>
                  )}
                </div>
                <div className="ms-3 text-end">
                  {statusBadge(j.status)}
                  <div className="text-muted mt-1" style={{fontSize:'0.85rem'}}><strong>{j.hours_declared} hrs</strong></div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </Layout>
  )
}

export default StudentLogbook

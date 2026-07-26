import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

const STATUS_MAP = {
  submitted:      { cls: 'badge-pending',  label: 'Submitted' },
  approved:       { cls: 'badge-active',   label: 'Approved' },
  needs_revision: { cls: 'badge-inactive', label: 'Needs Revision' },
  draft:          { cls: 'badge-pending',  label: 'Draft' },
}

const EMPTY_FORM = {
  week_number:        '',
  date:               '',
  activities_summary: '',   // ACCOMPLISHMENT
  challenges:         '',   // DIFFICULTIES ENCOUNTERED
  learnings:          '',   // NEW LEARNING / INSIGHTS
  notes:              '',
}

function StudentLogbook() {
  const [journals, setJournals]       = useState([])
  const [loading, setLoading]         = useState(true)
  const [error, setError]             = useState(null)
  const [submitting, setSubmitting]   = useState(false)
  const [generating, setGenerating]   = useState(null) // week_number being generated
  const [message, setMessage]         = useState(null)
  const [showForm, setShowForm]       = useState(false)
  const [editEntry, setEditEntry]     = useState(null) // journal entry being edited
  const [form, setForm]               = useState(EMPTY_FORM)

  const fetchJournals = () => {
    setLoading(true)
    setError(null)
    api.get('/student/logbook')
      .then(res => setJournals(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load journals.')
        setJournals([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchJournals() }, [])

  // â”€â”€ Form helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  const openNewEntry = () => {
    setForm(EMPTY_FORM)
    setEditEntry(null)
    setShowForm(true)
    setMessage(null)
  }

  const openEditEntry = (j) => {
    setForm({
      week_number:        j.week_number ?? '',
      date:               j.date ?? '',
      activities_summary: j.activities_summary ?? '',
      challenges:         j.challenges ?? '',
      learnings:          j.learnings ?? '',
      notes:              j.notes ?? '',
    })
    setEditEntry(j)
    setShowForm(true)
    setMessage(null)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const handleChange = e =>
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.activities_summary && !form.challenges && !form.learnings) {
      setMessage({ type: 'danger', text: 'Please fill in at least one journal field.' })
      return
    }
    setSubmitting(true)
    setMessage(null)
    try {
      await api.post('/student/logbook', form)
      setMessage({ type: 'success', text: `Week ${form.week_number} journal saved successfully!` })
      setShowForm(false)
      setForm(EMPTY_FORM)
      setEditEntry(null)
      fetchJournals()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Submission failed.' })
    } finally {
      setSubmitting(false)
    }
  }

  // â”€â”€ PDF Generation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  const generatePdf = async (weekNumber, internshipId) => {
    setGenerating(weekNumber)
    try {
      const resp = await api.get('/student/journal/generate', {
        params: { internship_id: internshipId, week_number: weekNumber },
        responseType: 'blob',
      })
      const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href  = url
      link.download = `Journal_Week${weekNumber}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch {
      setMessage({ type: 'danger', text: 'Failed to generate PDF. Please try again.' })
    } finally {
      setGenerating(null)
    }
  }

  // â”€â”€â”€ Render â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  const statusBadge = (s) => {
    const { cls, label } = STATUS_MAP[s] ?? { cls: 'badge-pending', label: s }
    return <span className={`badge-status ${cls}`}>{label}</span>
  }

  return (
    <Layout
      title="Weekly Journal (Form 31)"
      subtitle="Encode your weekly journal directly — the system generates PNC:AA-FO-31 automatically"
      icon="fa-book-open"
      bodyClass="student-page"
    >
      {error && <PageError message={error} onRetry={fetchJournals} />}
      {message && (
        <div className={`alert alert-${message.type} mb-3 d-flex align-items-center gap-2`}>
          <i className={`fa fa-${message.type === 'success' ? 'check-circle' : 'exclamation-circle'}`}></i>
          {message.text}
        </div>
      )}

      {/* Action bar */}
      <div className="d-flex justify-content-end align-items-center mb-4 gap-3 flex-wrap">
        <button className="btn btn-green" onClick={openNewEntry}>
          <i className="fa fa-plus me-2"></i>New Journal Entry
        </button>
      </div>

      {/* â”€â”€ Entry Form â”€â”€ */}
      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header">
            <i className="fa fa-pen-to-square"></i>
            <h6>{editEntry ? `Edit — Week ${editEntry.week_number}` : 'New Weekly Journal Entry'}</h6>
          </div>
          <form className="p-3 p-md-4" onSubmit={handleSubmit}>
            <div className="row g-3 mb-3">
              <div className="col-md-3">
                <label className="form-label fw-semibold">
                  Week No. <span className="text-danger">*</span>
                </label>
                <input
                  type="number" name="week_number" className="form-control"
                  placeholder="e.g. 1" min={1} max={52}
                  value={form.week_number} onChange={handleChange} required
                />
              </div>
              <div className="col-md-9">
                <label className="form-label fw-semibold">
                  Week Date <span className="text-danger">*</span>
                </label>
                <input
                  type="date" name="date" className="form-control"
                  value={form.date} onChange={handleChange} required
                />
              </div>
            </div>

            {/* Three-column journal fields matching Form 31 */}
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label fw-semibold text-success">
                  <i className="fa fa-check-square me-1"></i>
                  Accomplishment <span className="text-danger">*</span>
                </label>
                <textarea
                  name="activities_summary" className="form-control" rows={8}
                  placeholder="What did you accomplish this week? List your tasks and achievements..."
                  value={form.activities_summary} onChange={handleChange} required
                />
                <small className="text-muted">Mapped to: "ACCOMPLISHMENT" column in Form 31</small>
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-danger">
                  <i className="fa fa-exclamation-triangle me-1"></i>
                  Difficulties Encountered
                </label>
                <textarea
                  name="challenges" className="form-control" rows={8}
                  placeholder="What challenges or problems did you encounter this week?..."
                  value={form.challenges} onChange={handleChange}
                />
                <small className="text-muted">Mapped to: "DIFFICULTIES ENCOUNTERED" column in Form 31</small>
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-primary">
                  <i className="fa fa-lightbulb me-1"></i>
                  New Learning / Insights
                </label>
                <textarea
                  name="learnings" className="form-control" rows={8}
                  placeholder="What new skills or knowledge did you gain this week?..."
                  value={form.learnings} onChange={handleChange}
                />
                <small className="text-muted">Mapped to: "NEW LEARNING / INSIGHTS" column in Form 31</small>
              </div>
            </div>

            <div className="mt-3">
              <label className="form-label fw-semibold">Notes / Remarks (Optional)</label>
              <textarea
                name="notes" className="form-control" rows={2}
                placeholder="Any additional remarks..."
                value={form.notes} onChange={handleChange}
              />
            </div>

            <div className="mt-4 d-flex gap-2">
              <button type="submit" className="btn btn-success" disabled={submitting}>
                <i className="fa fa-save me-2"></i>
                {submitting ? 'Saving…' : 'Save Journal Entry'}
              </button>
              <button
                type="button" className="btn btn-outline-secondary"
                onClick={() => { setShowForm(false); setEditEntry(null) }}
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* â”€â”€ Journal List â”€â”€ */}
      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-list-ul"></i>
          <h6>My Weekly Journal Entries</h6>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-5">
              <i className="fa fa-spinner fa-spin fa-2x text-muted"></i>
            </div>
          ) : journals.length === 0 && !error ? (
            <div className="text-center py-5 text-muted">
              <i className="fa fa-book-open fa-3x mb-3 d-block opacity-25"></i>
              No journal entries yet. Click <strong>New Journal Entry</strong> to get started.
            </div>
          ) : journals.map(j => (
            <div key={j.id} className="p-3 border-bottom">
              <div className="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="fw-semibold mb-1" style={{ fontSize: '1.05rem' }}>
                    <i className="fa fa-calendar-week me-2 text-primary"></i>
                    Week {j.week_number}
                    {j.date && (
                      <span className="text-muted fw-normal ms-2" style={{ fontSize: '0.88rem' }}>
                        — {new Date(j.date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                      </span>
                    )}
                  </div>

                  {/* Preview snippets */}
                  <div className="row g-2 mt-1">
                    {j.activities_summary && (
                      <div className="col-md-4">
                        <div className="p-2 rounded" style={{ background: '#f0fdf4', fontSize: '0.82rem' }}>
                          <strong className="text-success">Accomplishment:</strong>
                          <p className="mb-0 text-truncate" style={{ maxWidth: 240 }}>{j.activities_summary}</p>
                        </div>
                      </div>
                    )}
                    {j.challenges && (
                      <div className="col-md-4">
                        <div className="p-2 rounded" style={{ background: '#fff5f5', fontSize: '0.82rem' }}>
                          <strong className="text-danger">Difficulties:</strong>
                          <p className="mb-0 text-truncate" style={{ maxWidth: 240 }}>{j.challenges}</p>
                        </div>
                      </div>
                    )}
                    {j.learnings && (
                      <div className="col-md-4">
                        <div className="p-2 rounded" style={{ background: '#eff6ff', fontSize: '0.82rem' }}>
                          <strong className="text-primary">Insights:</strong>
                          <p className="mb-0 text-truncate" style={{ maxWidth: 240 }}>{j.learnings}</p>
                        </div>
                      </div>
                    )}
                  </div>

                  {j.supervisor_feedback && (
                    <div className="mt-2 p-2 rounded" style={{ background: '#f0fdf4', fontSize: '0.82rem', color: '#15803d' }}>
                      <i className="fa fa-comment-dots me-1"></i>
                      <strong>Feedback:</strong> {j.supervisor_feedback}
                    </div>
                  )}
                </div>

                {/* Actions */}
                <div className="d-flex flex-column align-items-end gap-2 ms-2">
                  {statusBadge(j.status)}
                  <div className="d-flex gap-1 flex-wrap justify-content-end mt-1">
                    <button
                      className="btn btn-sm btn-outline-secondary"
                      onClick={() => openEditEntry(j)}
                      title="Edit this journal entry"
                    >
                      <i className="fa fa-edit me-1"></i>Edit
                    </button>
                    <button
                      className="btn btn-sm btn-outline-danger"
                      onClick={() => generatePdf(j.week_number, j.internship_id)}
                      disabled={generating === j.week_number}
                      title="Generate Form 31 PDF"
                    >
                      <i className={`fa fa-${generating === j.week_number ? 'spinner fa-spin' : 'file-pdf'} me-1`}></i>
                      {generating === j.week_number ? 'Generating…' : 'PDF (FO-31)'}
                    </button>
                  </div>
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

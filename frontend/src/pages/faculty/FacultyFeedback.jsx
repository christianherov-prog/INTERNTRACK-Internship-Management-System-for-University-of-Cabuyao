import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function studentName(entry) {
  const p = entry?.internship?.student?.student_profile
    || entry?.internship?.student?.studentProfile
    || entry?.student?.student_profile
    || entry?.student?.studentProfile
  if (p) return `${p.first_name || ''} ${p.last_name || ''}`.trim()
  return entry?.internship?.student?.username || entry?.student?.username || '—'
}

function FacultyFeedback() {
  const [feedbackRows, setFeedbackRows] = useState([])
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [internshipId, setInternshipId] = useState('')
  const [feedback, setFeedback] = useState('')
  const [saving, setSaving] = useState(false)

  const load = () => {
    setLoading(true)
    setError(null)
    Promise.all([
      api.get('/faculty/feedback'),
      api.get('/faculty/assigned-students'),
    ])
      .then(([fbRes, stRes]) => {
        setFeedbackRows(unwrapList(fbRes.data).items)
        setStudents(unwrapList(stRes.data).items)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load feedback.')
        setFeedbackRows([])
        setStudents([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!internshipId || feedback.trim().length < 5) {
      setMessage({ type: 'danger', text: 'Select a student and enter feedback (at least 5 characters).' })
      return
    }
    setSaving(true)
    setMessage(null)
    try {
      await api.post(`/faculty/feedback/${internshipId}`, { feedback: feedback.trim() })
      setMessage({ type: 'success', text: 'Feedback submitted.' })
      setFeedback('')
      setInternshipId('')
      load()
    } catch (err) {
      setMessage({
        type: 'danger',
        text: err.response?.data?.message || 'Failed to submit feedback.',
      })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Layout title="Feedback" subtitle="AY 2024-2025, Sem 2" icon="fa-comment-dots" bodyClass="faculty-page">
      {error && <PageError message={error} onRetry={load} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-pen"></i>
          <h6>Submit Feedback</h6>
        </div>
        <form className="p-3" onSubmit={handleSubmit}>
          <div className="row g-3">
            <div className="col-md-5">
              <label className="form-label">Student</label>
              <select
                className="form-select"
                value={internshipId}
                onChange={(e) => setInternshipId(e.target.value)}
                required
              >
                <option value="">Select assigned student…</option>
                {students.map((row) => (
                  <option key={row.id} value={row.id}>
                    {studentName(row)} ({row.student?.username || '—'})
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-7">
              <label className="form-label">Feedback</label>
              <textarea
                className="form-control"
                rows={3}
                value={feedback}
                onChange={(e) => setFeedback(e.target.value)}
                placeholder="Write guidance or remarks for the student…"
                required
                minLength={5}
              />
            </div>
          </div>
          <div className="mt-3">
            <button type="submit" className="btn-green" disabled={saving}>
              {saving ? <i className="fa fa-spinner fa-spin me-2"></i> : <i className="fa fa-paper-plane me-2"></i>}
              Submit Feedback
            </button>
          </div>
        </form>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-comment-dots"></i>
          <h6>Recent Feedback</h6>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Week</th>
                    <th>Feedback</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  {feedbackRows.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="text-center text-muted py-4">No feedback submitted yet.</td>
                    </tr>
                  ) : feedbackRows.map((row) => (
                    <tr key={row.id}>
                      <td className="fw-semibold">{studentName(row)}</td>
                      <td>{row.week_number ?? row.entry_number ?? '—'}</td>
                      <td style={{ maxWidth: 360 }}>{row.faculty_feedback}</td>
                      <td style={{ fontSize: '0.85rem' }}>
                        {row.faculty_reviewed_at
                          ? new Date(row.faculty_reviewed_at).toLocaleDateString()
                          : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default FacultyFeedback

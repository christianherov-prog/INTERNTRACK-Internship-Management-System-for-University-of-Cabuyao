import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

import { formatStudentName } from '../../utils/formatName'
import { useCachedPage } from '../../hooks/useCachedPage'
import InternTrackLoader from '../../components/InternTrackLoader'
import { formatManilaDateTime } from '../../utils/manilaTime'
import { cacheDelete } from '../../utils/pageCache'

function studentName(entry) {
  if (entry?.internship?.student) return formatStudentName(entry.internship.student)
  if (entry?.student) return formatStudentName(entry.student)
  return entry?.internship?.student?.username || entry?.student?.username || '—'
}

/** Narrative feedback — mirrors Faculty Feedback pattern. */
function SupervisorFeedback() {
  const { loading, seed, run } = useCachedPage('supervisor:feedback')
  const [feedbackRows, setFeedbackRows] = useState(() => seed?.feedbackRows ?? [])
  const [students, setStudents] = useState(() => seed?.students ?? [])
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [internshipId, setInternshipId] = useState('')
  const [feedback, setFeedback] = useState('')
  const [saving, setSaving] = useState(false)
  const [search, setSearch] = useState('')
  const [editingId, setEditingId] = useState(null)
  const [editText, setEditText] = useState('')
  const [rowBusy, setRowBusy] = useState(null)

  const load = () => {
    setError(null)
    run(() => Promise.all([
      api.get('/supervisor/feedback'),
      api.get('/supervisor/assigned-students'),
    ]).then(([fbRes, stRes]) => ({
      feedbackRows: unwrapList(fbRes.data).items,
      students: unwrapList(stRes.data).items,
    })))
      .then((next) => {
        if (next) {
          setFeedbackRows(next.feedbackRows)
          setStudents(next.students)
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load feedback.')
        setFeedbackRows([])
        setStudents([])
      })
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
      await api.post(`/supervisor/feedback/${internshipId}`, { feedback: feedback.trim() })
      setMessage({ type: 'success', text: 'Feedback submitted.' })
      setFeedback('')
      setInternshipId('')
      cacheDelete('supervisor:feedback')
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

  const startEdit = (row) => {
    setEditingId(row.id)
    setEditText(row.supervisor_feedback || row.feedback || '')
  }

  const saveEdit = async (id) => {
    if (editText.trim().length < 5) {
      setMessage({ type: 'danger', text: 'Feedback must be at least 5 characters.' })
      return
    }
    setRowBusy(`edit-${id}`)
    try {
      await api.patch(`/supervisor/feedback/${id}`, { feedback: editText.trim() })
      setMessage({ type: 'success', text: 'Feedback updated.' })
      setEditingId(null)
      cacheDelete('supervisor:feedback')
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to update feedback.' })
    } finally {
      setRowBusy(null)
    }
  }

  const removeFeedback = async (id) => {
    setRowBusy(`del-${id}`)
    try {
      await api.delete(`/supervisor/feedback/${id}`)
      setMessage({ type: 'success', text: 'Feedback removed.' })
      cacheDelete('supervisor:feedback')
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to remove feedback.' })
    } finally {
      setRowBusy(null)
    }
  }

  return (
    <Layout title="Feedback" subtitle="Industry Supervisor" icon="fa-comment-dots" bodyClass="supervisor-page">
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
                placeholder="Performance observation, strengths, areas for improvement, or general internship feedback"
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
        
        {/* Filters */}
        <div className="p-3 border-bottom bg-light">
          <div className="input-group input-group-sm" style={{ width: 260 }}>
            <span className="input-group-text bg-white text-muted border-end-0"><i className="fa fa-search"></i></span>
            <input className="form-control border-start-0 ps-0" placeholder="Search Students" value={search} onChange={e => setSearch(e.target.value)} />
          </div>
        </div>

        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><InternTrackLoader /></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Feedback</th>
                    <th>Date</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {(() => {
                    const filtered = feedbackRows.filter(row => {
                      if (!search) return true
                      return studentName(row).toLowerCase().includes(search.toLowerCase())
                    })
                    
                    if (filtered.length === 0) {
                      return (
                        <tr>
                          <td colSpan={4} className="text-center text-muted py-4">No feedback matches your search.</td>
                        </tr>
                      )
                    }

                    return filtered.map((row) => (
                    <tr key={row.id}>
                      <td className="fw-semibold">{studentName(row)}</td>
                      <td style={{ maxWidth: 360 }}>
                        {editingId === row.id ? (
                          <textarea
                            className="form-control form-control-sm"
                            rows={3}
                            value={editText}
                            onChange={(e) => setEditText(e.target.value)}
                          />
                        ) : (
                          row.supervisor_feedback || row.feedback
                        )}
                      </td>
                      <td style={{ fontSize: '0.85rem' }}>
                        {row.supervisor_reviewed_at_manila
                          || formatManilaDateTime(row.supervisor_reviewed_at)}
                      </td>
                      <td className="text-nowrap">
                        {editingId === row.id ? (
                          <>
                            <button
                              type="button"
                              className="btn btn-sm btn-primary me-1"
                              disabled={rowBusy === `edit-${row.id}`}
                              onClick={() => saveEdit(row.id)}
                            >
                              {rowBusy === `edit-${row.id}` ? 'Saving...' : 'Save'}
                            </button>
                            <button type="button" className="btn btn-sm btn-outline-secondary" disabled={!!rowBusy} onClick={() => setEditingId(null)}>
                              Cancel
                            </button>
                          </>
                        ) : (
                          <>
                            <button type="button" className="btn btn-sm btn-outline-secondary me-1" disabled={!!rowBusy} onClick={() => startEdit(row)}>
                              Edit
                            </button>
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-danger"
                              disabled={rowBusy === `del-${row.id}`}
                              onClick={() => removeFeedback(row.id)}
                            >
                              {rowBusy === `del-${row.id}` ? 'Removing...' : 'Remove'}
                            </button>
                          </>
                        )}
                      </td>
                    </tr>
                    ))
                  })()}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default SupervisorFeedback

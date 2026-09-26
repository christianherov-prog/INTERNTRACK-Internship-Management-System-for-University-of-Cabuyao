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
import { useConfirm } from '../../contexts/ConfirmContext'
import { FEEDBACK_MAX_LENGTH, FEEDBACK_MIN_LENGTH } from '../../config/feedback'

function studentName(entry) {
  if (entry?.internship?.student) return formatStudentName(entry.internship.student)
  if (entry?.student) return formatStudentName(entry.student)
  return entry?.internship?.student?.username || entry?.student?.username || '—'
}

function mergeFeedbackRow(rows, next) {
  if (!next?.id) return rows
  const rest = rows.filter((r) => r.id !== next.id && r.internship_id !== next.internship_id)
  return [next, ...rest]
}

/** Narrative feedback — mirrors Faculty Feedback pattern. */
function SupervisorFeedback() {
  const confirm = useConfirm()
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
        setError(err.response?.data?.message || 'Unable to load feedback. Please try again.')
        setFeedbackRows([])
        setStudents([])
      })
  }

  useEffect(() => { load() }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    const text = feedback.trim()
    if (!internshipId || text.length < FEEDBACK_MIN_LENGTH) {
      setMessage({
        type: 'danger',
        text: `Select a student and enter feedback (at least ${FEEDBACK_MIN_LENGTH} characters).`,
      })
      return
    }
    if (text.length > FEEDBACK_MAX_LENGTH) {
      setMessage({ type: 'danger', text: `Feedback cannot exceed ${FEEDBACK_MAX_LENGTH} characters.` })
      return
    }
    setSaving(true)
    setMessage(null)
    try {
      const res = await api.post(`/supervisor/feedback/${internshipId}`, { feedback: text })
      const saved = res.data?.feedback
      setMessage({ type: 'success', text: res.data?.message || 'Feedback submitted.' })
      setFeedback('')
      setInternshipId('')
      if (saved) {
        setFeedbackRows((prev) => mergeFeedbackRow(prev, saved))
      }
      cacheDelete('supervisor:feedback')
      load()
    } catch (err) {
      const validation = err.response?.data?.errors?.feedback?.[0]
      setMessage({
        type: 'danger',
        text: validation
          || err.response?.data?.message
          || 'Unable to submit feedback. Please try again.',
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
    const text = editText.trim()
    if (text.length < FEEDBACK_MIN_LENGTH) {
      setMessage({ type: 'danger', text: `Feedback must be at least ${FEEDBACK_MIN_LENGTH} characters.` })
      return
    }
    if (text.length > FEEDBACK_MAX_LENGTH) {
      setMessage({ type: 'danger', text: `Feedback cannot exceed ${FEEDBACK_MAX_LENGTH} characters.` })
      return
    }
    setRowBusy(`edit-${id}`)
    try {
      const res = await api.patch(`/supervisor/feedback/${id}`, { feedback: text })
      const saved = res.data?.feedback
      setMessage({ type: 'success', text: res.data?.message || 'Feedback updated.' })
      setEditingId(null)
      if (saved) {
        setFeedbackRows((prev) => mergeFeedbackRow(prev, saved))
      }
      cacheDelete('supervisor:feedback')
      load()
    } catch (err) {
      const validation = err.response?.data?.errors?.feedback?.[0]
      setMessage({
        type: 'danger',
        text: validation || err.response?.data?.message || 'Unable to update feedback. Please try again.',
      })
    } finally {
      setRowBusy(null)
    }
  }

  const removeFeedback = async (row) => {
    const name = studentName(row)
    await confirm({
      title: 'Remove supervisor feedback?',
      message: `Remove your feedback for ${name}? This cannot be undone from the student view.`,
      confirmLabel: 'Remove Feedback',
      variant: 'danger',
      run: async () => {
        setRowBusy(`del-${row.id}`)
        try {
          await api.delete(`/supervisor/feedback/${row.id}`)
          setMessage({ type: 'success', text: 'Feedback removed.' })
          setFeedbackRows((prev) => prev.filter((r) => r.id !== row.id))
          cacheDelete('supervisor:feedback')
          load()
        } catch (err) {
          setMessage({ type: 'danger', text: err.response?.data?.message || 'Unable to remove feedback. Please try again.' })
          throw err
        } finally {
          setRowBusy(null)
        }
      },
    })
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
                    {studentName(row)} ({row.student?.student_profile?.student_number || row.student?.username || '—'})
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-7">
              <div className="d-flex justify-content-between align-items-center">
                <label className="form-label mb-1">Feedback</label>
                <small className={`text-muted ${feedback.length >= FEEDBACK_MAX_LENGTH ? 'text-danger' : ''}`}>
                  {feedback.length} / {FEEDBACK_MAX_LENGTH}
                </small>
              </div>
              <textarea
                className="form-control"
                rows={3}
                value={feedback}
                onChange={(e) => setFeedback(e.target.value.slice(0, FEEDBACK_MAX_LENGTH))}
                placeholder="Performance observation, strengths, areas for improvement, or general internship feedback"
                required
                minLength={FEEDBACK_MIN_LENGTH}
                maxLength={FEEDBACK_MAX_LENGTH}
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

        <div className="p-3 border-bottom bg-light">
          <div className="input-group input-group-sm" style={{ width: 260 }}>
            <span className="input-group-text bg-white text-muted border-end-0"><i className="fa fa-search"></i></span>
            <input maxLength={100} className="form-control border-start-0 ps-0" placeholder="Search Students" value={search} onChange={e => setSearch(e.target.value)} />
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
                          <>
                            <textarea
                              className="form-control form-control-sm"
                              rows={3}
                              value={editText}
                              onChange={(e) => setEditText(e.target.value.slice(0, FEEDBACK_MAX_LENGTH))}
                              maxLength={FEEDBACK_MAX_LENGTH}
                            />
                            <small className="text-muted">{editText.length} / {FEEDBACK_MAX_LENGTH}</small>
                          </>
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
                              onClick={() => removeFeedback(row)}
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

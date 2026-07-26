import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapGroups } from '../../utils/apiList'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

const COMPETENCIES = [
  { key: 'technical_skills', label: 'Technical Skills' },
  { key: 'communication_skills', label: 'Communication Skills' },
  { key: 'teamwork', label: 'Teamwork' },
  { key: 'initiative', label: 'Initiative' },
  { key: 'work_ethics', label: 'Work Ethics' },
  { key: 'attendance_punctuality', label: 'Attendance & Punctuality' },
  { key: 'adaptability', label: 'Adaptability' },
  { key: 'problem_solving', label: 'Problem Solving' },
]

const RATING_LABELS = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Very Good', 5: 'Excellent' }

function profileOf(entity) {
  return entity?.student?.student_profile || entity?.student?.studentProfile || null
}

function displayName(entity) {
  const p = profileOf(entity)
  if (p) return `${p.first_name || ''} ${p.last_name || ''}`.trim()
  return entity?.student?.username || '—'
}

function EvalModal({ internship, onClose, onSubmit, processing }) {
  const [period, setPeriod] = useState('midterm')
  const [scores, setScores] = useState({})
  const [comments, setComments] = useState('')

  const setScore = (key, val) => setScores((prev) => ({ ...prev, [key]: val }))
  const allFilled = COMPETENCIES.every((c) => scores[c.key])
  const canSubmit = allFilled
  const name = displayName(internship)

  const handleLocalSubmit = async () => {
    if (!canSubmit || processing) return

    const fd = new FormData()
    fd.append('evaluation_period', period)
    COMPETENCIES.forEach((c) => fd.append(c.key, String(scores[c.key])))
    if (comments.trim()) fd.append('general_comments', comments.trim())
    onSubmit(internship.id, fd, period)
  }

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.45)' }}>
      <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">
              <i className="fa fa-star me-2 text-warning"></i>
              Supervisor Evaluation — {name}
            </h5>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            <div className="mb-3">
              <label className="form-label fw-semibold">Evaluation Period</label>
              <div className="d-flex gap-3">
                {['midterm', 'final'].map((p) => (
                  <div key={p} className="form-check">
                    <input
                      type="radio"
                      className="form-check-input"
                      id={`sup_period_${p}`}
                      checked={period === p}
                      onChange={() => setPeriod(p)}
                    />
                    <label className="form-check-label text-capitalize" htmlFor={`sup_period_${p}`}>{p}</label>
                  </div>
                ))}
              </div>
            </div>
            <div className="table-responsive mb-3">
              <table className="table table-bordered table-sm align-middle">
                <thead className="table-light">
                  <tr>
                    <th>Competency</th>
                    {[1, 2, 3, 4, 5].map((n) => (
                      <th key={n} className="text-center">
                        {n}<br /><small className="text-muted fw-normal">{RATING_LABELS[n]}</small>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {COMPETENCIES.map((c) => (
                    <tr key={c.key}>
                      <td className="fw-semibold">{c.label}</td>
                      {[1, 2, 3, 4, 5].map((n) => (
                        <td key={n} className="text-center">
                          <input
                            type="radio"
                            name={c.key}
                            checked={scores[c.key] === n}
                            onChange={() => setScore(c.key, n)}
                            className="form-check-input"
                          />
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {Object.keys(scores).length > 0 && (
              <div className="alert alert-info py-2">
                <strong>Average Score: </strong>
                {(Object.values(scores).reduce((a, b) => a + b, 0) / Object.values(scores).length).toFixed(2)} / 5.00
              </div>
            )}
            <div className="mb-3">
              <label className="form-label fw-semibold">General Comments</label>
              <textarea
                className="form-control"
                rows={3}
                value={comments}
                onChange={(e) => setComments(e.target.value)}
                placeholder="Optional overall comments…"
              />
            </div>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button
              type="button"
              className="btn btn-warning text-white"
              onClick={handleLocalSubmit}
              disabled={processing || !canSubmit}
            >
              <i className={`fa fa-${processing ? 'spinner fa-spin' : 'paper-plane'} me-2`}></i>
              Submit Evaluation
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function SupervisorPerformanceEvaluation() {
  const currentTerm = useCurrentTerm()
  const [groups, setGroups] = useState({ pending: [], completed: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage] = useState(null)
  const [modal, setModal] = useState(null)

  const fetchData = () => {
    setLoading(true)
    setError(null)
    api.get('/supervisor/evaluations')
      .then((res) => setGroups(unwrapGroups(res.data)))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
        setGroups({ pending: [], completed: [] })
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchData() }, [])

  const handleSubmit = async (internshipId, formData, period) => {
    setProcessing(true)
    setMessage(null)
    try {
      await api.post(`/supervisor/evaluations/${internshipId}`, formData)
      setMessage({
        type: 'success',
        text: `${period.charAt(0).toUpperCase() + period.slice(1)} evaluation submitted successfully.`,
      })
      setModal(null)
      fetchData()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Submission failed.' })
    } finally {
      setProcessing(false)
    }
  }

  const { pending, completed } = groups

  return (
    <Layout title="Evaluations" subtitle={currentTerm} icon="fa-star" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={fetchData} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}
      {modal && (
        <EvalModal internship={modal} onClose={() => setModal(null)} onSubmit={handleSubmit} processing={processing} />
      )}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="content-card mb-4">
            <div className="content-card-header">
              <i className="fa fa-clock text-warning"></i>
              <h6>Pending Evaluations</h6>
              <span className="ms-auto badge bg-warning text-dark">{pending.length}</span>
            </div>
            <div className="table-card">
              {pending.length === 0 ? (
                <div className="text-center py-3 text-muted">No pending evaluations.</div>
              ) : pending.map((i) => {
                const p = profileOf(i)
                return (
                  <div key={i.id} className="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                      <div className="fw-semibold">{displayName(i)}</div>
                      <div className="text-muted" style={{ fontSize: '0.82rem' }}>
                        {p?.course_name ?? '—'} · {i.total_hours_rendered}/{i.target_hours} hrs
                      </div>
                    </div>
                    <button type="button" className="btn btn-sm btn-warning text-white" onClick={() => setModal(i)}>
                      <i className="fa fa-star me-1"></i>Evaluate
                    </button>
                  </div>
                )
              })}
            </div>
          </div>

          <div className="content-card">
            <div className="content-card-header">
              <i className="fa fa-check-circle text-success"></i>
              <h6>Completed Evaluations</h6>
              <span className="ms-auto badge bg-success">{completed.length}</span>
            </div>
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student</th><th>Period</th><th>Technical</th><th>Comm.</th>
                    <th>Teamwork</th><th>Average</th><th>Rating</th>
                  </tr>
                </thead>
                <tbody>
                  {completed.length === 0 ? (
                    <tr><td colSpan={7} className="text-center text-muted py-3">No completed evaluations.</td></tr>
                  ) : completed.map((ev) => (
                    <tr key={ev.id}>
                      <td className="fw-semibold">{displayName(ev.internship || {})}</td>
                      <td><span className="badge bg-secondary text-capitalize">{ev.evaluation_period}</span></td>
                      <td>{ev.technical_skills}</td>
                      <td>{ev.communication_skills}</td>
                      <td>{ev.teamwork}</td>
                      <td><strong>{ev.average_score}</strong></td>
                      <td><span className="badge bg-info">{ev.rating ?? '—'}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default SupervisorPerformanceEvaluation

import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function CoordEvaluations() {
  const [evals, setEvals] = useState([])
  const [feedback, setFeedback] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    Promise.all([
      api.get('/coordinator/evaluations'),
      api.get('/coordinator/supervisor-feedback'),
    ])
      .then(([eRes, fRes]) => {
        setEvals(unwrapList(eRes.data).items)
        setFeedback(unwrapList(fRes.data).items)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
        setEvals([])
        setFeedback([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const studentOf = (row) => {
    const p = row?.internship?.student?.student_profile || row?.internship?.student?.studentProfile
    return p ? `${p.first_name} ${p.last_name}` : (row?.internship?.student?.username || '—')
  }

  return (
    <Layout title="Evaluations Oversight" subtitle="Coordinator" icon="fa-star" bodyClass="coordinator-page">
      {error && <PageError message={error} onRetry={load} />}

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-star"></i>
          <h6>Submitted Evaluations (Faculty + Industry Supervisor)</h6>
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
                    <th>Evaluator Type</th>
                    <th>Period</th>
                    <th>Average</th>
                    <th>Rating</th>
                    <th>Submitted</th>
                  </tr>
                </thead>
                <tbody>
                  {evals.length === 0 ? (
                    <tr><td colSpan={6} className="text-center text-muted py-4">No evaluations yet.</td></tr>
                  ) : evals.map((ev) => (
                    <tr key={ev.id}>
                      <td className="fw-semibold">{studentOf(ev)}</td>
                      <td className="text-capitalize">{ev.evaluator_type}</td>
                      <td className="text-capitalize">{ev.evaluation_period}</td>
                      <td>{ev.average_score ?? '—'}</td>
                      <td>{ev.rating ?? '—'}</td>
                      <td style={{ fontSize: '0.85rem' }}>
                        {ev.submitted_at ? new Date(ev.submitted_at).toLocaleString() : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-comment-dots"></i>
          <h6>Industry Supervisor Narrative Feedback</h6>
        </div>
        <div className="table-card">
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Feedback</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {feedback.length === 0 ? (
                  <tr><td colSpan={3} className="text-center text-muted py-4">No supervisor feedback yet.</td></tr>
                ) : feedback.map((row) => (
                  <tr key={row.id}>
                    <td className="fw-semibold">{studentOf(row)}</td>
                    <td style={{ maxWidth: 420 }}>{row.supervisor_feedback}</td>
                    <td style={{ fontSize: '0.85rem' }}>
                      {row.supervisor_reviewed_at
                        ? new Date(row.supervisor_reviewed_at).toLocaleDateString()
                        : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Layout>
  )
}

export default CoordEvaluations

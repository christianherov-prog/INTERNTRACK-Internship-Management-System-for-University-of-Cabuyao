import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileImage } from '../../components/AuthenticatedFile'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

const COMPETENCIES = [
  { key: 'technical_skills',       label: 'Technical Skills' },
  { key: 'communication_skills',   label: 'Communication Skills' },
  { key: 'teamwork',               label: 'Teamwork' },
  { key: 'initiative',             label: 'Initiative' },
  { key: 'work_ethics',            label: 'Work Ethics' },
  { key: 'attendance_punctuality', label: 'Attendance & Punctuality' },
  { key: 'adaptability',           label: 'Adaptability' },
  { key: 'problem_solving',        label: 'Problem Solving' },
]

function ScoreBar({ value }) {
  const pct = ((value ?? 0) / 5) * 100
  const color = pct >= 80 ? '#16a34a' : pct >= 60 ? '#d97706' : '#dc2626'
  return (
    <div className="d-flex align-items-center gap-2">
      <div className="progress flex-grow-1" style={{ height: '8px' }}>
        <div className="progress-bar" style={{ width: `${pct}%`, background: color }}></div>
      </div>
      <span style={{ fontSize: '0.82rem', fontWeight: 600, minWidth: '30px', color }}>{value ?? '—'}</span>
    </div>
  )
}

function EvalDetailModal({ evaluation, onClose }) {
  const evaluatorLabel = evaluation.evaluator_type === 'supervisor' ? 'Company Supervisor' : 'Faculty Supervisor'
  const periodLabel    = evaluation.evaluation_period === 'midterm' ? 'Midterm' : 'Final'
  const signatureIsHttpUrl = evaluation.signature_url && /^https?:\/\//i.test(evaluation.signature_url)

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.45)' }}>
      <div className="modal-dialog modal-lg modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">
              <i className="fa fa-star me-2 text-warning"></i>
              {periodLabel} Evaluation — {evaluatorLabel}
            </h5>
            <button className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            {/* Summary */}
            <div className="row g-3 mb-4">
              <div className="col-sm-4 text-center">
                <div style={{ fontSize: '2.5rem', fontWeight: 800, color: '#6366f1' }}>
                  {parseFloat(evaluation.average_score ?? 0).toFixed(2)}
                </div>
                <div className="text-muted" style={{ fontSize: '0.82rem' }}>Average Score / 5.00</div>
              </div>
              <div className="col-sm-4 text-center">
                <div style={{ fontSize: '2.5rem', fontWeight: 800, color: '#14b8a6' }}>
                  {parseFloat(evaluation.total_score ?? 0).toFixed(0)}
                </div>
                <div className="text-muted" style={{ fontSize: '0.82rem' }}>Total Score</div>
              </div>
              <div className="col-sm-4 text-center">
                <div style={{ fontSize: '1.4rem', fontWeight: 700, color: '#d97706' }}>
                  {evaluation.rating ?? '—'}
                </div>
                <div className="text-muted" style={{ fontSize: '0.82rem' }}>Rating</div>
              </div>
            </div>

            {/* Competencies */}
            <h6 className="fw-semibold mb-3">Competency Breakdown</h6>
            {COMPETENCIES.map(c => (
              <div key={c.key} className="mb-2">
                <div className="d-flex justify-content-between mb-1" style={{ fontSize: '0.83rem' }}>
                  <span>{c.label}</span>
                </div>
                <ScoreBar value={evaluation[c.key]} />
              </div>
            ))}

            {/* Comments */}
            {evaluation.general_comments && (
              <div className="mt-4 p-3 rounded" style={{ background: '#f8fafc', fontSize: '0.88rem' }}>
                <div className="fw-semibold mb-1">General Comments</div>
                <p className="mb-0 text-muted">{evaluation.general_comments}</p>
              </div>
            )}
            {evaluation.strengths && (
              <div className="mt-3 p-3 rounded" style={{ background: '#f0fdf4', fontSize: '0.88rem' }}>
                <div className="fw-semibold mb-1 text-success">Strengths</div>
                <p className="mb-0">{evaluation.strengths}</p>
              </div>
            )}
            {evaluation.areas_for_improvement && (
              <div className="mt-3 p-3 rounded" style={{ background: '#fff7ed', fontSize: '0.88rem' }}>
                <div className="fw-semibold mb-1 text-warning">Areas for Improvement</div>
                <p className="mb-0">{evaluation.areas_for_improvement}</p>
              </div>
            )}

            {(evaluation.signer_name || evaluation.signature_url || evaluation.signature_path) && (
              <div className="mt-4 p-3 rounded border" style={{ background: '#f8fafc', fontSize: '0.88rem' }}>
                <div className="fw-semibold mb-2">Electronic signature</div>
                {(evaluation.signature_url || evaluation.signature_path) && (
                  signatureIsHttpUrl ? (
                    <img
                      src={evaluation.signature_url}
                      alt="Evaluator signature"
                      className="border rounded bg-white mb-2"
                      style={{ maxHeight: 100, maxWidth: '100%' }}
                    />
                  ) : evaluation.signature_path ? (
                    <AuthenticatedFileImage
                      path={evaluation.signature_path}
                      alt="Evaluator signature"
                      className="border rounded bg-white mb-2"
                      style={{ maxHeight: 100, maxWidth: '100%' }}
                    />
                  ) : null
                )}
                <div><strong>Signed by:</strong> {evaluation.signer_name || '—'}</div>
                <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                  {evaluation.signed_at
                    ? new Date(evaluation.signed_at).toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : '—'}
                </div>
                <small className="text-muted">Electronic acknowledgment (drawn signature + typed name), not a PKI certificate.</small>
              </div>
            )}

            <div className="mt-3 text-muted" style={{ fontSize: '0.78rem' }}>
              Submitted: {evaluation.submitted_at ? new Date(evaluation.submitted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'}
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Close</button>
          </div>
        </div>
      </div>
    </div>
  )
}

function StudentEvaluations() {
  const currentTerm = useCurrentTerm()
  const [evaluations, setEvaluations] = useState([])
  const [loading, setLoading]         = useState(true)
  const [error, setError]             = useState(null)
  const [selected, setSelected]       = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/student/evaluations')
      .then(res => setEvaluations(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
        setEvaluations([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const getRatingBg = (avg) => {
    const n = parseFloat(avg ?? 0)
    if (n >= 4.5) return { bg: '#dcfce7', color: '#15803d' }
    if (n >= 3.5) return { bg: '#e0f2fe', color: '#075985' }
    if (n >= 2.5) return { bg: '#fef9c3', color: '#854d0e' }
    return { bg: '#fee2e2', color: '#991b1b' }
  }

  const supervisor = evaluations.filter(e => e.evaluator_type === 'supervisor')
  const faculty    = evaluations.filter(e => e.evaluator_type === 'faculty')

  const totalAvg = evaluations.length > 0
    ? (evaluations.reduce((sum, e) => sum + parseFloat(e.average_score ?? 0), 0) / evaluations.length).toFixed(2)
    : null

  return (
    <Layout title="Evaluations" subtitle={currentTerm} icon="fa-star" bodyClass="student-page">
      {error && <PageError message={error} onRetry={load} />}

      {selected && <EvalDetailModal evaluation={selected} onClose={() => setSelected(null)} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        <>
          {/* Summary Cards */}
          <div className="row g-3 mb-4">
            <div className="col-sm-4">
              <div className="stat-card">
                <div className="stat-icon blue"><i className="fa fa-clipboard-check"></i></div>
                <div>
                  <div className="stat-value">{evaluations.length}</div>
                  <div className="stat-label">Total Evaluations</div>
                </div>
              </div>
            </div>
            <div className="col-sm-4">
              <div className="stat-card">
                <div className="stat-icon green"><i className="fa fa-star"></i></div>
                <div>
                  <div className="stat-value">{totalAvg ?? '—'}</div>
                  <div className="stat-label">Overall Average</div>
                </div>
              </div>
            </div>
            <div className="col-sm-4">
              <div className="stat-card">
                <div className="stat-icon amber"><i className="fa fa-clock"></i></div>
                <div>
                  <div className="stat-value">{4 - evaluations.length}</div>
                  <div className="stat-label">Pending Evaluations</div>
                </div>
              </div>
            </div>
          </div>

          {evaluations.length === 0 && !error ? (
            <div className="content-card p-4 text-center text-muted">
              <i className="fa fa-hourglass-half fa-2x mb-3 d-block"></i>
              No evaluations submitted yet. Your supervisor and faculty will submit evaluations during midterm and final periods.
            </div>
          ) : evaluations.length === 0 ? null : (
            <>
              {/* Supervisor Evaluations */}
              {supervisor.length > 0 && (
                <div className="content-card mb-4">
                  <div className="content-card-header">
                    <i className="fa fa-building text-primary"></i>
                    <h6>Company Supervisor Evaluations</h6>
                  </div>
                  <div className="table-responsive">
                    <table className="table table-hover mb-0">
                      <thead><tr><th>Period</th><th>Technical</th><th>Communication</th><th>Teamwork</th><th>Work Ethics</th><th>Average</th><th>Rating</th><th className="text-center">Details</th></tr></thead>
                      <tbody>
                        {supervisor.map(ev => {
                          const { bg, color } = getRatingBg(ev.average_score)
                          return (
                            <tr key={ev.id}>
                              <td><span className="badge bg-secondary text-capitalize">{ev.evaluation_period}</span></td>
                              <td>{ev.technical_skills ?? '—'}</td>
                              <td>{ev.communication_skills ?? '—'}</td>
                              <td>{ev.teamwork ?? '—'}</td>
                              <td>{ev.work_ethics ?? '—'}</td>
                              <td><strong style={{ color }}>{parseFloat(ev.average_score ?? 0).toFixed(2)}</strong></td>
                              <td><span className="badge" style={{ background: bg, color }}>{ev.rating ?? '—'}</span></td>
                              <td className="text-center">
                                <button className="btn btn-sm btn-outline-green" onClick={() => setSelected(ev)}>
                                  <i className="fa fa-eye me-1"></i>View
                                </button>
                              </td>
                            </tr>
                          )
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Faculty Evaluations */}
              {faculty.length > 0 && (
                <div className="content-card mb-4">
                  <div className="content-card-header">
                    <i className="fa fa-chalkboard-teacher text-success"></i>
                    <h6>Faculty Supervisor Evaluations</h6>
                  </div>
                  <div className="table-responsive">
                    <table className="table table-hover mb-0">
                      <thead><tr><th>Period</th><th>Technical</th><th>Communication</th><th>Teamwork</th><th>Work Ethics</th><th>Average</th><th>Rating</th><th className="text-center">Details</th></tr></thead>
                      <tbody>
                        {faculty.map(ev => {
                          const { bg, color } = getRatingBg(ev.average_score)
                          return (
                            <tr key={ev.id}>
                              <td><span className="badge bg-secondary text-capitalize">{ev.evaluation_period}</span></td>
                              <td>{ev.technical_skills ?? '—'}</td>
                              <td>{ev.communication_skills ?? '—'}</td>
                              <td>{ev.teamwork ?? '—'}</td>
                              <td>{ev.work_ethics ?? '—'}</td>
                              <td><strong style={{ color }}>{parseFloat(ev.average_score ?? 0).toFixed(2)}</strong></td>
                              <td><span className="badge" style={{ background: bg, color }}>{ev.rating ?? '—'}</span></td>
                              <td className="text-center">
                                <button className="btn btn-sm btn-outline-success" onClick={() => setSelected(ev)}>
                                  <i className="fa fa-eye me-1"></i>View
                                </button>
                              </td>
                            </tr>
                          )
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Pending Periods */}
              {evaluations.length < 4 && (
                <div className="content-card">
                  <div className="content-card-header">
                    <i className="fa fa-clock text-warning"></i>
                    <h6>Pending Evaluations</h6>
                  </div>
                  <div className="p-3">
                    {['midterm', 'final'].flatMap(period =>
                      ['supervisor', 'faculty'].map(type => {
                        const done = evaluations.some(e => e.evaluation_period === period && e.evaluator_type === type)
                        if (done) return null
                        return (
                          <div key={`${period}-${type}`} className="d-flex align-items-center gap-3 py-2 border-bottom">
                            <i className="fa fa-clock text-warning"></i>
                            <div>
                              <div className="fw-semibold" style={{ fontSize: '0.88rem' }}>
                                {period.charAt(0).toUpperCase() + period.slice(1)} — {type === 'supervisor' ? 'Company Supervisor' : 'Faculty Supervisor'}
                              </div>
                              <div className="text-muted" style={{ fontSize: '0.78rem' }}>Waiting for evaluator to submit</div>
                            </div>
                            <span className="badge bg-warning text-dark ms-auto">Pending</span>
                          </div>
                        )
                      })
                    ).filter(Boolean)}
                  </div>
                </div>
              )}
            </>
          )}
        </>
      )}
    </Layout>
  )
}

export default StudentEvaluations

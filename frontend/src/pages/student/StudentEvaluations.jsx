import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileImage } from '../../components/AuthenticatedFile'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { HostTrainingEstEvaluationForm } from '../../components/evaluations/HostTrainingEstEvaluationForm'
import { InternshipProgramEvaluationForm } from '../../components/evaluations/InternshipProgramEvaluationForm'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'

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

function EvalDetailModal({ evaluation, onClose, onPreview }) {
  const evaluatorLabel = evaluation.evaluator_type === 'supervisor' ? 'Company Supervisor' : 'Faculty Supervisor'
  const periodLabel = evaluation.evaluation_period === 'midterm' ? 'Midterm' : 'Final'
  const signatureIsHttpUrl = evaluation.signature_url && /^https?:\/\//i.test(evaluation.signature_url)

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.45)' }}>
      {/* Changed to modal-xl and added modal-dialog-scrollable for a larger preview */}
      <div className="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        {/* Added minHeight to stretch the modal vertically */}
        <div className="modal-content" style={{ minHeight: '85vh' }}>
          <div className="modal-header">
            <h5 className="modal-title">
              <i className="fa fa-star me-2 text-warning"></i>
              {periodLabel} Evaluation — {evaluatorLabel}
            </h5>
            <button className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body p-4">
            {/* Summary */}
            <div className="row g-4 mb-5">
              <div className="col-sm-4 text-center">
                <div style={{ fontSize: '3rem', fontWeight: 800, color: '#6366f1' }}>
                  {parseFloat(evaluation.average_score ?? 0).toFixed(2)}
                </div>
                <div className="text-muted" style={{ fontSize: '0.9rem' }}>Average Score / 5.00</div>
              </div>
              <div className="col-sm-4 text-center">
                <div style={{ fontSize: '3rem', fontWeight: 800, color: '#14b8a6' }}>
                  {parseFloat(evaluation.total_score ?? 0).toFixed(0)}
                </div>
                <div className="text-muted" style={{ fontSize: '0.9rem' }}>Total Score</div>
              </div>
              <div className="col-sm-4 text-center">
                <div style={{ fontSize: '1.8rem', fontWeight: 700, color: '#d97706', marginTop: '10px' }}>
                  {evaluation.rating ?? '—'}
                </div>
                <div className="text-muted" style={{ fontSize: '0.9rem', marginTop: '5px' }}>Rating</div>
              </div>
            </div>

            {/* Competencies */}
            <h5 className="fw-semibold mb-4">Competency Breakdown</h5>
            <div className="row mb-4">
              {COMPETENCIES.map(c => (
                <div key={c.key} className="col-md-6 mb-3">
                  <div className="d-flex justify-content-between mb-1" style={{ fontSize: '0.9rem' }}>
                    <span>{c.label}</span>
                  </div>
                  <ScoreBar value={evaluation[c.key]} />
                </div>
              ))}
            </div>

            {/* Comments */}
            {evaluation.general_comments && (
              <div className="mt-4 p-4 rounded" style={{ background: '#f8fafc', fontSize: '1rem', minHeight: '120px' }}>
                <div className="fw-bold mb-2">General Comments</div>
                <p className="mb-0 text-muted">{evaluation.general_comments}</p>
              </div>
            )}
            {evaluation.strengths && (
              <div className="mt-3 p-4 rounded" style={{ background: '#f0fdf4', fontSize: '1rem', minHeight: '100px' }}>
                <div className="fw-bold mb-2 text-success">Strengths</div>
                <p className="mb-0">{evaluation.strengths}</p>
              </div>
            )}
            {evaluation.areas_for_improvement && (
              <div className="mt-3 p-4 rounded" style={{ background: '#fff7ed', fontSize: '1rem', minHeight: '100px' }}>
                <div className="fw-bold mb-2 text-warning">Areas for Improvement</div>
                <p className="mb-0">{evaluation.areas_for_improvement}</p>
              </div>
            )}

            {(evaluation.signer_name || evaluation.signature_url || evaluation.signature_path) && (
              <div className="mt-5 p-4 rounded border" style={{ background: '#f8fafc', fontSize: '0.95rem' }}>
                <div className="fw-bold mb-3">Electronic signature</div>
                {(evaluation.signature_url || evaluation.signature_path) && (
                  signatureIsHttpUrl ? (
                    <img
                      src={evaluation.signature_url}
                      alt="Evaluator signature"
                      className="border rounded bg-white mb-3"
                      style={{ maxHeight: 150, maxWidth: '100%' }}
                    />
                  ) : evaluation.signature_path ? (
                    <AuthenticatedFileImage
                      path={evaluation.signature_path}
                      alt="Evaluator signature"
                      className="border rounded bg-white mb-3"
                      style={{ maxHeight: 150, maxWidth: '100%' }}
                    />
                  ) : null
                )}
                <div className="fs-6"><strong>Signed by:</strong> {evaluation.signer_name || '—'}</div>
                <div className="text-muted mt-1" style={{ fontSize: '0.85rem' }}>
                  {evaluation.signed_at
                    ? new Date(evaluation.signed_at).toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : '—'}
                </div>
                <small className="text-muted d-block mt-2">Electronic acknowledgment (drawn signature + typed name), not a PKI certificate.</small>
              </div>
            )}

            <div className="mt-4 text-muted text-end" style={{ fontSize: '0.85rem' }}>
              Submitted: {evaluation.submitted_at ? new Date(evaluation.submitted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'}
            </div>
          </div>
          <div className="modal-footer py-3">
            <button className="btn btn-outline-primary px-4" onClick={onPreview}>
              <i className="fa fa-print me-2"></i> Print Official Form
            </button>
            <button className="btn btn-secondary px-4" onClick={onClose}>Close</button>
          </div>
        </div>
      </div>
    </div>
  )
}

function SubmitEvalModal({ internship, activeForm, onClose, onSubmit, processing }) {

  return (
    <div
      className="modal show d-block"
      tabIndex="-1"
      role="dialog"
      aria-labelledby="evaluation-modal"
      style={{ backgroundColor: 'rgba(0, 0, 0, 0.5)' }} // Adds a dark overlay backdrop
    >

      <div className="modal-content modal-dialog modalxl modal-dialog-centered modal-dialog-scrollable" style={{ maxWidth: '950px' }}>

        {/* Modal Header */}
        <div className="modal-header bg-white pb-3 pt-3 px-4 border-bottom flex-shrink-0 align-items-center justify-content-between w-100">
          <h5 className="modal-title fw-bold text-primary mb-0">
            {activeForm === 'FO-22' ? 'HTE Evaluation (FO-22)' : 'Program Evaluation (FO-23)'}
          </h5>
          <button
            type="button"
            className="btn-close ms-auto"
            onClick={onClose}
            aria-label="Close"
          ></button>
        </div>

        {/* Modal Body */}
        <div className="modal-body bg-light p-4">
          {activeForm === 'FO-22' ? (
            <HostTrainingEstEvaluationForm
              internship={internship}
              onSubmit={onSubmit}
              processing={processing}
            />
          ) : (
            <InternshipProgramEvaluationForm
              internship={internship}
              onSubmit={onSubmit}
              processing={processing}
            />
          )}
        </div>

      </div>

    </div>

  )
}

function StudentEvaluations() {
  const currentTerm = useCurrentTerm()
  const [evaluations, setEvaluations] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [selected, setSelected] = useState(null)
  const [showSubmitModal, setShowSubmitModal] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [internship, setInternship] = useState(null)
  const [previewEval, setPreviewEval] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)

    api.get('/student/records')
      .then(res => {
        const history = res.data?.data || res.data
        if (history && history.length > 0) {
          setInternship(history[0])
        }
      })
      .catch(() => { })

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

  const handleLocalSubmit = async (data) => {
    setProcessing(true)
    setError(null)
    try {
      await api.post(`/student/evaluations`, data)
      setShowSubmitModal(null)
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to submit evaluation.')
    } finally {
      setProcessing(false)
    }
  }

  const supervisor = evaluations.filter(e => e.evaluator_type === 'supervisor')
  const faculty = evaluations.filter(e => e.evaluator_type === 'faculty')
  const studentEvals = evaluations.filter(e => e.evaluator_type === 'student')
  const hasFO22 = studentEvals.some(e => e.form_type === 'FO-22')
  const hasFO23 = studentEvals.some(e => e.form_type === 'FO-23')

  // Get specific evaluation objects for direct preview
  const fo24 = evaluations.find(e => e.form_type === 'FO-24')
  const fo03 = evaluations.find(e => e.form_type === 'FO-03')
  const fo22 = studentEvals.find(e => e.form_type === 'FO-22')
  const fo23 = studentEvals.find(e => e.form_type === 'FO-23')

  const FORM_STATUS = [
    { key: 'FO-24', label: 'FO-24', title: 'Performance Evaluation', source: 'By: Company Supervisor', eval: fo24, color: 'primary' },
    { key: 'FO-03', label: 'FO-03', title: 'HTE Evaluation', source: 'By: Company Supervisor', eval: fo03, color: 'success' },
    { key: 'FO-22', label: 'FO-22', title: 'HTE Evaluation', source: 'By: You (Student)', eval: fo22, color: 'info', canSubmit: !hasFO22, submitKey: 'FO-22' },
    { key: 'FO-23', label: 'FO-23', title: 'Program Evaluation', source: 'By: You (Student)', eval: fo23, color: 'warning', canSubmit: !hasFO23, submitKey: 'FO-23' },
  ]

  const totalAvg = evaluations.length > 0
    ? (evaluations.reduce((sum, e) => sum + parseFloat(e.average_score ?? 0), 0) / evaluations.length).toFixed(2)
    : null

  return (
    <Layout title="Evaluations" subtitle={currentTerm} icon="fa-star" bodyClass="student-page">


      {/* Summary stats */}
      <div className="row g-3 mb-4">
        <div className="col-sm-4">
          <div className="stat-card">
            <div className="stat-icon blue"><i className="fa fa-clipboard-check"></i></div>
            <div>
              <div className="stat-value">{evaluations.length}</div>
              <div className="stat-label">Total Submitted</div>
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
              <div className="stat-label">Pending Forms</div>
            </div>
          </div>
        </div>
      </div>



      {error && <PageError message={error} onRetry={load} />}
      {!!showSubmitModal && <SubmitEvalModal internship={internship} activeForm={showSubmitModal} onClose={() => setShowSubmitModal(null)} onSubmit={handleLocalSubmit} processing={processing} />}
      <FormPreviewModal
        isOpen={!!previewEval}
        onClose={() => setPreviewEval(null)}
        type={previewEval?.form_type || 'FO-22'}
        data={{ evalData: previewEval, internship: internship }}
      />

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        <>
          {/* 4-Form Evaluation Status Cards */}
          <div className="row g-3 mb-4">
            {FORM_STATUS.map(form => (
              <div key={form.key} className="col-sm-6 col-xl-3">
                <div className={`content-card h-100 border-start border-3 border-${form.color}`} style={{ padding: '1rem 1.25rem' }}>
                  <div className="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <span className={`badge bg-${form.color} mb-1`}>{form.label}</span>
                      <div className="fw-bold" style={{ fontSize: '0.9rem' }}>{form.title}</div>
                      <div className="text-muted" style={{ fontSize: '0.78rem' }}>{form.source}</div>
                    </div>
                    {form.eval ? (
                      <span className="badge bg-success"><i className="fa fa-check me-1"></i>Submitted</span>
                    ) : (
                      <span className="badge bg-warning text-dark"><i className="fa fa-clock me-1"></i>Pending</span>
                    )}
                  </div>

                  {form.eval && (
                    <div className="mb-2" style={{ fontSize: '0.82rem' }}>
                      <span className="text-muted">Score: </span>
                      <strong>{parseFloat(form.eval.average_score ?? 0).toFixed(2)}</strong>
                      {form.eval.rating && <span className={`ms-2 badge bg-${form.color}`}>{form.eval.rating}</span>}
                    </div>
                  )}

                  <div className="d-flex gap-2 mt-auto">
                    {form.eval && (
                      <button
                        className={`btn btn-sm btn-outline-${form.color} flex-fill`}
                        onClick={() => setPreviewEval(form.eval)}
                      >
                        <i className="fa fa-eye me-1"></i>Preview {form.label}
                      </button>
                    )}
                    {form.canSubmit && (
                      <button
                        className={`btn btn-sm btn-${form.color} flex-fill`}
                        onClick={() => setShowSubmitModal(form.submitKey)}
                      >
                        <i className="fa fa-plus me-1"></i>Submit
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Received evaluations (from supervisor/faculty) */}
          {(supervisor.length > 0 || faculty.length > 0) && (
            <div className="content-card mb-4">
              <div className="content-card-header">
                <i className="fa fa-building text-primary"></i>
                <h6>Received Evaluations (from Supervisor &amp; Faculty)</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead><tr><th>Form</th><th>Period</th><th>Evaluator</th><th>Average</th><th>Rating</th><th className="text-center">Preview</th></tr></thead>
                  <tbody>
                    {[...supervisor, ...faculty].map(ev => {
                      const { bg, color } = getRatingBg(ev.average_score)
                      return (
                        <tr key={ev.id}>
                          <td><span className="badge bg-primary">{ev.form_type}</span></td>
                          <td><span className="badge bg-secondary text-capitalize">{ev.evaluation_period}</span></td>
                          <td className="text-capitalize">{ev.evaluator_type} supervisor</td>
                          <td><strong style={{ color }}>{parseFloat(ev.average_score ?? 0).toFixed(2)}</strong></td>
                          <td><span className="badge" style={{ background: bg, color }}>{ev.rating ?? '—'}</span></td>
                          <td className="text-center">
                            <button className="btn btn-sm btn-outline-primary" onClick={() => setPreviewEval(ev)}>
                              <i className="fa fa-eye me-1"></i>Preview {ev.form_type}
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

          {/* Student's own submitted evaluations */}
          {studentEvals.length > 0 && (
            <div className="content-card">
              <div className="content-card-header">
                <i className="fa fa-user-graduate text-info"></i>
                <h6>My Submitted Evaluations</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead><tr><th>Form</th><th>Average</th><th>Rating</th><th className="text-center">Preview</th></tr></thead>
                  <tbody>
                    {studentEvals.map(ev => {
                      const { bg, color } = getRatingBg(ev.average_score)
                      return (
                        <tr key={ev.id}>
                          <td><span className="badge bg-info text-dark">{ev.form_type}</span></td>
                          <td><strong style={{ color }}>{parseFloat(ev.average_score ?? 0).toFixed(2)}</strong></td>
                          <td><span className="badge" style={{ background: bg, color }}>{ev.rating ?? '—'}</span></td>
                          <td className="text-center">
                            <button className="btn btn-sm btn-outline-info" onClick={() => setPreviewEval(ev)}>
                              <i className="fa fa-eye me-1"></i>Preview {ev.form_type}
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

          {evaluations.length === 0 && !error && (
            <div className="content-card p-4 text-center text-muted">
              <i className="fa fa-hourglass-half fa-2x mb-3 d-block"></i>
              No evaluations submitted yet. Your supervisor and faculty will submit evaluations during midterm and final periods.
            </div>
          )}
        </>
      )}
    </Layout>
  )
}

export default StudentEvaluations
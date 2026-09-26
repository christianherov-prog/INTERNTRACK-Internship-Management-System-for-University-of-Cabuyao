import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapGroups } from '../../utils/apiList'
import { StudentInternPerformanceForm } from '../../components/evaluations/StudentInternPerformanceForm'

import { HTEToUniversityEvaluationForm } from '../../components/evaluations/HTEToUniversityEvaluationForm'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import AppModal from '../../components/modals/AppModal'
import { useCachedPage } from '../../hooks/useCachedPage'
import { invalidateStudentPortfolio } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'
import { useConfirm } from '../../contexts/ConfirmContext'
import { formatManilaDate } from '../../utils/manilaTime'

function profileOf(entity) {
  return entity?.student?.student_profile || entity?.student?.studentProfile || null
}

function displayName(entity) {
  const p = profileOf(entity)
  if (p) return `${p.last_name || ''}, ${p.first_name || ''}`.trim()
  return entity?.student?.username || ''
}

function EvalModal({ internship, activeForm, onClose, onSubmit, processing }) {
  const formTitle = activeForm === 'FO-24'
    ? 'Student Performance (FO-24)'
    : 'Program Evaluation (FO-03)'

  return (
    <AppModal
      onClose={onClose}
      size="lg"
      title={formTitle}
      icon="fa-clipboard-check"
      busy={processing}
      bodyClassName="bg-light"
    >
      {activeForm === 'FO-24' ? (
        <StudentInternPerformanceForm
          internship={internship}
          onSubmit={(data) => onSubmit(internship.id, data, data.evaluation_period)}
          processing={processing}
        />
      ) : (
        <HTEToUniversityEvaluationForm
          internship={internship}
          onSubmit={(data) => onSubmit(internship.id, data, data.evaluation_period)}
          processing={processing}
        />
      )}
    </AppModal>
  )
}

export default function SupervisorPerformanceEvaluation() {
  const confirm = useConfirm()
  const { loading, seed, run } = useCachedPage('supervisor:evaluations')
  const [modal, setModal] = useState(null)
  const [previewEval, setPreviewEval] = useState(null)
  const [groups, setGroups] = useState(() => seed ?? { pending: [], completed: [] })
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage] = useState(null)

  const fetchData = () => {
    setError(null)
    run(() => api.get('/supervisor/evaluations').then((res) => unwrapGroups(res.data)))
      .then((next) => { if (next) setGroups(next) })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
        setGroups({ pending: [], completed: [] })
      })
  }

  useEffect(() => { fetchData() }, [])

  const handleSubmit = async (internshipId, data, period) => {
    const student = displayName(modal?.internship || { id: internshipId }) || 'this intern'
    const formLabel = data?.form_type || modal?.activeForm || 'evaluation'
    const ok = await confirm({
      title: 'Submit evaluation?',
      message: `Submit ${formLabel}${period ? ` (${period})` : ''} for ${student}? You will not be able to edit it after submission.`,
      confirmLabel: 'Submit Evaluation',
      variant: 'primary',
    })
    if (!ok) return

    setProcessing(true)
    setMessage(null)
    try {
      // Send as JSON
      await api.post(`/supervisor/evaluations/${internshipId}`, data)
      invalidateStudentPortfolio()
      setMessage({
        type: 'success',
        text: `Evaluation submitted successfully.`,
      })
      setModal(null)
      fetchData()
    } catch (err) {
      setMessage({
        type: 'danger',
        text: err.response?.data?.message || 'Failed to submit evaluation.',
      })
    } finally {
      setProcessing(false)
    }
  }

  if (loading && !seed) {
    return (
      <Layout role="supervisor">
        <div className="d-flex justify-content-center py-5"><InternTrackLoader /></div>
      </Layout>
    )
  }

  if (error) {
    return <Layout role="supervisor"><PageError message={error} onRetry={fetchData} /></Layout>
  }

  return (
    <Layout title="Performance Evaluations" subtitle="Assessing Student Learning, Progress, and Growth" icon="fa-star" role="supervisor">
      <div className="container-fluid py-4 max-w-7xl">  
        {message && (
          <div className={`alert alert-${message.type} alert-dismissible fade show`} role="alert">
            {message.text}
            <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
          </div>
        )}

        <div className="row g-4">
          <div className="col-12 col-xl-6">
            <div className="card shadow-sm border-0 h-100">
              <div className="card-header bg-white border-bottom py-3">
                <h6 className="mb-0 fw-bold d-flex align-items-center">
                  <i className="fa fa-clock text-warning me-2"></i> Pending Evaluations
                  <span className="badge bg-warning ms-auto rounded-pill">{groups.pending.length}</span>
                </h6>
              </div>
              <div className="card-body p-0">
                {groups.pending.length === 0 ? (
                  <div className="text-center text-muted p-5">
                    <i className="fa fa-check-circle fa-2x mb-3 d-block text-success"></i>
                    All caught up! No pending evaluations.
                  </div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {groups.pending.map((internship) => (
                      <li key={internship.id} className="list-group-item p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                          <div className="fw-bold mb-1">{displayName(internship)}</div>
                          <div className="text-muted small mb-1">
                            {internship.program?.name || internship.program?.code || (typeof internship.program === 'string' ? internship.program : null) || profileOf(internship)?.program?.name || profileOf(internship)?.program?.code || (typeof profileOf(internship)?.program === 'string' ? profileOf(internship)?.program : null) || profileOf(internship)?.course_name || '—'}
                          </div>
                          <div className="text-muted small">
                            Status: <span className="text-uppercase">{internship.status}</span>
                          </div>
                          {internship.evaluation_eligibility && (
                            <div className="mt-2">
                              <span className={`badge ${internship.evaluation_eligibility.status === 'not_yet_eligible' ? 'bg-warning text-dark' : 'bg-success'}`}>
                                {internship.evaluation_eligibility.label}
                              </span>
                              <div className="text-muted small mt-1">
                                {internship.hours_rendered ?? internship.evaluation_eligibility.hours_rendered ?? 0}
                                {' / '}
                                {internship.target_hours ?? internship.evaluation_eligibility.target_hours ?? 0}
                                {' hrs'}
                                {internship.evaluation_eligibility.reason ? ` — ${internship.evaluation_eligibility.reason}` : ''}
                              </div>
                            </div>
                          )}
                        </div>
                        <div className="d-flex flex-column gap-2 align-items-end">
                          {(internship.evaluation_period_status || 'pending') !== 'approved' ? (
                            <span className="text-muted small text-end">
                              <i className="fa fa-lock me-1"></i>Waiting for Faculty approval
                            </span>
                          ) : (
                            <>
                          {(!internship.missing_forms || internship.missing_forms.includes('FO-24')) && (
                            <button className="btn btn-primary btn-sm px-3 rounded-pill text-start" 
                            style={{fontSize: '0.80rem', whiteSpace: 'normal', lineHeight: '1.2'}}
                            onClick={() => setModal({ internship, activeForm: 'FO-24' })}>
                              <i className="fa fa-edit me-1"></i> Evaluate STUDENT INTERN PERFORMANCE EVALUATION FORM
                            </button>
                          )}
                          {(!internship.missing_forms || internship.missing_forms.includes('FO-03')) && (
                            <button className="btn btn-outline-primary btn-sm px-3 rounded-pill text-start" 
                              style={{fontSize: '8pt', whiteSpace: 'normal', lineHeight: '1.2', textTransform: 'uppercase'}}
                              onClick={() => setModal({ internship, activeForm: 'FO-03' })}>
                              <i className="fa fa-edit me-1"></i> Evaluate HTE Evaluation to the University Internship Program
                            </button>
                          )}
                            </>
                          )}
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>

          <div className="col-12 col-xl-6">
            <div className="card shadow-sm border-0 h-100">
              <div className="card-header bg-white border-bottom py-3">
                <h6 className="mb-0 fw-bold d-flex align-items-center">
                  <i className="fa fa-check-double text-success me-2"></i> Completed Evaluations
                  <span className="badge bg-success ms-auto rounded-pill">{groups.completed.length}</span>
                </h6>
              </div>
              <div className="card-body p-0">
                {groups.completed.length === 0 ? (
                  <div className="text-center text-muted p-5">
                    No evaluations completed yet.
                  </div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {groups.completed.map((ev) => (
                      <li key={ev.id} className="list-group-item p-4">
                        <div className="d-flex justify-content-between align-items-center mb-2">
                          <div>
                            <span className="fw-bold">{displayName(ev.internship)}</span>
                            <span className="badge bg-secondary ms-2" style={{ fontSize: '0.75rem' }}>{ev.form_type}</span>
                          </div>
                          <span className={`badge ${
                            ev.rating === 'Failed' ? 'bg-danger' : 
                            ev.rating === 'Excellent' ? 'bg-success' : 'bg-primary'
                          } rounded-pill`}>
                            {ev.rating || 'N/A'}
                          </span>
                        </div>
                        <div className="row text-sm text-muted g-2">
                          <div className="col-6">Period: <span className="text-body text-capitalize">{ev.evaluation_period}</span></div>
                          <div className="col-6">Score: <span className="text-body fw-semibold">{ev.average_score}</span></div>
                          <div className="col-12 d-flex justify-content-between align-items-center">
                            <span>Submitted: {formatManilaDate(ev.submitted_at)}</span>
                            <button className="btn btn-sm btn-outline-primary" onClick={() => setPreviewEval(ev)}>
                              <i className="fa fa-print me-1"></i> Preview Form
                            </button>
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {modal && (
        <EvalModal
          internship={modal.internship}
          activeForm={modal.activeForm}
          onClose={() => setModal(null)}
          onSubmit={handleSubmit}
          processing={processing}
        />
      )}

      <FormPreviewModal 
        isOpen={!!previewEval} 
        onClose={() => setPreviewEval(null)} 
        type={previewEval?.form_type || 'FO-24'} 
        data={{ evalData: previewEval, internship: previewEval?.internship }} 
      />
    </Layout>
  )
}

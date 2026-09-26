import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { useCachedPage } from '../../hooks/useCachedPage'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import { invalidateStudentPortfolio } from '../../utils/pageCache'
import InternTrackLoader from '../../components/InternTrackLoader'
import { useConfirm } from '../../contexts/ConfirmContext'
import AsyncButton from '../../components/AsyncButton'
import AppModal from '../../components/modals/AppModal'

function FacultyEvalModal({ internship, existing, onClose, onSaved }) {
  const [period, setPeriod] = useState(existing?.evaluation_period || 'midterm')
  const [score, setScore] = useState(existing?.average_score ?? 80)
  const [comments, setComments] = useState(existing?.general_comments || '')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const confirm = useConfirm()

  const submit = async () => {
    const studentLabel = internship?.student_name
      || [internship?.student?.student_profile?.last_name, internship?.student?.student_profile?.first_name].filter(Boolean).join(', ')
      || internship?.student?.username
      || `Internship #${internship.id}`
    const ok = await confirm({
      title: 'Submit faculty evaluation?',
      message: `Submit faculty evaluation for ${studentLabel} (${period}) with score ${score}?`,
      confirmLabel: 'Submit Evaluation',
      variant: 'primary',
    })
    if (!ok) return

    setSaving(true)
    setError(null)
    try {
      await api.post(`/faculty/evaluations/${internship.id}`, {
        evaluation_period: period,
        overall_score: Number(score),
        general_comments: comments || undefined,
      })
      invalidateStudentPortfolio()
      onSaved()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to submit faculty evaluation.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <AppModal
      onClose={onClose}
      size="md"
      title="Faculty Evaluation"
      icon="fa-pen"
      busy={saving}
      footer={(
        <>
          <button type="button" className="btn btn-secondary" onClick={onClose} disabled={saving}>Cancel</button>
          <button type="button" className="btn btn-primary" onClick={submit} disabled={saving}>
            <i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-2`}></i>Submit Evaluation
          </button>
        </>
      )}
    >
      {error && <div className="alert alert-danger py-2">{error}</div>}
      <div className="it-form-grid">
        <div>
          <label className="form-label fw-semibold" htmlFor="faculty-eval-period">Period</label>
          <select id="faculty-eval-period" className="form-select" value={period} onChange={e => setPeriod(e.target.value)}>
            <option value="midterm">Midterm</option>
            <option value="final">Final</option>
          </select>
        </div>
        <div>
          <label className="form-label fw-semibold" htmlFor="faculty-eval-score">Overall score (0-100)</label>
          <input id="faculty-eval-score" type="number" className="form-control" min="0" max="100" step="1" required value={score} onChange={e => setScore(e.target.value)} />
        </div>
        <div className="it-span-2">
          <label className="form-label fw-semibold" htmlFor="faculty-eval-comments">Comments</label>
          <textarea id="faculty-eval-comments" className="form-control" rows={3} maxLength={2000} value={comments} onChange={e => setComments(e.target.value)} placeholder="Remarks" />
          <div className="form-text text-end">{comments.length}/2000</div>
        </div>
      </div>
    </AppModal>
  )
}

function FacultyEvaluations() {
  const currentTerm = useCurrentTerm()
  const confirm = useConfirm()
  const { loading, seed, run } = useCachedPage('faculty:evaluations')
  const [internships, setInternships] = useState(() => seed?.internships ?? [])
  const [availableSections, setAvailableSections] = useState(() => seed?.available_sections ?? [])
  const [error, setError] = useState(null)
  const [previewData, setPreviewData] = useState(null)  // { eval, internship }
  const [submitModal, setSubmitModal] = useState(null)
  const [message, setMessage] = useState(null)
  const [approvingId, setApprovingId] = useState(null)
  const [releasingId, setReleasingId] = useState(null)
  const [filters, setFilters] = useState({ search: '', section: '' })
  const [debouncedSearch, setDebouncedSearch] = useState('')

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(filters.search)
    }, 500)
    return () => clearTimeout(timer)
  }, [filters.search])

  const fetchData = () => {
    setError(null)
    const params = new URLSearchParams()
    if (debouncedSearch) params.append('search', debouncedSearch)
    if (filters.section) params.append('section', filters.section)

    run(() => api.get(`/faculty/evaluations?${params.toString()}`).then(res => res.data))
      .then((next) => {
        if (next) {
          setInternships(next.internships || [])
          setAvailableSections(next.available_sections || [])
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
      })
  }

  useEffect(() => { fetchData() }, [debouncedSearch, filters.section])

  const approvePeriod = async (internship) => {
    const studentLabel = internship?.student_name
      || [internship?.student?.student_profile?.last_name, internship?.student?.student_profile?.first_name].filter(Boolean).join(', ')
      || internship?.student?.username
      || `Internship #${internship.id}`
    await confirm({
      title: 'Approve evaluation period?',
      message: `Approve the evaluation period for ${studentLabel}? This unlocks Student (FO-22/FO-23) and Supervisor (FO-24/FO-03) forms.`,
      confirmLabel: 'Approve Period',
      variant: 'primary',
      run: async () => {
        setApprovingId(internship.id)
        setMessage(null)
        try {
          const res = await api.post(`/faculty/evaluations/${internship.id}/approve-period`)
          // Reflect the authoritative state immediately, then re-fetch the list.
          const nextPeriod = res.data?.evaluation_period
          if (nextPeriod) {
            setInternships(prev => prev.map(i => (i.id === internship.id
              ? { ...i, evaluation_period: nextPeriod, evaluation_period_status: res.data?.evaluation_period_status || 'approved' }
              : i)))
          }
          setMessage({ type: 'success', text: res.data?.message || 'Evaluation period approved. Student and supervisor forms are now unlocked.' })
          fetchData()
        } catch (err) {
          setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to approve evaluation period.' })
          throw err
        } finally {
          setApprovingId(null)
        }
      },
    })
  }

  // FO-24 details stay hidden from the student until this faculty releases them.
  const toggleRelease = async (internship, release) => {
    const studentLabel = internship?.student_name
      || [internship?.student?.student_profile?.last_name, internship?.student?.student_profile?.first_name].filter(Boolean).join(', ')
      || internship?.student?.username
      || `Internship #${internship.id}`
    await confirm({
      title: release ? 'Release Performance Evaluation?' : 'Hide Performance Evaluation?',
      message: release
        ? `Allow ${studentLabel} to view the FO-24 Performance Evaluation details (scores, ratings, and comments)?`
        : `Hide the FO-24 Performance Evaluation details from ${studentLabel}? The student will only see that it was completed.`,
      confirmLabel: release ? 'Release to Student' : 'Hide from Student',
      variant: release ? 'primary' : 'danger',
      run: async () => {
        setReleasingId(internship.id)
        setMessage(null)
        try {
          const res = await api.post(`/faculty/evaluations/${internship.id}/release-performance`, { released: release })
          setMessage({ type: 'success', text: res.data?.message || 'Updated.' })
          invalidateStudentPortfolio()
          fetchData()
        } catch (err) {
          setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to update evaluation visibility.' })
          throw err
        } finally {
          setReleasingId(null)
        }
      },
    })
  }

  return (
    <Layout title="Evaluation Review — FO-24" subtitle={currentTerm} icon="fa-search" bodyClass="faculty-page">
      {error && <PageError message={error} onRetry={fetchData} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {loading ? (
        <div className="text-center py-5"><InternTrackLoader /></div>
      ) : (
        <section className="content-card fo24-review" aria-labelledby="fo24-review-title">
          <div className="content-card-header fo24-review__header">
            <i className="fa fa-check-circle text-success" aria-hidden="true"></i>
            <h6 id="fo24-review-title">FO-24 — Student Internship Performance Evaluation (Official Basis for Grading)</h6>
            <span className="ms-auto badge bg-success" aria-label={`${internships.length} students`}>{internships.length}</span>
          </div>

          <div className="fo24-review__filters row g-2 g-md-3 align-items-end">
            <div className="col-12 col-md-8">
              <label htmlFor="fo24-search" className="form-label small text-muted mb-1">Search</label>
              <input
                id="fo24-search"
                maxLength={100}
                type="search"
                className="form-control"
                placeholder="Search students by name"
                value={filters.search}
                onChange={e => setFilters({ ...filters, search: e.target.value })}
              />
            </div>
            <div className="col-12 col-md-4">
              <label htmlFor="fo24-section" className="form-label small text-muted mb-1">Section</label>
              <select
                id="fo24-section"
                className="form-select"
                value={filters.section}
                onChange={e => setFilters({ ...filters, section: e.target.value })}
              >
                <option value="">All Assigned Sections</option>
                {availableSections.map(sec => {
                  // API returns plain section strings; tolerate {id, name} objects too.
                  const value = typeof sec === 'string' ? sec : (sec?.name ?? '')
                  return <option key={value} value={value}>{formatYearSection(value) || value}</option>
                })}
              </select>
            </div>
          </div>

          <div className="table-responsive fo24-review__scroll">
            <table className="table align-middle mb-0 fo24-review__table">
              <thead>
                <tr>
                  <th scope="col" className="fo24-col-student">Student</th>
                  <th scope="col">Section</th>
                  <th scope="col">Company</th>
                  <th scope="col">Supervisor</th>
                  <th scope="col" className="text-center">Preview Evaluations</th>
                  <th scope="col" className="text-center">Evaluation Period</th>
                  <th scope="col" className="text-center">Faculty Evaluation</th>
                </tr>
              </thead>
              <tbody>
                {internships.length === 0 ? (
                  <tr><td colSpan={7} className="text-center text-muted py-5">No evaluations found matching the filters.</td></tr>
                ) : internships.map(intern => {
                  const p = intern.student?.student_profile || intern.student?.studentProfile
                  const name = p ? `${p.last_name || ''}, ${p.first_name || ''}`.trim() : intern.student?.student_number || intern.student?.email || '—'
                  const sup = intern.supervisor?.supervisor_profile || intern.supervisor?.supervisorProfile
                  const supName = sup ? `${sup.last_name || ''}, ${sup.first_name || ''}`.trim() : '—'
                  const fo24 = (intern.evaluations || []).find(e => e.form_type === 'FO-24')
                  const facultyEval = (intern.evaluations || []).find(e => e.form_type === 'faculty_eval')
                  // Authoritative state from the API (EvaluationPeriod); legacy field as fallback.
                  const period = intern.evaluation_period || {
                    status: intern.evaluation_period_status === 'approved' ? 'approved' : 'pending_faculty_approval',
                    label: intern.evaluation_period_status === 'approved' ? 'Approved' : 'Pending Faculty Approval',
                  }
                  const periodApproved = period.status === 'approved'

                  return (
                    <tr key={intern.id}>
                      <td className="fo24-col-student">
                        <div className="fw-semibold text-dark">{name}</div>
                        <div className="text-muted small">{p?.course_name || p?.program?.name || '—'}</div>
                      </td>
                      <td><span className="badge bg-secondary-subtle text-secondary-emphasis fw-semibold">{formatYearSection(p?.section) || '—'}</span></td>
                      <td><div className="fw-medium fo24-wrap">{intern.company?.company_name || '—'}</div></td>
                      <td>
                        <div className="fw-medium fo24-wrap">{supName}</div>
                        <div className="text-muted small">{sup?.position || 'Supervisor'}</div>
                      </td>
                      <td>
                        {fo24 ? (
                          <div className="fo24-actions">
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-primary"
                              aria-label={`Preview FO-24 for ${name}`}
                              onClick={() => setPreviewData({ eval: fo24, internship: intern })}
                            >
                              <i className="fa fa-eye me-1" aria-hidden="true"></i>Preview FO-24
                            </button>
                            {fo24.released_to_student_at ? (
                              <div className="fo24-actions__row">
                                <span className="badge bg-success-subtle text-success-emphasis"><i className="fa fa-user-check me-1" aria-hidden="true"></i>Released to student</span>
                                <AsyncButton
                                  className="btn btn-sm btn-outline-danger"
                                  busy={releasingId === intern.id}
                                  busyLabel="Hiding…"
                                  aria-label={`Hide FO-24 details from ${name}`}
                                  onClick={() => toggleRelease(intern, false)}
                                >
                                  Hide
                                </AsyncButton>
                              </div>
                            ) : (
                              <AsyncButton
                                className="btn btn-sm btn-success"
                                busy={releasingId === intern.id}
                                busyLabel="Releasing…"
                                aria-label={`Release FO-24 details to ${name}`}
                                onClick={() => toggleRelease(intern, true)}
                              >
                                <i className="fa fa-share me-1" aria-hidden="true"></i>Release to Student
                              </AsyncButton>
                            )}
                          </div>
                        ) : (
                          <div className="text-center text-muted small"><i className="fa fa-clock me-1" aria-hidden="true"></i>Not yet submitted</div>
                        )}
                      </td>
                      <td>
                        <div className="fo24-actions">
                          {periodApproved ? (
                            <span className="badge bg-success fo24-badge-lg"><i className="fa fa-unlock me-1" aria-hidden="true"></i>Approved</span>
                          ) : period.status === 'closed' ? (
                            <span className="badge bg-secondary fo24-badge-lg">{period.label}</span>
                          ) : (
                            <>
                              <span className="badge bg-warning-subtle text-warning-emphasis">{period.label}</span>
                              <AsyncButton
                                className="btn btn-sm btn-outline-warning"
                                busy={approvingId === intern.id}
                                busyLabel="Approving…"
                                aria-label={`Approve evaluation period for ${name}`}
                                onClick={() => approvePeriod(intern)}
                              >
                                <i className="fa fa-unlock me-1" aria-hidden="true"></i>Approve period
                              </AsyncButton>
                            </>
                          )}
                        </div>
                      </td>
                      <td>
                        <div className="fo24-actions">
                          {facultyEval && (
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-secondary"
                              aria-label={`Preview faculty evaluation for ${name}`}
                              onClick={() => setPreviewData({ eval: facultyEval, internship: intern, type: 'faculty_eval' })}
                            >
                              <i className="fa fa-eye me-1" aria-hidden="true"></i>Preview
                            </button>
                          )}
                          <button
                            type="button"
                            className="btn btn-sm btn-outline-success"
                            aria-label={`${facultyEval ? 'Update' : 'Submit'} faculty evaluation for ${name}`}
                            onClick={() => setSubmitModal({ internship: intern, existing: facultyEval })}
                          >
                            <i className="fa fa-pen me-1" aria-hidden="true"></i>{facultyEval ? 'Update' : 'Submit'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* Preview Modal */}
      {submitModal && (
        <FacultyEvalModal
          internship={submitModal.internship}
          existing={submitModal.existing}
          onClose={() => setSubmitModal(null)}
          onSaved={() => {
            setMessage({ type: 'success', text: 'Faculty evaluation submitted.' })
            setSubmitModal(null)
            fetchData()
          }}
        />
      )}
      <FormPreviewModal
        isOpen={!!previewData}
        onClose={() => setPreviewData(null)}
        type={previewData?.eval?.form_type || previewData?.type || 'FO-24'}
        data={{ evalData: previewData?.eval, internship: previewData?.internship }}
      />
    </Layout>
  )
}

export default FacultyEvaluations

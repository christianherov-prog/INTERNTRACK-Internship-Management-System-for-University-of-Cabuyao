import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import InternTrackLoader from '../../components/InternTrackLoader'
import api from '../../services/api'
import { useCachedPage } from '../../hooks/useCachedPage'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

const FORM_LABELS = {
  'FO-22': 'FO-22 HTE (Student)',
  'FO-23': 'FO-23 Program (Student)',
  'FO-24': 'FO-24 Performance (Supervisor)',
  'FO-03': 'FO-03 HTE to University (Supervisor)',
}

function termLabel(year, semester) {
  if (!year && !semester) return 'All periods'
  return `AY ${year || '—'}${semester ? `, ${semester}` : ''}`
}

function pctBar(value, tone = '#6366f1') {
  const n = Math.max(0, Math.min(100, Number(value) || 0))
  return (
    <div className="progress" style={{ height: '8px' }}>
      <div className="progress-bar" style={{ width: `${n}%`, background: tone }}></div>
    </div>
  )
}

export default function InternshipAnalytics({ apiBase, bodyClass, evaluationsPath, reportsPath }) {
  const currentTerm = useCurrentTerm()
  const cacheKey = `${apiBase.replace('/', '')}:analytics`
  const { loading, seed, run } = useCachedPage(cacheKey)
  const [data, setData] = useState(seed ?? null)
  const [error, setError] = useState(null)
  const [filters, setFilters] = useState({ school_year: '', semester: '', department_id: '' })

  const load = () => {
    setError(null)
    const params = new URLSearchParams()
    if (filters.school_year) params.set('school_year', filters.school_year)
    if (filters.semester) params.set('semester', filters.semester)
    if (filters.department_id) params.set('department_id', filters.department_id)
    const qs = params.toString()
    run(() => api.get(`${apiBase}/analytics${qs ? `?${qs}` : ''}`).then((res) => res.data))
      .then((next) => { if (next) setData(next) })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load analytics.')
        setData(null)
      })
  }

  useEffect(() => { load() }, [filters.school_year, filters.semester, filters.department_id])

  const cross = Boolean(data?.scope?.cross_department)
  const kpis = data?.kpis ?? {}
  const evals = data?.evaluations ?? {}
  const activity = data?.student_activity ?? {}
  const companies = data?.companies ?? {}
  const compliance = data?.compliance ?? {}
  const leaderboard = data?.department_leaderboard ?? []
  const periods = data?.period_comparison ?? []
  const heat = companies.applications_by_department ?? []
  const deptCodes = useMemo(() => {
    const codes = new Set()
    heat.forEach((row) => Object.keys(row.by_department || {}).forEach((c) => codes.add(c)))
    return [...codes].sort()
  }, [heat])

  const yearOptions = [...new Set((data?.available_terms ?? []).map((t) => t.school_year).filter(Boolean))]
  const semesterOptions = (data?.available_terms ?? [])
    .filter((t) => !filters.school_year || t.school_year === filters.school_year)
    .map((t) => t.semester)
    .filter(Boolean)
  const uniqueSemesters = [...new Set(semesterOptions)]

  return (
    <Layout title="Analytics" subtitle={currentTerm} icon="fa-chart-line" bodyClass={bodyClass}>
      {error && <PageError message={error} onRetry={load} />}

      <div className="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div>
          <label className="form-label small text-muted mb-1">Academic year</label>
          <select
            className="form-select form-select-sm"
            value={filters.school_year}
            onChange={(e) => setFilters({ ...filters, school_year: e.target.value, semester: '' })}
          >
            <option value="">All years</option>
            {yearOptions.map((y) => <option key={y} value={y}>{y}</option>)}
          </select>
        </div>
        <div>
          <label className="form-label small text-muted mb-1">Semester</label>
          <select
            className="form-select form-select-sm"
            value={filters.semester}
            onChange={(e) => setFilters({ ...filters, semester: e.target.value })}
          >
            <option value="">All semesters</option>
            {uniqueSemesters.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        {Array.isArray(data?.departments) && data.departments.length > 0 && (
          <div>
            <label className="form-label small text-muted mb-1">Department</label>
            <select
              className="form-select form-select-sm"
              value={filters.department_id}
              onChange={(e) => setFilters({ ...filters, department_id: e.target.value })}
            >
              <option value="">All departments</option>
              {data.departments.map((d) => (
                <option key={d.id} value={d.id}>{d.code} — {d.name}</option>
              ))}
            </select>
          </div>
        )}
        <div className="ms-auto d-flex gap-2">
          <Link className="btn btn-sm btn-outline-primary" to={evaluationsPath}>View evaluations</Link>
          <Link className="btn btn-sm btn-outline-secondary" to={reportsPath}>View reports</Link>
        </div>
      </div>

      {loading && !data ? (
        <div className="text-center py-5"><InternTrackLoader /></div>
      ) : (
        <>
          <div className="row g-3 mb-4">
            {[
              { label: 'Internships', value: kpis.internships ?? 0, icon: 'fa-briefcase' },
              { label: 'Avg hours', value: kpis.avg_hours ?? 0, icon: 'fa-clock' },
              { label: 'Applications', value: kpis.applications ?? 0, icon: 'fa-paper-plane' },
              { label: 'Eval completion', value: `${kpis.eval_completion_pct ?? 0}%`, icon: 'fa-star' },
              { label: 'Doc compliance', value: `${kpis.doc_compliance_pct ?? 0}%`, icon: 'fa-file-circle-check' },
            ].map((card) => (
              <div className="col" key={card.label}>
                <div className="stat-card h-100">
                  <div className="stat-icon blue"><i className={`fa ${card.icon}`}></i></div>
                  <div>
                    <div className="stat-value">{card.value}</div>
                    <div className="stat-label">{card.label}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {cross && leaderboard.length > 0 && (
            <div className="content-card mb-4">
              <div className="content-card-header">
                <i className="fa fa-university"></i>
                <h6>Department leaderboard</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0" style={{ fontSize: '0.85rem' }}>
                  <thead className="table-light">
                    <tr>
                      <th>Department</th>
                      <th>Interns</th>
                      <th>Avg hours</th>
                      <th>Applications</th>
                      <th>Eval completion</th>
                      <th>Doc compliance</th>
                    </tr>
                  </thead>
                  <tbody>
                    {leaderboard.map((row) => (
                      <tr key={row.department_code}>
                        <td className="fw-semibold">{row.department_code}<div className="text-muted small">{row.department_name}</div></td>
                        <td>{row.internships}</td>
                        <td>{row.avg_hours}</td>
                        <td>{row.applications}</td>
                        <td style={{ minWidth: 120 }}>{pctBar(row.eval_completion_pct, '#16a34a')}<small>{row.eval_completion_pct}%</small></td>
                        <td style={{ minWidth: 120 }}>{pctBar(row.doc_compliance_pct, '#0ea5e9')}<small>{row.doc_compliance_pct}%</small></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div className="row g-3 mb-4">
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-star"></i>
                  <h6>Evaluations</h6>
                </div>
                <div className="p-3">
                  {(evals.by_form || []).length === 0 ? (
                    <EmptyState icon="fa-star" title="No evaluations" message="Submitted forms for this period will appear here." />
                  ) : (
                    <table className="table table-sm mb-0" style={{ fontSize: '0.85rem' }}>
                      <thead><tr><th>Form</th><th>Submitted</th><th>Avg score</th><th>Completion</th></tr></thead>
                      <tbody>
                        {(evals.by_form || []).map((row) => (
                          <tr key={row.form_type}>
                            <td>{FORM_LABELS[row.form_type] || row.form_type}</td>
                            <td>{row.count}</td>
                            <td>{row.avg_score ?? '—'}</td>
                            <td>{evals.completion?.[row.form_type]?.pct ?? 0}%</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </div>
              </div>
            </div>
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-clipboard-check"></i>
                  <h6>Compliance</h6>
                </div>
                <div className="p-3">
                  <div className="mb-3">
                    <div className="d-flex justify-content-between small mb-1"><span>Approved documents</span><span>{compliance.document_pct ?? 0}%</span></div>
                    {pctBar(compliance.document_pct, '#0ea5e9')}
                  </div>
                  <div className="mb-3">
                    <div className="d-flex justify-content-between small mb-1"><span>Approved journals</span><span>{compliance.journal_pct ?? 0}%</span></div>
                    {pctBar(compliance.journal_pct, '#d97706')}
                  </div>
                  <div>
                    <div className="d-flex justify-content-between small mb-1"><span>Evaluation period approved</span><span>{compliance.eval_period_approved_pct ?? 0}%</span></div>
                    {pctBar(compliance.eval_period_approved_pct, '#16a34a')}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {cross && (evals.by_department || []).length > 0 && (
            <div className="content-card mb-4">
              <div className="content-card-header">
                <i className="fa fa-layer-group"></i>
                <h6>Evaluations by department</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-sm mb-0" style={{ fontSize: '0.85rem' }}>
                  <thead className="table-light"><tr><th>Department</th><th>Interns</th><th>Submitted</th><th>Avg score</th><th>Completion</th></tr></thead>
                  <tbody>
                    {evals.by_department.map((row) => (
                      <tr key={row.department_code}>
                        <td className="fw-semibold">{row.department_code}</td>
                        <td>{row.internships}</td>
                        <td>{row.submitted}</td>
                        <td>{row.avg_score ?? '—'}</td>
                        <td style={{ minWidth: 140 }}>{pctBar(row.completion_pct)}<small>{row.completion_pct}%</small></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div className="row g-3 mb-4">
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-user-clock"></i>
                  <h6>Most active student per company</h6>
                </div>
                {(activity.most_active_per_company || []).length === 0 ? (
                  <div className="p-3"><EmptyState icon="fa-user-clock" title="No activity yet" message="Hours rendered by company will appear here." /></div>
                ) : (
                  <div className="table-responsive">
                    <table className="table table-sm mb-0" style={{ fontSize: '0.85rem' }}>
                      <thead><tr><th>Company</th><th>Student</th><th>Hours</th>{cross && <th>Dept</th>}</tr></thead>
                      <tbody>
                        {activity.most_active_per_company.map((row) => (
                          <tr key={`${row.company_id}-${row.student_name}`}>
                            <td>{row.company}</td>
                            <td>{row.student_name}</td>
                            <td>{row.hours}</td>
                            {cross && <td>{row.department_code}</td>}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-building"></i>
                  <h6>Most-applied-to companies</h6>
                </div>
                {(companies.most_applied || []).length === 0 ? (
                  <div className="p-3"><EmptyState icon="fa-building" title="No applications" message="Company application counts for this period will appear here." /></div>
                ) : (
                  <div className="table-responsive">
                    <table className="table table-sm mb-0" style={{ fontSize: '0.85rem' }}>
                      <thead><tr><th>Company</th><th>Applications</th></tr></thead>
                      <tbody>
                        {companies.most_applied.map((row) => (
                          <tr key={row.company_id}>
                            <td>{row.company}</td>
                            <td>{row.applications}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          </div>

          {cross && heat.length > 0 && deptCodes.length > 0 && (
            <div className="content-card mb-4">
              <div className="content-card-header">
                <i className="fa fa-table"></i>
                <h6>Applications by company and department</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-sm mb-0" style={{ fontSize: '0.82rem' }}>
                  <thead className="table-light">
                    <tr>
                      <th>Company</th>
                      {deptCodes.map((code) => <th key={code} className="text-center">{code}</th>)}
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {heat.map((row) => (
                      <tr key={row.company_id}>
                        <td>{row.company}</td>
                        {deptCodes.map((code) => (
                          <td key={code} className="text-center">{row.by_department?.[code] || 0}</td>
                        ))}
                        <td className="fw-semibold">{row.applications}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div className="content-card mb-4">
            <div className="content-card-header">
              <i className="fa fa-calendar-alt"></i>
              <h6>AY / Semester comparison</h6>
            </div>
            {periods.length === 0 ? (
              <div className="p-3"><EmptyState icon="fa-calendar" title="No period data" message="Internship terms will appear here for comparison." /></div>
            ) : (
              <div className="table-responsive">
                <table className="table table-sm mb-0" style={{ fontSize: '0.85rem' }}>
                  <thead className="table-light">
                    <tr><th>Period</th><th>Interns</th><th>Avg hours</th><th>Evaluations</th><th>Eval completion</th></tr>
                  </thead>
                  <tbody>
                    {periods.map((row) => (
                      <tr key={`${row.school_year}-${row.semester}`}>
                        <td className="fw-semibold">{termLabel(row.school_year, row.semester)}</td>
                        <td>{row.internships}</td>
                        <td>{row.avg_hours}</td>
                        <td>{row.evaluations_submitted}</td>
                        <td style={{ minWidth: 140 }}>{pctBar(row.eval_completion_pct, '#16a34a')}<small>{row.eval_completion_pct}%</small></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}
    </Layout>
  )
}

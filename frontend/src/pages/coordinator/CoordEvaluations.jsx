import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import { formatStudentName } from '../../utils/formatName'

const FORM_LABELS = {
  'FO-24': { label: 'FO-24', color: 'btn-outline-primary',  desc: 'Performance Eval (Supervisor)' },
  'FO-03': { label: 'FO-03', color: 'btn-outline-success',  desc: 'HTE Eval (Supervisor)' },
  'FO-22': { label: 'FO-22', color: 'btn-outline-info',     desc: 'HTE Eval (Student)' },
  'FO-23': { label: 'FO-23', color: 'btn-outline-warning',  desc: 'Program Eval (Student)' },
}

function CoordEvaluations() {
  const [internships, setInternships] = useState([])
  const [facultyOptions, setFacultyOptions] = useState([])
  const [feedback, setFeedback] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [previewData, setPreviewData] = useState(null)  // { eval, internship }
  const [filters, setFilters] = useState({ program: '', section: '', faculty_id: '', search: '' })
  const [pagination, setPagination] = useState(null)
  const [currentPage, setCurrentPage] = useState(1)

  const loadPage = (page = 1) => {
    setLoading(true)
    setError(null)
    const params = new URLSearchParams({ page, ...filters })
    Promise.all([
      api.get(`/coordinator/evaluations?${params.toString()}`),
      api.get('/coordinator/supervisor-feedback'),
    ])
      .then(([eRes, fRes]) => {
        const data = eRes.data
        setInternships(data.internships?.data || data.internships || [])
        setPagination(data.internships)
        setFacultyOptions(data.faculty_options || [])
        setFeedback(fRes.data?.data || fRes.data?.items || [])
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
        setInternships([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { loadPage(1) }, [filters])

  const studentOf = (intern) => formatStudentName(intern)

  return (
    <Layout title="Evaluations Oversight" subtitle="Coordinator" icon="fa-star" bodyClass="coordinator-page">
      {error && <PageError message={error} onRetry={() => loadPage(1)} />}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search student name…" value={filters.search} onChange={e => setFilters({ ...filters, search: e.target.value })} />
        </div>
        <div className="input-group input-group-sm" style={{ width: 170 }}>
          <input className="form-control" placeholder="Program (e.g. BS IT)" value={filters.program} onChange={e => setFilters({ ...filters, program: e.target.value })} />
        </div>
        <div className="input-group input-group-sm" style={{ width: 150 }}>
          <input className="form-control" placeholder="Section (e.g. 4A)" value={filters.section} onChange={e => setFilters({ ...filters, section: e.target.value })} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 180 }} value={filters.faculty_id} onChange={e => setFilters({ ...filters, faculty_id: e.target.value })}>
          <option value="">All Faculty</option>
          {facultyOptions.map(f => (
            <option key={f.id} value={f.id}>{f.first_name} {f.last_name}</option>
          ))}
        </select>
      </div>

      {/* Student Evaluations Table */}
      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-star"></i>
          <h6>Submitted Evaluations (FO-24, FO-03, FO-22, FO-23)</h6>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <>
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th className="ps-4">Student</th>
                      <th>Program / Section</th>
                      <th>Company</th>
                      <th>Faculty</th>
                      <th className="text-center">Preview Evaluations</th>
                    </tr>
                  </thead>
                  <tbody>
                    {internships.length === 0 ? (
                      <tr><td colSpan={5} className="text-center text-muted py-4">No evaluations found.</td></tr>
                    ) : internships.map((intern) => {
                      const p = intern.student?.student_profile || intern.student?.studentProfile
                      const name = studentOf(intern)
                      const fac = intern.faculty?.faculty_profile || intern.faculty?.facultyProfile
                      const facName = fac ? `${fac.first_name || ''} ${fac.last_name || ''}`.trim() : '—'
                      const evals = intern.evaluations || []

                      return (
                        <tr key={intern.id}>
                          <td className="ps-4">
                            <div className="fw-semibold">{name}</div>
                            <small className="text-muted">{intern.student?.student_number || intern.student?.email}</small>
                          </td>
                          <td>
                            <div className="small fw-semibold">{p?.course_name || '—'}</div>
                            <div className="small text-muted">Section: {p?.section || '—'}</div>
                          </td>
                          <td>
                            <div className="small">{intern.company?.company_name || '—'}</div>
                          </td>
                          <td>
                            <div className="small">{facName}</div>
                          </td>
                          <td className="text-center">
                            <div className="d-flex flex-wrap gap-1 justify-content-center">
                              {Object.entries(FORM_LABELS).map(([formType, { label, color }]) => {
                                const ev = evals.find(e => e.form_type === formType)
                                if (!ev) return (
                                  <span key={formType} className="btn btn-sm btn-light disabled text-muted px-2" style={{ fontSize: '0.75rem', opacity: 0.5 }}>
                                    {label}
                                  </span>
                                )
                                return (
                                  <button key={formType} className={`btn btn-sm ${color} px-2`} style={{ fontSize: '0.75rem' }}
                                    onClick={() => setPreviewData({ eval: ev, internship: intern })}>
                                    <i className="fa fa-eye me-1"></i>Preview {label}
                                  </button>
                                )
                              })}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              {pagination && pagination.last_page > 1 && (
                <div className="d-flex justify-content-between align-items-center p-3">
                  <small className="text-muted">Showing {pagination.from}–{pagination.to} of {pagination.total} students</small>
                  <div className="d-flex gap-2">
                    <button className="btn btn-sm btn-outline-secondary" disabled={currentPage <= 1 || loading}
                      onClick={() => { const p = currentPage - 1; setCurrentPage(p); loadPage(p) }}>
                      <i className="fa fa-chevron-left me-1"></i>Previous
                    </button>
                    <span className="btn btn-sm btn-light disabled">Page {pagination.current_page} / {pagination.last_page}</span>
                    <button className="btn btn-sm btn-outline-secondary" disabled={currentPage >= pagination.last_page || loading}
                      onClick={() => { const p = currentPage + 1; setCurrentPage(p); loadPage(p) }}>
                      Next<i className="fa fa-chevron-right ms-1"></i>
                    </button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* Supervisor Narrative Feedback */}
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
                    <td className="fw-semibold">{row?.internship?.student?.studentProfile?.first_name} {row?.internship?.student?.studentProfile?.last_name}</td>
                    <td style={{ maxWidth: 420 }}>{row.supervisor_feedback}</td>
                    <td style={{ fontSize: '0.85rem' }}>
                      {row.supervisor_reviewed_at ? new Date(row.supervisor_reviewed_at).toLocaleDateString() : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <FormPreviewModal
        isOpen={!!previewData}
        onClose={() => setPreviewData(null)}
        type={previewData?.eval?.form_type || 'FO-24'}
        data={{ evalData: previewData?.eval, internship: previewData?.internship }}
      />
    </Layout>
  )
}

export default CoordEvaluations

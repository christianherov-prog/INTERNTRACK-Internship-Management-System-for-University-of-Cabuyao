import React, { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import api from '../../services/api'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'

const FORM_LABELS = {
  'FO-24': { label: 'FO-24', color: 'btn-outline-primary', desc: 'Performance Eval (Supervisor)' },
  'FO-03': { label: 'FO-03', color: 'btn-outline-success', desc: 'HTE Eval (Supervisor)' },
  'FO-22': { label: 'FO-22', color: 'btn-outline-info',    desc: 'HTE Eval (Student)' },
  'FO-23': { label: 'FO-23', color: 'btn-outline-warning', desc: 'Program Eval (Student)' },
}

export default function DirectorHTEEvaluations() {
  const [internships, setInternships] = useState([])
  const [stats, setStats] = useState(null)
  const [ratingCounts, setRatingCounts] = useState({})
  const [formCounts, setFormCounts] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [previewData, setPreviewData] = useState(null)   // { eval, internship }
  const [currentPage, setCurrentPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [filters, setFilters] = useState({ department: '', program: '', section: '' })

  const loadPage = (page = 1) => {
    setLoading(true)
    const params = new URLSearchParams({ page, ...filters })
    api.get(`/director/evaluations?${params.toString()}`)
      .then(res => {
        const { internships: iData, stats: s, rating_counts, form_counts } = res.data
        setInternships(iData.data || [])
        setPagination(iData)
        setStats(s)
        setRatingCounts(rating_counts || {})
        setFormCounts(form_counts || {})
      })
      .catch(err => setError(err.response?.data?.message || 'Failed to load evaluations'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { loadPage(1) }, [filters])

  const totalEvals = stats?.total || 0
  const avgScore   = parseFloat(stats?.avg_score || 0)

  const openPreview = (evalItem, internship) => {
    setPreviewData({ eval: evalItem, internship })
  }

  return (
    <Layout title="Evaluations Overview" subtitle="All submitted evaluation forms by Department, Program, and Section." icon="fa-star" bodyClass="student-page">
      <div className="container-fluid px-4 py-4">
        {loading && <div className="text-center py-5"><div className="spinner-border text-primary" role="status"></div></div>}
        {error && <div className="alert alert-danger">{error}</div>}

        {!loading && !error && (
          <>
            {/* Filters */}
            <div className="card border-0 shadow-sm mb-4">
              <div className="card-body p-3">
                <div className="row g-2 align-items-end">
                  <div className="col-md-4">
                    <label className="form-label small text-muted mb-1">Department</label>
                    <select className="form-select form-select-sm" value={filters.department} onChange={e => setFilters({...filters, department: e.target.value})}>
                      <option value="">All Departments</option>
                      <option value="Computer Studies">Computer Studies</option>
                      <option value="Engineering">Engineering</option>
                      <option value="Business">Business</option>
                    </select>
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small text-muted mb-1">Program</label>
                    <input type="text" className="form-control form-control-sm" placeholder="e.g. BS Information Technology" value={filters.program} onChange={e => setFilters({...filters, program: e.target.value})} />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small text-muted mb-1">Section</label>
                    <input type="text" className="form-control form-control-sm" placeholder="e.g. 4A" value={filters.section} onChange={e => setFilters({...filters, section: e.target.value})} />
                  </div>
                </div>
              </div>
            </div>

            {/* Analytics Summary */}
            <div className="row g-3 mb-4">
              <div className="col-md-3">
                <div className="card border-0 shadow-sm h-100 p-3 text-center">
                  <h6 className="text-muted mb-2">Total Evaluations</h6>
                  <h2 className="fw-bold mb-0 text-primary">{totalEvals}</h2>
                </div>
              </div>
              <div className="col-md-3">
                <div className="card border-0 shadow-sm h-100 p-3 text-center">
                  <h6 className="text-muted mb-2">Overall Average Score</h6>
                  <h2 className="fw-bold mb-0 text-success">{avgScore.toFixed(2)}</h2>
                  <small className="text-muted">Out of 5.00</small>
                </div>
              </div>
              <div className="col-md-3">
                <div className="card border-0 shadow-sm h-100 p-3">
                  <h6 className="text-muted mb-2 text-center">By Form Type</h6>
                  <div className="d-flex flex-column gap-1">
                    {Object.entries(FORM_LABELS).map(([key, { label }]) => (
                      <div key={key} className="d-flex justify-content-between small">
                        <span className="fw-semibold">{label}</span>
                        <span className="badge bg-secondary">{formCounts[key] || 0}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
              <div className="col-md-3">
                <div className="card border-0 shadow-sm h-100 p-3">
                  <h6 className="text-muted mb-2 text-center">Rating Distribution</h6>
                  <div className="d-flex flex-column gap-1">
                    {Object.entries(ratingCounts).map(([rating, count]) => (
                      <div key={rating} className="d-flex justify-content-between small">
                        <span>{rating}</span>
                        <span className={`badge ${rating === 'Failed' ? 'bg-danger' : rating === 'Excellent' ? 'bg-success' : 'bg-primary'}`}>{count}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            {/* Evaluations Table — grouped by student */}
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white border-bottom py-3 px-4">
                <h6 className="mb-0 fw-bold"><i className="fa fa-list me-2 text-primary"></i>Student Evaluations</h6>
              </div>
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th className="ps-4 py-3">Student</th>
                      <th>Program / Section</th>
                      <th>Company</th>
                      <th>Supervisor</th>
                      <th className="text-center">Preview Evaluations</th>
                    </tr>
                  </thead>
                  <tbody>
                    {internships.length === 0 ? (
                      <tr><td colSpan="5" className="text-center py-5 text-muted">No evaluations found with the current filters.</td></tr>
                    ) : internships.map(intern => {
                      const p = intern.student?.student_profile || intern.student?.studentProfile
                      const name = p ? `${p.last_name || ''}, ${p.first_name || ''}`.trim() : intern.student?.student_number || intern.student?.email || '—'
                      const sup = intern.supervisor?.supervisor_profile || intern.supervisor?.supervisorProfile
                      const supName = sup ? `${sup.last_name || ''}, ${sup.first_name || ''}`.trim() : '—'
                      const evals = intern.evaluations || []

                      return (
                        <tr key={intern.id}>
                          <td className="ps-4">
                            <div className="fw-bold">{name}</div>
                            <small className="text-muted">{intern.student?.username}</small>
                          </td>
                          <td>
                            <div className="small fw-semibold">{p?.course_name || '—'}</div>
                            <div className="small text-muted">Section: {p?.section || '—'}</div>
                          </td>
                          <td>
                            <div className="small fw-semibold">{intern.company?.company_name || '—'}</div>
                          </td>
                          <td>
                            <div className="small">{supName}</div>
                            <div className="small text-muted">{sup?.position || '—'}</div>
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
                                    onClick={() => openPreview(ev, intern)}>
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
            </div>
          </>
        )}
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

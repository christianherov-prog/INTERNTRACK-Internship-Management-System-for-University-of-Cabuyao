import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'

function FacultyEvaluations() {
  const currentTerm = useCurrentTerm()
  const [internships, setInternships] = useState([])
  const [availableSections, setAvailableSections] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [previewData, setPreviewData] = useState(null)  // { eval, internship }
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
    setLoading(true)
    setError(null)
    const params = new URLSearchParams()
    if (debouncedSearch) params.append('search', debouncedSearch)
    if (filters.section) params.append('section', filters.section)

    api.get(`/faculty/evaluations?${params.toString()}`)
      .then(res => {
        setInternships(res.data.internships || [])
        setAvailableSections(res.data.available_sections || [])
      })
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load evaluations.')
        setInternships([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchData() }, [debouncedSearch, filters.section])

  return (
    <Layout title="Evaluation Review — FO-24" subtitle={currentTerm} icon="fa-search" bodyClass="faculty-page">
      {error && <PageError message={error} onRetry={fetchData} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        <div className="content-card">
          <div className="content-card-header">
            <i className="fa fa-check-circle text-success"></i>
            <h6>FO-24 — Student Internship Performance Evaluation (Official Basis for Grading)</h6>
            <span className="ms-auto badge bg-success">{internships.length}</span>
          </div>

          <div className="p-3 bg-light border-bottom text-muted" style={{ fontSize: '0.88rem' }}>
            <i className="fa fa-info-circle me-2"></i>
            As Faculty, you have access to the <strong>FO-24</strong> (Supervisor Performance Evaluation) submitted by the Company Supervisor. This serves as the official basis for grading your assigned students.
          </div>

          {/* Filters */}
          <div className="p-3 border-bottom bg-white d-flex gap-3">
            <div style={{ flex: '1' }}>
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder="Search by student name..."
                value={filters.search}
                onChange={e => setFilters({ ...filters, search: e.target.value })}
              />
            </div>
            <div style={{ width: '200px' }}>
              <select
                className="form-select form-select-sm"
                value={filters.section}
                onChange={e => setFilters({ ...filters, section: e.target.value })}
              >
                <option value="">All Assigned Sections</option>
                {availableSections.map(sec => (
                  <option key={sec.id} value={sec.name}>{sec.name}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th className="ps-4 py-3">Student</th>
                  <th>Section</th>
                  <th>Company</th>
                  <th>Supervisor</th>
                  <th className="text-center">Preview Evaluations</th>
                </tr>
              </thead>
              <tbody>
                {internships.length === 0 ? (
                  <tr><td colSpan={5} className="text-center text-muted py-4">No evaluations found matching the filters.</td></tr>
                ) : internships.map(intern => {
                  const p = intern.student?.student_profile || intern.student?.studentProfile
                  const name = p ? `${p.first_name || ''} ${p.last_name || ''}`.trim() : intern.student?.student_number || intern.student?.email || '—'
                  const sup = intern.supervisor?.supervisor_profile || intern.supervisor?.supervisorProfile
                  const supName = sup ? `${sup.first_name || ''} ${sup.last_name || ''}`.trim() : '—'
                  const fo24 = (intern.evaluations || []).find(e => e.form_type === 'FO-24')

                  return (
                    <tr key={intern.id}>
                      <td className="ps-4">
                        <div className="fw-semibold">{name}</div>
                        <div className="text-muted" style={{ fontSize: '0.82rem' }}>
                          {p?.course_name || '—'}
                        </div>
                      </td>
                      <td><span className="badge bg-secondary">{p?.section || '—'}</span></td>
                      <td>
                        <div className="fw-medium">{intern.company?.company_name || '—'}</div>
                      </td>
                      <td>
                        <div className="fw-medium">{supName}</div>
                        <div className="text-muted" style={{ fontSize: '0.82rem' }}>{sup?.position || 'Supervisor'}</div>
                      </td>
                      <td className="text-center pe-4">
                        {fo24 ? (
                          <button
                            className="btn btn-sm btn-outline-primary px-3"
                            onClick={() => setPreviewData({ eval: fo24, internship: intern })}
                          >
                            <i className="fa fa-eye me-1"></i>Preview FO-24
                          </button>
                        ) : (
                          <span className="text-muted small"><i className="fa fa-clock me-1"></i>Not yet submitted</span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Preview Modal */}
      <FormPreviewModal
        isOpen={!!previewData}
        onClose={() => setPreviewData(null)}
        type={previewData?.eval?.form_type || 'FO-24'}
        data={{ evalData: previewData?.eval, internship: previewData?.internship }}
      />
    </Layout>
  )
}

export default FacultyEvaluations

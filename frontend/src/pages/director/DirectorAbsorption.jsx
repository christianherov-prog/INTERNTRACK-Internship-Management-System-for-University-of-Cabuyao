import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'

function DirectorAbsorption() {
  const [absorption, setAbsorption] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/director/dashboard')
      .then((res) => setAbsorption(res.data.absorption ?? null))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load absorption analytics.')
        setAbsorption(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const a = absorption ?? {}
  const byCompany = a.by_company ?? []

  return (
    <Layout title="Absorption" subtitle="Hire outcomes across completed internships" icon="fa-user-check" bodyClass="director-page">
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row g-3 mb-4">
            <div className="col-md-3 col-6">
              <div className="content-card p-3 text-center h-100">
                <div className="text-muted small">Completed</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 700 }}>{a.completed_internships ?? 0}</div>
              </div>
            </div>
            <div className="col-md-3 col-6">
              <div className="content-card p-3 text-center h-100">
                <div className="text-muted small">Absorbed</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 700, color: '#16a34a' }}>{a.absorbed ?? 0}</div>
              </div>
            </div>
            <div className="col-md-3 col-6">
              <div className="content-card p-3 text-center h-100">
                <div className="text-muted small">Not Hired</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 700, color: '#dc2626' }}>{a.not_hired ?? 0}</div>
              </div>
            </div>
            <div className="col-md-3 col-6">
              <div className="content-card p-3 text-center h-100">
                <div className="text-muted small">Absorption Rate</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 700 }}>
                  {a.absorption_rate != null ? `${a.absorption_rate}%` : '—'}
                </div>
              </div>
            </div>
          </div>

          <div className="row g-3 mb-4">
            <div className="col-md-6">
              <div className="content-card p-3">
                <div className="text-muted small mb-1">Pending confirmation</div>
                <div className="fw-semibold" style={{ fontSize: '1.25rem' }}>{a.pending ?? 0}</div>
              </div>
            </div>
            <div className="col-md-6">
              <div className="content-card p-3">
                <div className="text-muted small mb-1">Student hire declarations</div>
                <div className="fw-semibold" style={{ fontSize: '1.25rem' }}>{a.student_declarations ?? 0}</div>
              </div>
            </div>
          </div>

          <div className="content-card">
            <div className="content-card-header">
              <i className="fa fa-building"></i>
              <h6>Absorption by Company</h6>
            </div>
            {byCompany.length === 0 ? (
              <p className="text-muted text-center py-4 mb-0">No completed internships yet.</p>
            ) : (
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Company</th>
                      <th>Completed</th>
                      <th>Absorbed</th>
                      <th>Not Hired</th>
                      <th>Pending</th>
                      <th>Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    {byCompany.map((row) => (
                      <tr key={row.company}>
                        <td className="fw-semibold">{row.company}</td>
                        <td>{row.completed}</td>
                        <td><span className="badge bg-success">{row.absorbed}</span></td>
                        <td><span className="badge bg-danger">{row.not_hired}</span></td>
                        <td><span className="badge bg-warning text-dark">{row.pending}</span></td>
                        <td>{row.rate != null ? `${row.rate}%` : '—'}</td>
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

export default DirectorAbsorption

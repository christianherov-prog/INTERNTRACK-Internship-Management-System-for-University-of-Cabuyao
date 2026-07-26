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

  const stats = [
    {
      key: 'completed',
      label: 'Completed',
      value: a.completed_internships ?? 0,
      icon: 'fa-graduation-cap',
      tone: 'teal',
    },
    {
      key: 'absorbed',
      label: 'Absorbed',
      value: a.absorbed ?? 0,
      icon: 'fa-user-check',
      tone: 'green',
      valueClass: 'text-success',
    },
    {
      key: 'not_hired',
      label: 'Not Hired',
      value: a.not_hired ?? 0,
      icon: 'fa-user-xmark',
      tone: 'red',
      valueClass: 'text-danger',
    },
    {
      key: 'rate',
      label: 'Absorption Rate',
      value: a.absorption_rate != null ? `${a.absorption_rate}%` : '—',
      icon: 'fa-chart-pie',
      tone: 'amber',
    },
    {
      key: 'pending',
      label: 'Pending confirmation',
      value: a.pending ?? 0,
      icon: 'fa-clock',
      tone: 'amber',
    },
    {
      key: 'declarations',
      label: 'Student hire declarations',
      value: a.student_declarations ?? 0,
      icon: 'fa-file-signature',
      tone: 'blue',
    },
  ]

  return (
    <Layout title="Absorption" subtitle="Hire outcomes across completed internships" icon="fa-user-check" bodyClass="director-page">
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row g-3 mb-4">
            {stats.slice(0, 4).map((s) => (
              <div className="col-sm-6 col-xl-3" key={s.key}>
                <div className="stat-card dir-absorption-stat h-100">
                  <div className={`stat-icon ${s.tone}`}><i className={`fa ${s.icon}`} aria-hidden="true" /></div>
                  <div>
                    <div className={`stat-value ${s.valueClass || ''}`.trim()}>{s.value}</div>
                    <div className="stat-label">{s.label}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div className="row g-3 mb-4">
            {stats.slice(4).map((s) => (
              <div className="col-md-6" key={s.key}>
                <div className="stat-card dir-absorption-stat h-100">
                  <div className={`stat-icon ${s.tone}`}><i className={`fa ${s.icon}`} aria-hidden="true" /></div>
                  <div>
                    <div className={`stat-value ${s.valueClass || ''}`.trim()}>{s.value}</div>
                    <div className="stat-label">{s.label}</div>
                  </div>
                </div>
              </div>
            ))}
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
                        <td><span className="badge bg-success rounded-pill">{row.absorbed}</span></td>
                        <td><span className="badge bg-danger rounded-pill">{row.not_hired}</span></td>
                        <td><span className="badge bg-warning text-dark rounded-pill">{row.pending}</span></td>
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

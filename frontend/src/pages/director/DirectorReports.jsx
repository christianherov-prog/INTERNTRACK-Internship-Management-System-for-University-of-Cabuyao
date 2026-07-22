import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { downloadCsv } from '../../utils/csv'
import { CURRENT_TERM } from '../../config/term'

function DirectorReports() {
  const [data, setData] = useState(null)
  const [trends, setTrends] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    Promise.all([
      api.get('/director/dashboard'),
      api.get('/director/reports/placement-trends'),
    ])
      .then(([dash, trendRes]) => {
        setData(dash.data)
        setTrends(trendRes.data)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load reports.')
        setData(null)
        setTrends(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const stats = data?.stats ?? {}
  const byProgram = data?.by_program ?? []
  const topCompanies = data?.top_companies ?? []
  const moaByStatus = data?.moa_by_status ?? {}
  const years = trends?.academic_years ?? []
  const trendCompanies = trends?.by_company ?? []

  const exportInternshipSummary = () => {
    downloadCsv('internship-summary-by-program', byProgram.map((p) => ({
      Program: p.program ?? 'Unknown',
      Ongoing: p.ongoing ?? 0,
      Completed: p.completed ?? 0,
      Total: p.count ?? 0,
    })))
  }

  const exportCompanies = () => {
    downloadCsv('company-partnerships', topCompanies.map((c) => ({
      Company: c.company_name,
      Industry: c.industry ?? '—',
      'MOA Status': c.moa_status ?? '—',
      Interns: c.internships_count ?? 0,
    })))
  }

  const exportMoa = () => {
    downloadCsv('moa-status-summary', Object.entries(moaByStatus).map(([status, count]) => ({
      Status: status,
      Count: count,
    })))
  }

  const exportTrends = () => {
    downloadCsv('placement-trends-3yr', trendCompanies.map((c) => {
      const row = {
        Company: c.company_name,
        Industry: c.industry ?? '—',
        Total: c.total,
      }
      years.forEach((y) => { row[y] = c.years?.[y] ?? 0 })
      return row
    }))
  }

  return (
    <Layout title="Reports" subtitle={CURRENT_TERM} icon="fa-chart-bar" bodyClass="director-page">
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row g-3 mb-4">
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon green"><i className="fa fa-users"></i></div><div><div className="stat-value">{stats.active_interns ?? 0}</div><div className="stat-label">Active Interns</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon teal"><i className="fa fa-building"></i></div><div><div className="stat-value">{stats.partner_companies ?? 0}</div><div className="stat-label">Partner Companies</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon blue"><i className="fa fa-trophy"></i></div><div><div className="stat-value">{stats.completed ?? 0}</div><div className="stat-label">Completed</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon amber"><i className="fa fa-chart-pie"></i></div><div><div className="stat-value">{stats.placement_rate ?? 0}%</div><div className="stat-label">Placement Rate</div></div></div>
            </div>
          </div>

          <div className="row g-3 mb-4">
            <div className="col-md-4">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-graduation-cap"></i>
                  <h6>Internship Summary</h6>
                </div>
                <div className="p-3">
                  <p className="text-muted small mb-3">Interns by program (ongoing vs completed) from live analytics.</p>
                  <button type="button" className="btn-green btn-sm" onClick={exportInternshipSummary} disabled={!byProgram.length}>
                    <i className="fa fa-file-csv me-1"></i>Export CSV
                  </button>
                  {!byProgram.length && <p className="text-muted small mt-3 mb-0">No program data yet.</p>}
                </div>
              </div>
            </div>
            <div className="col-md-4">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-building"></i>
                  <h6>Company Partnerships</h6>
                </div>
                <div className="p-3">
                  <p className="text-muted small mb-3">Top partner companies by intern count.</p>
                  <button type="button" className="btn-green btn-sm" onClick={exportCompanies} disabled={!topCompanies.length}>
                    <i className="fa fa-file-csv me-1"></i>Export CSV
                  </button>
                  {!topCompanies.length && <p className="text-muted small mt-3 mb-0">No company data yet.</p>}
                </div>
              </div>
            </div>
            <div className="col-md-4">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-file-signature"></i>
                  <h6>MOA Status Report</h6>
                </div>
                <div className="p-3">
                  <p className="text-muted small mb-3">Counts of companies by MOA status.</p>
                  <button type="button" className="btn-green btn-sm" onClick={exportMoa} disabled={!Object.keys(moaByStatus).length}>
                    <i className="fa fa-file-csv me-1"></i>Export CSV
                  </button>
                  {!Object.keys(moaByStatus).length && <p className="text-muted small mt-3 mb-0">No MOA data yet.</p>}
                </div>
              </div>
            </div>
          </div>

          <div className="content-card">
            <div className="content-card-header">
              <i className="fa fa-chart-line"></i>
              <h6>3-Year Placement Trends by Company</h6>
              <button type="button" className="btn btn-sm btn-outline-success ms-auto" onClick={exportTrends} disabled={!trendCompanies.length}>
                <i className="fa fa-file-csv me-1"></i>Export CSV
              </button>
            </div>
            <div className="table-card">
              {!trendCompanies.length ? (
                <div className="text-center py-4 text-muted">No placement data for the last three academic years.</div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover mb-0" style={{ fontSize: '0.85rem' }}>
                    <thead>
                      <tr>
                        <th>Company</th>
                        <th>Industry</th>
                        {years.map((y) => <th key={y} className="text-center">{y}</th>)}
                        <th className="text-center">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {trendCompanies.map((c) => (
                        <tr key={c.company_id}>
                          <td className="fw-semibold">{c.company_name}</td>
                          <td>{c.industry || '—'}</td>
                          {years.map((y) => (
                            <td key={y} className="text-center">{c.years?.[y] ?? 0}</td>
                          ))}
                          <td className="text-center fw-semibold">{c.total}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              <p className="text-muted px-3 pb-3 mb-0" style={{ fontSize: '0.78rem' }}>
                Aggregated via SQL GROUP BY company + academic year. Years: {years.join(', ') || '—'}.
              </p>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default DirectorReports

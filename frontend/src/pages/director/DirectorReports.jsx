import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { downloadCsv } from '../../utils/csv'
import { CURRENT_TERM } from '../../config/term'

function DirectorReports() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/director/dashboard')
      .then((res) => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load reports.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const stats = data?.stats ?? {}
  const byProgram = data?.by_program ?? []
  const topCompanies = data?.top_companies ?? []
  const moaByStatus = data?.moa_by_status ?? {}

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

          <div className="row g-3">
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
        </>
      )}
    </Layout>
  )
}

export default DirectorReports

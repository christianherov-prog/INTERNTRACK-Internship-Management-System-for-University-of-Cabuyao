import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { downloadCsv } from '../../utils/csv'
import { unwrapList } from '../../utils/apiList'

function CoordMonitoring() {
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)
  const [search, setSearch]   = useState('')

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/coordinator/monitoring')
      .then(res => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load monitoring data.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const s = data?.stats ?? {}
  const rows = unwrapList(data).items.filter(r =>
    !search || r.student_name?.toLowerCase().includes(search.toLowerCase()) || r.program?.toLowerCase().includes(search.toLowerCase())
  )

  const progressColor = (pct) => {
    if (pct >= 75) return '#14b8a6'
    if (pct >= 40) return '#f59e0b'
    return '#ef4444'
  }

  const handleExportCsv = () => {
    downloadCsv('intern-monitoring', rows.map(r => ({
      Student: r.student_name, Program: r.program, Supervisor: r.supervisor_name, Company: r.company,
      'Hours Rendered': r.hours_rendered, 'Target Hours': r.target_hours, 'Progress %': r.progress_percent,
      Documents: r.docs_label, 'Last Journal': r.last_journal_date ?? '—', 'Journal Status': r.journal_status,
    })))
  }

  return (
    <Layout title="Intern Monitoring" subtitle="AY 2024-2025, Sem 2" icon="fa-eye" bodyClass="coordinator-page">
      <RoleSummaryPanel />
      {error && <PageError message={error} onRetry={load} />}

      {/* Operational monitoring stats (system-wide) */}
      {!error && (
      <div className="row g-3 mb-4">
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card"><div className="stat-icon green"><i className="fa fa-users"></i></div><div><div className="stat-value">{s.active_interns ?? 0}</div><div className="stat-label">Active Interns (All)</div></div></div>
        </div>
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card"><div className="stat-icon teal"><i className="fa fa-chart-line"></i></div><div><div className="stat-value">{s.avg_hours_completion ?? 0}%</div><div className="stat-label">Avg. Completion</div></div></div>
        </div>
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card"><div className="stat-icon amber"><i className="fa fa-triangle-exclamation"></i></div><div><div className="stat-value">{s.at_risk_students ?? 0}</div><div className="stat-label">At-Risk Students</div></div></div>
        </div>
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card"><div className="stat-icon blue"><i className="fa fa-circle-check"></i></div><div><div className="stat-value">{s.fully_completed ?? 0}</div><div className="stat-label">Completed</div></div></div>
        </div>
      </div>
      )}

      {/* Table */}
      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-table"></i>
          <h6>Intern Overview</h6>
          <div className="ms-auto d-flex gap-2">
            <input className="form-control form-control-sm" style={{width:'200px'}} placeholder="Search student…" value={search} onChange={e => setSearch(e.target.value)} />
            <button className="btn btn-sm btn-outline-success" onClick={handleExportCsv} disabled={rows.length === 0}>
              <i className="fa fa-file-csv me-1"></i>Export CSV
            </button>
          </div>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr><th>Student</th><th>Program</th><th>Supervisor</th><th>Company</th><th>Progress</th><th>Documents</th><th>Last Journal</th></tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr><td colSpan={7} className="text-center text-muted py-4">No interns found.</td></tr>
                  ) : rows.map(r => (
                    <tr key={r.internship_id}>
                      <td className="fw-semibold">{r.student_name}</td>
                      <td style={{fontSize:'0.82rem'}}>{r.program}</td>
                      <td style={{fontSize:'0.82rem'}}>{r.supervisor_name}</td>
                      <td style={{fontSize:'0.82rem'}}>{r.company}</td>
                      <td style={{minWidth:'130px'}}>
                        <div className="progress mb-1" style={{height:'8px'}}>
                          <div className="progress-bar" style={{width:`${r.progress_percent}%`,background:progressColor(r.progress_percent)}}></div>
                        </div>
                        <span style={{fontSize:'0.75rem',color:'#64748b'}}>{r.hours_rendered}/{r.target_hours} hrs ({r.progress_percent}%)</span>
                      </td>
                      <td>
                        <span className={`badge-status ${r.docs_status === 'complete' ? 'badge-active' : 'badge-pending'}`}>{r.docs_label}</span>
                      </td>
                      <td style={{fontSize:'0.82rem'}}>
                        {r.last_journal_date ? (
                          <><span className={`badge-status ${r.journal_status === 'approved' ? 'badge-active' : 'badge-pending'}`}>{r.journal_status}</span><br/><span style={{fontSize:'0.75rem',color:'#64748b'}}>{r.last_journal_date}</span></>
                        ) : <span className="text-muted">No entries</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default CoordMonitoring

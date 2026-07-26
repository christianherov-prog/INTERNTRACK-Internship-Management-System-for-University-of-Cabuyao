import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import QuickActionsPanel from '../../components/QuickActionsPanel'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import ReportExportModal from '../../components/modals/ReportExportModal'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

const COORD_QUICK_ACTIONS = [
  {
    to: '/coordinator/records',
    title: 'Student Records & Placements',
    description: 'Place students and manage internship records',
    icon: 'fa-folder-open',
    tone: 'blue',
  },
  {
    to: '/coordinator/announcements',
    title: 'Announcements',
    description: 'Post updates for students and staff',
    icon: 'fa-bullhorn',
    tone: 'amber',
  },
  {
    to: '/coordinator/absorption',
    title: 'Absorption',
    description: 'Review hire declarations and outcomes',
    icon: 'fa-user-check',
    tone: 'green',
  },
  {
    to: '/coordinator/reports',
    title: 'Reports',
    description: 'Generate monitoring and compliance reports',
    icon: 'fa-chart-bar',
    tone: 'teal',
  },
  {
    to: '/coordinator/evaluations',
    title: 'Evaluations',
    description: 'Review evaluation and feedback submissions',
    icon: 'fa-star',
    tone: 'gray',
  },
]

function CoordMonitoring() {
  const currentTerm = useCurrentTerm()
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)
  const [search, setSearch]   = useState('')
  const [exportPreview, setExportPreview] = useState(null)
  const [sectionFilter, setSectionFilter] = useState('all')
  const [sexFilter, setSexFilter] = useState('all')

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
  const allRows = unwrapList(data).items || []
  const sections = ['all', ...new Set(allRows.map(r => r.section).filter(s => s && s !== '—'))]

  const rows = allRows.filter(r => {
    const matchSearch = !search || r.student_name?.toLowerCase().includes(search.toLowerCase()) || r.program?.toLowerCase().includes(search.toLowerCase()) || r.faculty_name?.toLowerCase().includes(search.toLowerCase())
    const matchSection = sectionFilter === 'all' || r.section === sectionFilter
    const matchSex = sexFilter === 'all' || r.sex?.toLowerCase() === sexFilter.toLowerCase()
    return matchSearch && matchSection && matchSex
  })

  // Interns needing coordinator attention — derived from the same live monitoring data.
  // "At risk" mirrors the backend stat definition (live internship below 30% hours).
  const LIVE_STATUSES = ['ongoing', 'active', 'placed', 'for_evaluation']
  const attentionRows = allRows
    .map(r => {
      const atRisk = LIVE_STATUSES.includes(r.status) && r.target_hours > 0 && r.progress_percent < 30
      const reasons = []
      if (atRisk) reasons.push('Low progress')
      if (r.docs_status === 'missing' && r.docs_label) reasons.push(r.docs_label + ' docs')
      if (!r.last_journal_date) reasons.push('No journal yet')
      else if (r.journal_status && r.journal_status !== 'approved') reasons.push('Journal ' + r.journal_status)
      return { ...r, atRisk, reasons }
    })
    .filter(r => r.reasons.length > 0)
    .sort((a, b) => (Number(b.atRisk) - Number(a.atRisk)) || (a.progress_percent - b.progress_percent))
    .slice(0, 6)

  const progressColor = (pct) => {
    if (pct >= 75) return '#14b8a6'
    if (pct >= 40) return '#f59e0b'
    return '#ef4444'
  }

  const handleExportCsv = () => {
    setExportPreview({
      title: 'Intern Monitoring Report',
      filename: 'intern-monitoring',
      rows: rows.map(r => ({
        Student: r.student_name, Section: r.section, Faculty: r.faculty_name, Sex: r.sex, Program: r.program, Supervisor: r.supervisor_name, Company: r.company,
        'Hours Rendered': r.hours_rendered, 'Target Hours': r.target_hours, 'Progress %': r.progress_percent,
        Documents: r.docs_label, 'Last Journal': r.last_journal_date ?? '—', 'Journal Status': r.journal_status,
      })),
    })
  }

  return (
    <Layout title="Intern Monitoring" subtitle={currentTerm} icon="fa-eye" bodyClass="coordinator-page">
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

      {!error && (
        <div className="row g-3 mb-4">
          <div className="col-lg-6">
            <QuickActionsPanel actions={COORD_QUICK_ACTIONS} />
          </div>
          <div className="col-lg-6">
            <div className="content-card h-100">
              <div className="content-card-header">
                <i className="fa fa-triangle-exclamation"></i>
                <h6>Interns Needing Attention</h6>
              </div>
              <div className="p-0">
                {loading ? (
                  <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                ) : attentionRows.length === 0 ? (
                  <div className="text-center text-muted py-5">
                    <i className="fa fa-circle-check fa-2x mb-2 d-block text-success"></i>
                    <p className="mb-0">All interns are on track.</p>
                  </div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {attentionRows.map(r => (
                      <li key={r.user_id} className="list-group-item d-flex align-items-start gap-3 px-4 py-3">
                        <div
                          className={`rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white ${r.atRisk ? 'bg-danger' : 'bg-warning'}`}
                          style={{ width: 34, height: 34, fontSize: '0.85rem' }}
                        >
                          <i className={`fa ${r.atRisk ? 'fa-triangle-exclamation' : 'fa-clock'}`}></i>
                        </div>
                        <div className="flex-grow-1 min-w-0">
                          <div className="fw-semibold" style={{ fontSize: '0.88rem' }}>{r.student_name}</div>
                          <div className="text-muted" style={{ fontSize: '0.76rem' }}>
                            {r.program} · {r.progress_percent}% hrs
                          </div>
                          <div className="mt-1 d-flex flex-wrap gap-1">
                            {r.reasons.map((reason, i) => (
                              <span
                                key={i}
                                className={`badge-status ${reason === 'Low progress' ? 'badge-overdue' : 'badge-pending'}`}
                                style={{ fontSize: '0.66rem' }}
                              >
                                {reason}
                              </span>
                            ))}
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Table */}
      <div className="content-card">
        <div className="content-card-header flex-wrap">
          <i className="fa fa-table"></i>
          <h6>Intern Overview</h6>
          <div className="ms-auto d-flex gap-2 flex-wrap">
            <input className="form-control form-control-sm" style={{width:'180px'}} placeholder="Search student/faculty…" value={search} onChange={e => setSearch(e.target.value)} />
            <select className="form-select form-select-sm" style={{width:'140px'}} value={sectionFilter} onChange={e => setSectionFilter(e.target.value)}>
              {sections.map(s => <option key={s} value={s}>{s === 'all' ? 'All Sections' : s}</option>)}
            </select>
            <select className="form-select form-select-sm" style={{width:'120px'}} value={sexFilter} onChange={e => setSexFilter(e.target.value)}>
              <option value="all">All Sexes</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
            <button className="btn btn-sm btn-outline-success" onClick={handleExportCsv} disabled={rows.length === 0}>
              <i className="fa fa-file-csv me-1"></i>Export CSV
            </button>
          </div>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : rows.length === 0 ? (
            <EmptyState icon="fa-eye" title="No interns found" message="Active and pending-placement internships will appear here." />
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr><th>Student</th><th>Section / Faculty</th><th>Sex</th><th>Program</th><th>Supervisor</th><th>Company</th><th>Progress</th><th>Documents</th><th>Last Journal</th><th>Risk</th></tr>
                </thead>
                <tbody>
                  {rows.map(r => (
                    <tr key={r.user_id} className={r.is_at_risk ? 'table-warning' : undefined}>
                      <td className="fw-semibold">{r.student_name}</td>
                      <td style={{fontSize:'0.82rem'}}><span className="fw-semibold">{r.section}</span><br/><span style={{fontSize:'0.75rem',color:'#64748b'}}>{r.faculty_name}</span></td>
                      <td style={{fontSize:'0.82rem'}}>{r.sex}</td>
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
                      <td style={{fontSize:'0.78rem', maxWidth: 160}}>
                        {r.is_at_risk ? (
                          <span className="badge-at-risk" title={(r.at_risk_labels || []).join(', ')}>
                            At risk
                            {(r.at_risk_labels || []).length > 0 && (
                              <span className="d-block text-muted fw-normal mt-1">{(r.at_risk_labels || []).join(' · ')}</span>
                            )}
                          </span>
                        ) : (
                          <span className="text-muted">—</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
      <ReportExportModal preview={exportPreview} onClose={() => setExportPreview(null)} />
    </Layout>
  )
}

export default CoordMonitoring

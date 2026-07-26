import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import EmptyState from '../../components/EmptyState'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import QuickActionsPanel from '../../components/QuickActionsPanel'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'

const DIRECTOR_QUICK_ACTIONS = [
  {
    to: '/director/reports',
    title: 'Reports & Analytics',
    description: 'Generate placement and performance reports',
    icon: 'fa-chart-bar',
    tone: 'blue',
  },
  {
    to: '/director/companies',
    title: 'Company Partnerships',
    description: 'Manage partner companies and contacts',
    icon: 'fa-building',
    tone: 'teal',
  },
  {
    to: '/director/moa-monitoring',
    title: 'MOA Monitoring',
    description: 'Track MOA status and renewals',
    icon: 'fa-file-signature',
    tone: 'amber',
  },
  {
    to: '/director/internships',
    title: 'Student Roster & Placements',
    description: 'View and manage internship placements',
    icon: 'fa-users',
    tone: 'green',
  },
  {
    to: '/director/absorption',
    title: 'Absorption Overview',
    description: 'Finalize hire / absorption outcomes',
    icon: 'fa-user-check',
    tone: 'gray',
  },
]

const MOA_COLORS = {
  active:      { bg: '#dcfce7', color: '#166534', label: 'Active' },
  pending:     { bg: '#fef9c3', color: '#854d0e', label: 'Pending' },
  expired:     { bg: '#fee2e2', color: '#991b1b', label: 'Expired' },
  for_renewal: { bg: '#e0f2fe', color: '#075985', label: 'For Renewal' },
  'on-process':{ bg: '#f3e8ff', color: '#6b21a8', label: 'On-Process' },
}

function ProgramBar({ programs, total }) {
  if (!programs?.length) return <EmptyState icon="fa-graduation-cap" title="No program data" message="Intern counts by program will appear once internships are active." />
  return (
    <div className="table-responsive">
      <table className="table table-hover mb-0" style={{ fontSize: '0.85rem' }}>
        <thead className="table-light"><tr><th>Program</th><th>Active</th><th>Completed</th><th>Distribution</th></tr></thead>
        <tbody>
          {programs.map((p, i) => (
            <tr key={i}>
              <td className="fw-semibold">{p.program ?? 'Unknown'}</td>
              <td><span className="badge bg-success">{p.ongoing ?? p.count ?? 0}</span></td>
              <td><span className="badge bg-primary">{p.completed ?? 0}</span></td>
              <td style={{ minWidth: '140px' }}>
                <div className="progress" style={{ height: '8px' }}>
                  <div
                    className="progress-bar"
                    style={{
                      width: `${Math.min(100, ((p.ongoing ?? p.count ?? 0) / Math.max(total, 1)) * 100)}%`,
                      background: 'linear-gradient(90deg, #6366f1, #14b8a6)',
                    }}
                  ></div>
                </div>
                <small className="text-muted">{Math.round(((p.ongoing ?? p.count ?? 0) / Math.max(total, 1)) * 100)}%</small>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function MoaDonut({ moaByStatus }) {
  const entries = Object.entries(moaByStatus ?? {})
  if (entries.length === 0) return <p className="text-muted">No MOA data.</p>
  return (
    <div className="d-flex flex-wrap gap-2">
      {entries.map(([status, count]) => {
        const cfg = MOA_COLORS[status] ?? { bg: '#f1f5f9', color: '#475569', label: status }
        return (
          <div key={status} className="text-center px-3 py-2 rounded" style={{ background: cfg.bg, minWidth: '90px' }}>
            <div style={{ fontSize: '1.6rem', fontWeight: 700, color: cfg.color }}>{count}</div>
            <div style={{ fontSize: '0.75rem', color: cfg.color }}>{cfg.label}</div>
          </div>
        )
      })}
    </div>
  )
}

function CompetencyBars({ evalBreakdown }) {
  if (!evalBreakdown) return <p className="text-muted">No evaluation data.</p>
  const fields = [
    { key: 'avg_technical',       label: 'Technical Skills' },
    { key: 'avg_communication',   label: 'Communication' },
    { key: 'avg_teamwork',        label: 'Teamwork' },
    { key: 'avg_initiative',      label: 'Initiative' },
    { key: 'avg_work_ethics',     label: 'Work Ethics' },
    { key: 'avg_attendance',      label: 'Attendance' },
    { key: 'avg_overall',         label: 'Overall Average' },
  ]
  return (
    <div>
      {fields.map(f => {
        const val = parseFloat(evalBreakdown[f.key] ?? 0)
        const pct = (val / 5) * 100
        return (
          <div key={f.key} className="mb-2">
            <div className="d-flex justify-content-between mb-1" style={{ fontSize: '0.82rem' }}>
              <span>{f.label}</span><span className="fw-semibold">{val.toFixed(2)}/5.00</span>
            </div>
            <div className="progress" style={{ height: '8px' }}>
              <div
                className="progress-bar"
                style={{ width: `${pct}%`, background: pct >= 80 ? '#16a34a' : pct >= 60 ? '#d97706' : '#dc2626' }}
              ></div>
            </div>
          </div>
        )
      })}
    </div>
  )
}

function DirectorDashboard() {
  const currentTerm = useCurrentTerm()
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/director/dashboard')
      .then(res => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load dashboard analytics.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const s           = data?.stats         ?? {}
  const byProgram   = data?.by_program    ?? []
  const moaByStatus = data?.moa_by_status ?? {}
  const topCompanies= data?.top_companies ?? []
  const evalBreak   = data?.eval_breakdown ?? null

  return (
    <Layout title="Dashboard" subtitle={currentTerm} icon="fa-chart-pie" bodyClass="director-page">
      <RoleSummaryPanel />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row g-3 mb-4">
            <div className="col-lg-6">
              <QuickActionsPanel actions={DIRECTOR_QUICK_ACTIONS} />
            </div>

            {/* Evaluation Competency Averages — promoted next to Quick Actions to fill the row */}
            <div className="col-lg-6">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-star"></i>
                  <h6>Average Evaluation Scores</h6>
                </div>
                <div className="p-3">
                  <CompetencyBars evalBreakdown={evalBreak} />
                </div>
              </div>
            </div>
          </div>

          <div className="row g-3 mb-4">
            {/* Interns by Program */}
            <div className="col-lg-7">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-graduation-cap"></i>
                  <h6>Interns by Program</h6>
                </div>
                <div className="p-3">
                  <ProgramBar programs={byProgram} total={s.active_interns ?? 1} />
                </div>
              </div>
            </div>

            {/* MOA Status Summary */}
            <div className="col-lg-5">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-file-contract"></i>
                  <h6>MOA Status Summary</h6>
                </div>
                <div className="p-3">
                  <MoaDonut moaByStatus={moaByStatus} />
                </div>
              </div>
            </div>
          </div>

          <div className="row g-3 mb-4">
            {/* Top Companies by Interns */}
            <div className="col-12">
              <div className="content-card h-100">
                <div className="content-card-header">
                  <i className="fa fa-building"></i>
                  <h6>Top Partner Companies</h6>
                </div>
                <div className="table-card">
                  {topCompanies.length === 0 ? (
                    <p className="text-muted text-center py-3">No company data.</p>
                  ) : topCompanies.map((c, i) => {
                    const cfg = MOA_COLORS[c.moa_status] ?? { bg: '#f1f5f9', color: '#475569', label: c.moa_status }
                    return (
                      <div key={c.id} className="p-3 border-bottom d-flex align-items-center justify-content-between">
                        <div>
                          <div className="fw-semibold">{c.company_name}</div>
                          <div className="text-muted" style={{ fontSize: '0.78rem' }}>{c.industry ?? '—'}</div>
                        </div>
                        <div className="text-end">
                          <div className="fw-semibold">{c.internships_count} interns</div>
                          <span className="badge" style={{ background: cfg.bg, color: cfg.color }}>{cfg.label}</span>
                        </div>
                      </div>
                    )
                  })}
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default DirectorDashboard

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import QuickActionsPanel from '../../components/QuickActionsPanel'
import api from '../../services/api'

const ROLE_META = [
  { key: 'student', label: 'Students', to: '/admin/users?role=student', color: '#0f766e' },
  { key: 'faculty', label: 'Faculty', to: '/admin/users?role=faculty', color: '#1d4ed8' },
  { key: 'director', label: 'Directors', to: '/admin/directors', color: '#b45309' },
  { key: 'coordinator', label: 'Coordinators', to: '/admin/coordinators', color: '#7c3aed' },
  { key: 'supervisor', label: 'Supervisors', to: '/admin/users?role=supervisor', color: '#be123c' },
]

const ADMIN_QUICK_ACTIONS = [
  {
    to: '/admin/directors',
    title: 'Assign Directors',
    description: 'Manage PALD Director staff assignments',
    icon: 'fa-user-tie',
    tone: 'amber',
  },
  {
    to: '/admin/coordinators',
    title: 'Manage Coordinators',
    description: 'Assign and update practicum coordinators',
    icon: 'fa-user-check',
    tone: 'green',
  },
  {
    to: '/admin/section-mappings',
    title: 'Section Mappings',
    description: 'Map sections to faculty supervisors',
    icon: 'fa-sitemap',
    tone: 'blue',
  },
  {
    to: '/admin/users',
    title: 'All Users',
    description: 'Browse and filter accounts by role',
    icon: 'fa-users',
    tone: 'gray',
  },
  {
    to: '/admin/sync',
    title: 'MISD Sync Monitor',
    description: 'Check iEnroll sync status and pull records',
    icon: 'fa-sync',
    tone: 'teal',
  },
]

function MisdDashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/admin/dashboard')
      .then((res) => setData(res.data))
      .catch((err) => setError(err.response?.data?.message || 'Failed to load MISD dashboard.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const counts = data?.users_by_role || {}
  const status = data?.misd_status

  const unmappedFooter = (data?.unmapped_sections?.length > 0) ? (
    <div className="px-3 pb-3">
      <div className="alert alert-warning mb-0">
        <strong>{data.unmapped_count}</strong> section group(s) have students without a faculty mapping.
        <Link to="/admin/section-mappings" className="ms-2">Fix mappings</Link>
      </div>
    </div>
  ) : null

  return (
    <Layout title="MISD Dashboard" subtitle="Enrollment & Staff Administration" icon="fa-server" bodyClass="admin-page">
      <RoleSummaryPanel showMetrics={false} />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : data && (
        <>
          <div className="row g-3 mb-4">
            {ROLE_META.map((r) => (
              <div className="col-12 col-sm-6 col-md-4 col-xl" key={r.key}>
                <Link to={r.to} className="text-decoration-none">
                  <div className="content-card h-100 p-3" style={{ borderTop: `3px solid ${r.color}` }}>
                    <div className="text-muted" style={{ fontSize: '0.8rem' }}>{r.label}</div>
                    <div style={{ fontSize: '1.8rem', fontWeight: 700, color: r.color }}>
                      {counts[r.key]?.active ?? 0}
                    </div>
                    <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                      {counts[r.key]?.total ?? 0} total
                    </div>
                  </div>
                </Link>
              </div>
            ))}
            <div className="col-12 col-sm-6 col-md-4 col-xl">
              <Link to="/admin/section-mappings" className="text-decoration-none">
                <div className="content-card h-100 p-3" style={{ borderTop: '3px solid #dc2626' }}>
                  <div className="text-muted" style={{ fontSize: '0.8rem' }}>Unmapped Sections</div>
                  <div style={{ fontSize: '1.8rem', fontWeight: 700, color: '#dc2626' }}>
                    {data.unmapped_count ?? 0}
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.75rem' }}>need faculty mapping</div>
                </div>
              </Link>
            </div>
          </div>

          <div className="row g-3 mb-4">
            <div className="col-lg-5">
              <div className="content-card h-100">
                <div className="content-card-header"><i className="fa fa-plug"></i><h6>MISD Integration</h6></div>
                <div className="p-3">
                  <div className="d-flex justify-content-between mb-2">
                    <span>Mode</span>
                    <span className={`badge ${status?.use_mock ? 'bg-warning text-dark' : 'bg-success'}`}>
                      {status?.use_mock ? 'Mock iEnroll' : 'Live API'}
                    </span>
                  </div>
                  <div className="d-flex justify-content-between mb-2">
                    <span>Reachable</span>
                    <span className={`badge ${status?.reachable ? 'bg-success' : 'bg-danger'}`}>
                      {status?.reachable ? 'Yes' : 'No'}
                    </span>
                  </div>
                  <div className="d-flex justify-content-between mb-2">
                    <span>Latency</span>
                    <span>{status?.latency_ms != null ? `${status.latency_ms} ms` : '—'}</span>
                  </div>
                  <div className="d-flex justify-content-between mb-2">
                    <span>Cache TTL</span>
                    <span>{status?.cache_ttl ?? '—'} s</span>
                  </div>
                  <div className="text-muted mt-2" style={{ fontSize: '0.78rem', wordBreak: 'break-all' }}>
                    {status?.base_url}
                  </div>
                  {status?.error && <div className="alert alert-warning mt-3 mb-0 py-2">{status.error}</div>}
                  <Link to="/admin/sync" className="btn btn-outline-green btn-sm mt-3">
                    Open Sync Monitor
                  </Link>
                </div>
              </div>
            </div>

            <div className="col-lg-7">
              <QuickActionsPanel actions={ADMIN_QUICK_ACTIONS} footer={unmappedFooter} />
            </div>
          </div>

          <div className="content-card">
            <div className="content-card-header"><i className="fa fa-history"></i><h6>Recent Admin Activity</h6></div>
            <div className="table-responsive">
              <table className="table table-hover mb-0" style={{ fontSize: '0.85rem' }}>
                <thead className="table-light">
                  <tr><th>When</th><th>Action</th><th>Actor</th><th>Details</th></tr>
                </thead>
                <tbody>
                  {(data.recent_activity || []).length === 0 ? (
                    <tr><td colSpan={4} className="text-center text-muted py-4">No admin activity yet.</td></tr>
                  ) : data.recent_activity.map((row) => (
                    <tr key={row.id}>
                      <td>{row.created_at ? new Date(row.created_at).toLocaleString() : '—'}</td>
                      <td><code>{row.action}</code></td>
                      <td>{row.actor?.username || '—'}</td>
                      <td className="text-muted">{row.new_values ? JSON.stringify(row.new_values) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default MisdDashboard

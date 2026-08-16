import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import api from '../../services/api'

const ROLE_META = [
  { key: 'student', label: 'Students', to: '/admin/users?role=student', icon: 'fa-user-graduate', color: '#157938', bg: '#ecfdf5' },
  { key: 'faculty', label: 'Faculty Supervisors', to: '/admin/users?role=faculty', icon: 'fa-chalkboard-user', color: '#0284c7', bg: '#f0f9ff' },
  { key: 'director', label: 'PALD Directors', to: '/admin/directors', icon: 'fa-user-tie', color: '#d97706', bg: '#fffbeb' },
  { key: 'coordinator', label: 'Coordinators', to: '/admin/coordinators', icon: 'fa-user-check', color: '#7c3aed', bg: '#f5f3ff' },
  { key: 'supervisor', label: 'Industry Supervisors', to: '/admin/users?role=supervisor', icon: 'fa-building-user', color: '#0d9488', bg: '#f0fdfa' },
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

  return (
    <Layout title="MISD Dashboard" subtitle="Enrollment & Staff Administration" icon="fa-server" bodyClass="admin-page">
      <RoleSummaryPanel showMetrics={false} />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : data && (
        <>
          {/* Top Stat Cards */}
          <div className="row g-3 mb-4">
            {ROLE_META.map((r) => (
              <div className="col-6 col-md-4 col-xl" key={r.key}>
                <Link to={r.to} className="misd-stat-card-link text-decoration-none d-block h-100">
                  <div
                    className="card misd-stat-card border-0 shadow-sm rounded-4 p-3 h-100 bg-white"
                    style={{ '--card-accent': r.color }}
                  >
                    <div className="d-flex align-items-center justify-content-between mb-2">
                      <span className="text-muted fw-semibold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                        {r.label}
                      </span>
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center"
                        style={{ width: '32px', height: '32px', background: r.bg, color: r.color }}
                      >
                        <i className={`fa ${r.icon}`} style={{ fontSize: '0.88rem' }}></i>
                      </div>
                    </div>
                    <div className="fw-bolder text-dark mb-1" style={{ fontSize: '1.6rem', lineHeight: 1.1 }}>
                      {counts[r.key]?.active ?? 0}
                    </div>
                    <div className="text-muted fw-medium" style={{ fontSize: '0.75rem' }}>
                      <span className="text-success fw-bold">{counts[r.key]?.active ?? 0} Active</span> · {counts[r.key]?.total ?? 0} total
                    </div>
                  </div>
                </Link>
              </div>
            ))}

            {/* Unmapped Sections Stat Card */}
            <div className="col-6 col-md-4 col-xl">
              <Link to="/admin/section-mappings" className="misd-stat-card-link text-decoration-none d-block h-100">
                <div
                  className="card misd-stat-card border-0 shadow-sm rounded-4 p-3 h-100 bg-white"
                  style={{ '--card-accent': (data.unmapped_count ?? 0) > 0 ? '#dc2626' : '#157938' }}
                >
                  <div className="d-flex align-items-center justify-content-between mb-2">
                    <span className="text-muted fw-semibold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                      Unmapped Sections
                    </span>
                    <div
                      className="rounded-circle d-flex align-items-center justify-content-center"
                      style={{ width: '32px', height: '32px', background: (data.unmapped_count ?? 0) > 0 ? '#fee2e2' : '#ecfdf5', color: (data.unmapped_count ?? 0) > 0 ? '#dc2626' : '#157938' }}
                    >
                      <i className="fa fa-sitemap" style={{ fontSize: '0.88rem' }}></i>
                    </div>
                  </div>
                  <div className="fw-bolder text-dark mb-1" style={{ fontSize: '1.6rem', lineHeight: 1.1, color: (data.unmapped_count ?? 0) > 0 ? '#dc2626' : '#0f172a' }}>
                    {data.unmapped_count ?? 0}
                  </div>
                  <div className="text-muted fw-medium" style={{ fontSize: '0.75rem' }}>
                    {(data.unmapped_count ?? 0) > 0 ? 'Need faculty mapping' : 'All sections mapped'}
                  </div>
                </div>
              </Link>
            </div>
          </div>

          <div className="row g-4 gx-lg-5 mb-4">
            {/* MISD Integration Status */}
            <div className="col-lg-5">
              <div className="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex align-items-center justify-content-between">
                  <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                    <i className="fa fa-plug text-success"></i> MISD Integration Engine
                  </h6>
                  <span className="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold px-2.5 py-1" style={{ fontSize: '0.72rem' }}>
                    Mock Engine
                  </span>
                </div>
                <div className="card-body px-4 pt-2 pb-4 d-flex flex-column justify-content-between">
                  <div className="d-flex flex-column">
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Operating Mode</span>
                      <span className="badge bg-warning-subtle text-warning-emphasis fw-bold px-2.5 py-1 rounded-pill" style={{ fontSize: '0.75rem' }}>
                        {status?.use_mock !== false ? 'Mock iEnroll Engine' : 'Live University API'}
                      </span>
                    </div>
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Connector Reachability</span>
                      <span className={`badge ${status?.reachable !== false ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} fw-bold px-2.5 py-1 rounded-pill`} style={{ fontSize: '0.75rem' }}>
                        {status?.reachable !== false ? 'Reachable (OK)' : 'Unreachable'}
                      </span>
                    </div>
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Latency</span>
                      <span className="text-dark fw-bold" style={{ fontSize: '0.88rem' }}>
                        {status?.latency_ms != null ? `${status.latency_ms} ms` : '0 ms'}
                      </span>
                    </div>
                    <div className="d-flex justify-content-between align-items-center py-3 border-bottom" style={{ borderColor: '#f1f5f9' }}>
                      <span className="text-muted fw-medium" style={{ fontSize: '0.85rem' }}>Cache TTL</span>
                      <span className="text-dark fw-bold" style={{ fontSize: '0.88rem' }}>{status?.cache_ttl ?? 3600}s</span>
                    </div>
                    <div className="text-muted mt-4 px-3 py-2 rounded-3 bg-light" style={{ fontSize: '0.78rem', wordBreak: 'break-all' }}>
                      <i className="fa-solid fa-link me-1.5 text-secondary"></i> {status?.base_url || 'in-process://MockMisdRepository'}
                    </div>
                  </div>
                  {status?.error && (
                    <div className="alert alert-warning mt-3 mb-0 py-2 rounded-3 border-0" style={{ fontSize: '0.82rem' }}>
                      <i className="fa fa-triangle-exclamation me-1"></i> {status.error}
                    </div>
                  )}
                  <Link to="/admin/sync" className="btn btn-outline-success btn-sm mt-4 w-100 rounded-3 fw-semibold py-2">
                    <i className="fa fa-rotate me-1.5"></i> Open Sync Monitor & Tools
                  </Link>
                </div>
              </div>
            </div>

            {/* Quick Actions */}
            <div className="col-lg-7">
              <div className="card border-0 shadow-sm rounded-4 bg-white">
                <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                  <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                    <i className="fa fa-bolt text-success"></i> Administrative Quick Actions
                  </h6>
                </div>
                <div className="card-body px-4 pt-3 pb-4 d-flex flex-column justify-content-between">
                  <div className="d-flex flex-wrap mb-3" style={{ gap: '14px 14px' }}>
                    <Link to="/admin/directors" className="btn btn-success rounded-3 fw-semibold px-3.5 py-2">
                      <i className="fa fa-user-tie me-2"></i>Assign Director
                    </Link>
                    <Link to="/admin/coordinators" className="btn btn-outline-success rounded-3 fw-semibold px-3.5 py-2">
                      <i className="fa fa-user-check me-2"></i>Manage Coordinators
                    </Link>
                    <Link to="/admin/section-mappings" className="btn btn-outline-success rounded-3 fw-semibold px-3.5 py-2">
                      <i className="fa fa-sitemap me-2"></i>Section Mappings
                    </Link>
                    <Link to="/admin/users" className="btn btn-outline-secondary rounded-3 fw-semibold px-3.5 py-2">
                      <i className="fa fa-users me-2"></i>All User Accounts
                    </Link>
                    <Link to="/admin/sync" className="btn btn-outline-secondary rounded-3 fw-semibold px-3.5 py-2">
                      <i className="fa fa-arrows-rotate me-2"></i>Sync Student
                    </Link>
                  </div>
                  {(data.unmapped_sections?.length > 0) && (
                    <div className="alert alert-warning border-0 rounded-3 mb-0 d-flex align-items-center justify-content-between">
                      <div className="d-flex align-items-center gap-2">
                        <i className="fa fa-triangle-exclamation text-warning flex-shrink-0" style={{ fontSize: '1.2rem' }}></i>
                        <span style={{ fontSize: '0.85rem' }}>
                          <strong>{data.unmapped_count}</strong> section group(s) have students without assigned faculty supervisors.
                        </span>
                      </div>
                      <Link to="/admin/section-mappings" className="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3 flex-shrink-0">
                        Fix Mappings
                      </Link>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Recent Admin Activity Table */}
          <div className="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex align-items-center justify-content-between">
              <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
                <i className="fa fa-clock-rotate-left text-success"></i> Recent Administrative Activity
              </h6>
            </div>
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0" style={{ fontSize: '0.86rem' }}>
                <thead style={{ background: '#f8fafc', borderBottom: '1px solid #eef2f6' }}>
                  <tr>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-4" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Timestamp</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Action Type</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Actor</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-4" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Details</th>
                  </tr>
                </thead>
                <tbody>
                  {(data.recent_activity || []).length === 0 ? (
                    <tr>
                      <td colSpan={4} className="text-center text-muted py-5">
                        <i className="fa-regular fa-folder-open fa-2x mb-2 d-block opacity-40"></i>
                        No administrative activity recorded yet.
                      </td>
                    </tr>
                  ) : data.recent_activity.map((row) => (
                    <tr key={row.id}>
                      <td className="px-4 py-3 text-muted">{row.created_at ? new Date(row.created_at).toLocaleString() : '—'}</td>
                      <td className="px-3 py-3">
                        <span className="badge bg-light text-dark border fw-semibold font-monospace" style={{ fontSize: '0.75rem' }}>
                          {row.action}
                        </span>
                      </td>
                      <td className="px-3 py-3 fw-semibold text-dark">{row.actor?.username || '—'}</td>
                      <td className="px-4 py-3 text-muted" style={{ maxWidth: '380px' }}>
                        <span className="text-truncate d-block" title={row.new_values ? JSON.stringify(row.new_values) : ''}>
                          {row.new_values ? JSON.stringify(row.new_values) : '—'}
                        </span>
                      </td>
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

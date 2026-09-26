import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCachedPage } from '../../hooks/useCachedPage'
import { prefetchPage } from '../../utils/pageCache'
import { formatManilaDateTime } from '../../utils/manilaTime'
import InternTrackLoader from '../../components/InternTrackLoader'

const ROLE_METRICS = [
  { key: 'student', label: 'Students', icon: 'fa-user-graduate', to: '/admin/users?role=student' },
  { key: 'faculty', label: 'Faculty', icon: 'fa-chalkboard-user', to: '/admin/users?role=faculty' },
  { key: 'director', label: 'Directors', icon: 'fa-user-tie', to: '/admin/directors' },
  { key: 'coordinator', label: 'Coordinators', icon: 'fa-user-check', to: '/admin/coordinators' },
  { key: 'supervisor', label: 'Supervisors', icon: 'fa-briefcase', to: '/admin/users?role=supervisor' },
]

const QUICK_ACTIONS = [
  { to: '/admin/directors', icon: 'fa-user-tie', label: 'Assign Director', hint: 'Set the PALD Director' },
  { to: '/admin/coordinators', icon: 'fa-user-check', label: 'Manage Coordinators', hint: 'Assign college coordinators' },
  { to: '/admin/section-mappings', icon: 'fa-sitemap', label: 'Section Mappings', hint: 'Map sections to faculty' },
  { to: '/admin/users', icon: 'fa-users', label: 'All Users', hint: 'Accounts and access' },
  { to: '/admin/sync', icon: 'fa-arrows-rotate', label: 'Sync Students', hint: 'Refresh enrollment profiles' },
]

/** "validate_attendance" / "staff.activated" → "Validate Attendance" / "Staff Activated" */
function humanizeAction(action) {
  return String(action || '—')
    .replace(/[._]+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatCacheDuration(seconds) {
  const s = Number(seconds)
  if (!Number.isFinite(s) || s <= 0) return '—'
  if (s % 3600 === 0) return `${s / 3600} ${s === 3600 ? 'hour' : 'hours'}`
  if (s % 60 === 0) return `${s / 60} min`
  return `${s} s`
}

function MetricCard({ label, icon, to, active, total, tone = 'green' }) {
  const inactive = Math.max(0, total - active)
  return (
    <Link to={to} className="misd-metric text-decoration-none" data-tone={tone}>
      <div className="misd-metric__head">
        <span className="misd-metric__icon" aria-hidden="true"><i className={`fa ${icon}`}></i></span>
        <span className="misd-metric__label">{label}</span>
      </div>
      <div className="misd-metric__value">{active}</div>
      <div className="misd-metric__caption">active {active === 1 ? 'account' : 'accounts'}</div>
      <div className="misd-metric__meta">
        {total} total{inactive > 0 ? ` · ${inactive} inactive` : ''}
      </div>
    </Link>
  )
}

function MisdDashboard() {
  const { loading, seed, run } = useCachedPage('admin:dashboard')
  const [data, setData] = useState(seed ?? null)
  const [error, setError] = useState(null)

  const load = () => {
    setError(null)
    run(() => api.get('/admin/dashboard').then((res) => res.data))
      .then((next) => {
        if (next) {
          setData(next)
          prefetchPage('admin:users:all:all:q:', () =>
            api.get('/admin/users', { params: { page: 1, per_page: 25 } }).then((res) => {
              const { items, meta } = unwrapList(res.data)
              return { rows: items, meta }
            })
          )
        }
      })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load MISD dashboard.'))
  }

  useEffect(() => { load() }, [])

  const counts = data?.users_by_role || {}
  const status = data?.misd_status
  const unmapped = data?.unmapped_count ?? 0
  const recent = data?.recent_activity || []

  return (
    <Layout title="MISD Dashboard" subtitle="Enrollment & Staff Administration" icon="fa-server" bodyClass="admin-page misd-dashboard">
      <RoleSummaryPanel showMetrics={false} />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><InternTrackLoader /></div>
      ) : data && (
        <>
          {/* 1. Key metrics */}
          <section className="misd-section" aria-labelledby="misd-metrics-title">
            <h2 id="misd-metrics-title" className="misd-section__title">Accounts Overview</h2>
            <div className="misd-metrics">
              {ROLE_METRICS.map((m) => (
                <MetricCard
                  key={m.key}
                  label={m.label}
                  icon={m.icon}
                  to={m.to}
                  active={counts[m.key]?.active ?? 0}
                  total={counts[m.key]?.total ?? 0}
                />
              ))}
              <Link to="/admin/section-mappings" className="misd-metric text-decoration-none" data-tone={unmapped > 0 ? 'danger' : 'green'}>
                <div className="misd-metric__head">
                  <span className="misd-metric__icon" aria-hidden="true"><i className="fa fa-sitemap"></i></span>
                  <span className="misd-metric__label">Unmapped Sections</span>
                </div>
                <div className="misd-metric__value">{unmapped}</div>
                <div className="misd-metric__caption">{unmapped === 1 ? 'section group' : 'section groups'}</div>
                <div className="misd-metric__meta">{unmapped > 0 ? 'need a faculty mapping' : 'All sections mapped'}</div>
              </Link>
            </div>
          </section>

          {/* 2. Quick Actions */}
          <section className="content-card misd-section" aria-labelledby="misd-actions-title">
            <div className="content-card-header">
              <i className="fa fa-bolt"></i><h6 id="misd-actions-title">Quick Actions</h6>
            </div>
            <div className="misd-actions">
              {QUICK_ACTIONS.map((a) => (
                <Link key={a.to} to={a.to} className="misd-action text-decoration-none">
                  <span className="misd-action__icon" aria-hidden="true"><i className={`fa ${a.icon}`}></i></span>
                  <span className="misd-action__text">
                    <span className="misd-action__label">{a.label}</span>
                    <span className="misd-action__hint">{a.hint}</span>
                  </span>
                </Link>
              ))}
            </div>
            {unmapped > 0 && (
              <div className="misd-actions__notice">
                <i className="fa fa-triangle-exclamation me-2" aria-hidden="true"></i>
                <strong>{unmapped}</strong>&nbsp;section group(s) have students without a faculty mapping.
                <Link to="/admin/section-mappings" className="ms-2 fw-semibold">Fix mappings</Link>
              </div>
            )}
          </section>

          {/* 3. Integration status + 4. Activity today */}
          <div className="misd-split misd-section">
            <section className="content-card" aria-labelledby="misd-status-title">
              <div className="content-card-header">
                <i className="fa fa-plug"></i><h6 id="misd-status-title">iEnroll Directory</h6>
              </div>
              <dl className="misd-status">
                <div className="misd-status__row">
                  <dt>Source</dt>
                  <dd>{status?.mode_label || '—'}</dd>
                </div>
                <div className="misd-status__row">
                  <dt>Status</dt>
                  <dd>
                    <span className={`it-status-chip it-status-chip--${status?.reachable ? 'success' : 'danger'}`}>
                      <i className={`fa ${status?.reachable ? 'fa-circle-check' : 'fa-circle-xmark'}`} aria-hidden="true"></i>
                      <span className="it-status-chip__status">{status?.reachable ? 'Available' : 'Unavailable'}</span>
                    </span>
                  </dd>
                </div>
                <div className="misd-status__row">
                  <dt>Response time</dt>
                  <dd>{status?.latency_ms != null ? `${status.latency_ms} ms` : '—'}</dd>
                </div>
                <div className="misd-status__row">
                  <dt>Profile cache</dt>
                  <dd>{formatCacheDuration(status?.cache_ttl)}</dd>
                </div>
                <div className="misd-status__row">
                  <dt>Last checked</dt>
                  <dd>{formatManilaDateTime(status?.checked_at)}</dd>
                </div>
              </dl>
              {status?.source_label && <p className="misd-status__note">{status.source_label}</p>}
              {status?.error && <div className="alert alert-warning mx-3 mb-3 py-2">{status.error}</div>}
              <div className="px-3 pb-3">
                <Link to="/admin/sync" className="btn btn-outline-success btn-sm">
                  <i className="fa fa-arrows-rotate me-1"></i>Open Sync Monitor
                </Link>
              </div>
            </section>

            <section className="content-card misd-today" aria-labelledby="misd-today-title">
              <div className="content-card-header">
                <i className="fa fa-calendar-day"></i><h6 id="misd-today-title">Activity Today</h6>
              </div>
              <div className="misd-today__body">
                <div className="misd-today__value">{data.activities_today ?? 0}</div>
                <div className="misd-today__caption">recorded system {Number(data.activities_today) === 1 ? 'event' : 'events'} today (Asia/Manila)</div>
                <Link to="/admin/audit-logs" className="btn btn-success btn-sm mt-3">
                  <i className="fa fa-clipboard-list me-1"></i>View Audit Logs
                </Link>
              </div>
            </section>
          </div>

          {/* 5. Recent activity */}
          <section className="content-card" aria-labelledby="misd-recent-title">
            <div className="content-card-header">
              <i className="fa fa-history"></i><h6 id="misd-recent-title">Recent System Activity</h6>
              <Link to="/admin/audit-logs" className="ms-auto small fw-semibold">Open Audit Logs</Link>
            </div>
            <div className="table-responsive">
              <table className="table table-hover mb-0 misd-activity-table" style={{ fontSize: '0.85rem' }}>
                <thead className="table-light">
                  <tr><th>When</th><th>Action</th><th>Actor</th><th>Summary</th></tr>
                </thead>
                <tbody>
                  {recent.length === 0 ? (
                    <tr><td colSpan={4} className="text-center text-muted py-4">No recent activity yet.</td></tr>
                  ) : recent.map((row) => (
                    <tr key={row.id}>
                      <td className="text-nowrap">{row.created_at_display || formatManilaDateTime(row.created_at)}</td>
                      <td><span className="it-status-chip it-status-chip--neutral"><span className="it-status-chip__status">{humanizeAction(row.action)}</span></span></td>
                      <td className="text-nowrap">{row.actor?.label || row.actor?.username || '—'}</td>
                      <td className="text-muted">{row.summary || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </Layout>
  )
}

export default MisdDashboard

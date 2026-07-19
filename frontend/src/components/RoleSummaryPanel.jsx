import { useEffect, useMemo, useState } from 'react'
import api from '../services/api'
import DashboardHeroBanner from './DashboardHeroBanner'

/** Role-specific metric tiles rendered under the shared hero banner. */
const ROLE_METRICS = {
  director: [
    { key: 'total_coordinators', label: 'Coordinators', icon: 'fa-user-tie', tone: 'green' },
    { key: 'total_companies', label: 'Partner Companies', icon: 'fa-building', tone: 'teal' },
    { key: 'total_active_interns', label: 'Active Interns', icon: 'fa-users', tone: 'blue' },
    { key: 'pending_approvals', label: 'Pending Approvals', icon: 'fa-clock', tone: 'amber' },
  ],
  coordinator: [
    { key: 'assigned_students_count', label: 'Assigned Students', icon: 'fa-users', tone: 'green' },
    { key: 'assigned_companies_count', label: 'Assigned Companies', icon: 'fa-building', tone: 'teal' },
    { key: 'pending_evaluations_count', label: 'Pending Reviews', icon: 'fa-book', tone: 'amber' },
    { key: 'pending_documents_count', label: 'Pending Documents', icon: 'fa-file-alt', tone: 'blue' },
  ],
  faculty: [
    { key: 'assigned_students_count', label: 'Assigned Students', icon: 'fa-users', tone: 'green' },
    { key: 'pending_evaluations_count', label: 'Pending Evaluations', icon: 'fa-star', tone: 'amber' },
    { key: 'pending_journals_count', label: 'Pending Journals', icon: 'fa-book', tone: 'teal' },
    { key: 'last_login_display', label: 'Last Login', icon: 'fa-clock', tone: 'blue' },
  ],
  supervisor: [
    { key: 'assigned_students_count', label: 'Assigned Students', icon: 'fa-users', tone: 'green' },
    { key: 'company_name', label: 'Host Company', icon: 'fa-building', tone: 'teal' },
    { key: 'pending_validations_count', label: 'Pending Validations', icon: 'fa-calendar-check', tone: 'amber' },
    { key: 'pending_evaluations_count', label: 'Pending Evaluations', icon: 'fa-star', tone: 'blue' },
  ],
}

function formatLastLogin(iso) {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  const diffMs = Date.now() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return `${diffDays}d ago`
  return date.toLocaleDateString()
}

function formatInternshipStatus(status) {
  if (!status) return 'NO ACTIVE INTERNSHIP'
  return String(status).replace(/_/g, ' ').toUpperCase()
}

/**
 * Fetches GET /dashboard/summary and renders the shared hero + role metrics.
 * Used on Director, Coordinator, Faculty, and Supervisor dashboards.
 */
function RoleSummaryPanel({ showMetrics = true }) {
  const [summary, setSummary] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true
    setLoading(true)
    setError(null)

    api.get('/dashboard/summary')
      .then((res) => {
        if (active) setSummary(res.data)
      })
      .catch((err) => {
        if (active) {
          setError(err.response?.data?.message || 'Could not load dashboard summary.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => { active = false }
  }, [])

  const view = useMemo(() => {
    if (!summary) return null

    const role = summary.role
    const lastLoginDisplay = formatLastLogin(summary.last_login_at)
    const enriched = { ...summary, last_login_display: lastLoginDisplay }

    let meta = []
    let badges = [
      { text: summary.security_status || 'Standard', variant: 'ongoing' },
      { text: summary.current_term || '—', variant: 'term' },
    ]

    if (role === 'student') {
      meta = [
        summary.section,
        summary.student_number ? `Student No. ${summary.student_number}` : null,
        summary.company_name || 'No company assigned',
      ]
      badges = [
        { text: formatInternshipStatus(summary.internship_status), variant: 'ongoing' },
        { text: summary.current_term || '—', variant: 'term' },
      ]
    } else {
      // Staff roles: role label + ID only (no last-login on the banner line).
      meta = [
        summary.role_label,
        summary.username ? `ID ${summary.username}` : null,
      ]
    }

    return {
      role,
      enriched,
      label: summary.label || 'DASHBOARD',
      title: `Welcome back, ${summary.name || 'User'}`,
      meta,
      badges,
      metrics: ROLE_METRICS[role] || [],
    }
  }, [summary])

  return (
    <div className="role-summary-panel">
      <DashboardHeroBanner
        label={view?.label}
        title={view?.title}
        meta={view?.meta}
        badges={view?.badges}
        loading={loading}
        error={error}
      />

      {showMetrics && !loading && !error && view?.metrics?.length > 0 && (
        <div className="row g-3 mb-4">
          {view.metrics.map((metric) => (
            <div
              key={metric.key}
              className={`col-sm-6 ${view.metrics.length >= 4 ? 'col-xl-3' : 'col-xl-6'}`}
            >
              <div className="stat-card">
                <div className={`stat-icon ${metric.tone}`}>
                  <i className={`fa ${metric.icon}`} aria-hidden="true" />
                </div>
                <div>
                  <div className="stat-value">{view.enriched[metric.key] ?? 0}</div>
                  <div className="stat-label">{metric.label}</div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default RoleSummaryPanel

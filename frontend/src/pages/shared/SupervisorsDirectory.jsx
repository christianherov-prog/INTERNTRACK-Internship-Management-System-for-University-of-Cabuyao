import { useEffect, useMemo, useState, Fragment } from 'react'
import Layout from '../../components/Layout'
import EmptyState from '../../components/EmptyState'
import PageError from '../../components/PageError'
import InternTrackLoader from '../../components/InternTrackLoader'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useCachedPage } from '../../hooks/useCachedPage'

function approvalBadge(supervisor) {
  const status = supervisor.approval_status
  if (!supervisor.is_active) {
    return <span className="badge-status badge-inactive">Inactive</span>
  }
  if (status === 'approved') {
    return <span className="badge-status badge-active">Approved</span>
  }
  if (status === 'rejected') {
    return <span className="badge-status badge-inactive">Rejected</span>
  }
  if (status === 'registered' || status === 'pending') {
    return <span className="badge-status badge-pending">Pending Approval</span>
  }
  if (supervisor.is_active) {
    return <span className="badge-status badge-active">Active</span>
  }
  return <span className="badge-status badge-pending">{status || '—'}</span>
}

function hoursLabel(student) {
  const rendered = Number(student.hours_rendered ?? 0)
  const target = Number(student.target_hours ?? 0)
  if (!target && !rendered) return '—'
  return `${rendered}${target ? ` / ${target}` : ''} hrs`
}

/**
 * Shared supervisor directory for Faculty / Coordinator / Director.
 * @param {{ apiBase: string, bodyClass: string, cacheKey: string, subtitle?: string }} props
 */
function SupervisorsDirectory({
  apiBase,
  bodyClass,
  cacheKey,
  subtitle = 'HTE supervisors linked to students in your scope',
}) {
  const { loading, seed, run } = useCachedPage(cacheKey)
  const [supervisors, setSupervisors] = useState(() => unwrapList(seed).items)
  const [error, setError] = useState(null)
  const [search, setSearch] = useState('')
  const [expandedId, setExpandedId] = useState(null)
  const [detailLoading, setDetailLoading] = useState(null)

  const fetchList = () => {
    setError(null)
    run(() => api.get(`${apiBase}/supervisors`).then((res) => res.data))
      .then((next) => {
        if (next) setSupervisors(unwrapList(next).items)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load supervisors.')
        setSupervisors([])
      })
  }

  useEffect(() => {
    fetchList()
  }, [apiBase])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return supervisors
    return supervisors.filter((s) => {
      const hay = [
        s.name,
        s.faculty_number,
        s.login_username,
        s.email,
        s.contact_number,
        s.company,
        s.position,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      return hay.includes(q)
    })
  }, [supervisors, search])

  const toggleExpand = async (supervisor) => {
    if (expandedId === supervisor.id) {
      setExpandedId(null)
      return
    }

    setExpandedId(supervisor.id)

    // Refresh detail when expanding so assigned students stay current.
    setDetailLoading(supervisor.id)
    try {
      const res = await api.get(`${apiBase}/supervisors/${supervisor.id}`)
      const detail = res.data?.data ?? res.data
      if (detail?.id) {
        setSupervisors((prev) =>
          prev.map((row) => (row.id === detail.id ? { ...row, ...detail } : row))
        )
      }
    } catch {
      // Keep list payload if detail fetch fails.
    } finally {
      setDetailLoading(null)
    }
  }

  return (
    <Layout title="Supervisors" subtitle={subtitle} icon="fa-user-tie" bodyClass={bodyClass}>
      {error && <PageError message={error} onRetry={fetchList} />}

      <div className="content-card mb-3">
        <div className="content-card-header">
          <i className="fa fa-search"></i>
          <h6>Find supervisors</h6>
          <span className="ms-auto badge bg-secondary">{filtered.length}</span>
        </div>
        <div className="p-3">
          <input
            type="search"
            className="form-control"
            placeholder="Search by name, Supervisor ID, login, email, or company…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-table"></i>
          <h6>Supervisor Directory</h6>
        </div>
        <div className="table-card">
          {loading && supervisors.length === 0 ? (
            <div className="text-center py-4">
              <InternTrackLoader />
            </div>
          ) : filtered.length === 0 && !error ? (
            <EmptyState
              icon="fa-user-tie"
              title="No supervisors found"
              message="Supervisors appear here once they are assigned to students in your scope."
            />
          ) : filtered.length === 0 ? null : (
            <div className="table-responsive">
              <table className="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th style={{ width: 40 }}></th>
                    <th>Supervisor</th>
                    <th>Supervisor ID</th>
                    <th>Login</th>
                    <th>Company</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th className="text-center">Students</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((s) => {
                    const open = expandedId === s.id
                    const students = s.assigned_students || []
                    return (
                      <Fragment key={s.id}>
                        <tr
                          role="button"
                          style={{ cursor: 'pointer' }}
                          onClick={() => toggleExpand(s)}
                        >
                          <td className="text-muted">
                            <i className={`fa fa-chevron-${open ? 'down' : 'right'}`}></i>
                          </td>
                          <td>
                            <div className="fw-semibold">{s.name || '—'}</div>
                            {s.position && (
                              <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                                {s.position}
                              </div>
                            )}
                          </td>
                          <td style={{ fontSize: '0.85rem' }}>{s.faculty_number || '—'}</td>
                          <td style={{ fontSize: '0.85rem' }}>{s.login_username || '—'}</td>
                          <td style={{ fontSize: '0.85rem' }}>{s.company || '—'}</td>
                          <td style={{ fontSize: '0.82rem' }}>
                            <div>{s.email || '—'}</div>
                            <div className="text-muted">{s.contact_number || ''}</div>
                          </td>
                          <td>{approvalBadge(s)}</td>
                          <td className="text-center">
                            <span className="badge bg-light text-dark border">
                              {s.assigned_students_count ?? students.length}
                            </span>
                          </td>
                        </tr>
                        {open && (
                          <tr className="table-light">
                            <td colSpan={8} className="p-0">
                              <div className="p-3">
                                <div className="d-flex align-items-center mb-2">
                                  <h6 className="mb-0">
                                    <i className="fa fa-users me-2 text-muted"></i>
                                    Assigned students
                                  </h6>
                                  {detailLoading === s.id && (
                                    <span className="ms-2 text-muted small">Refreshing…</span>
                                  )}
                                </div>
                                {students.length === 0 ? (
                                  <div className="text-muted small">No assigned students in your scope.</div>
                                ) : (
                                  <div className="table-responsive">
                                    <table className="table table-sm mb-0">
                                      <thead>
                                        <tr>
                                          <th>Student</th>
                                          <th>Student No.</th>
                                          <th>Program</th>
                                          <th>Company</th>
                                          <th>Status</th>
                                          <th>Hours</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        {students.map((st) => (
                                          <tr key={st.id}>
                                            <td className="fw-semibold">{st.name}</td>
                                            <td>{st.student_number || '—'}</td>
                                            <td style={{ fontSize: '0.82rem' }}>{st.program || '—'}</td>
                                            <td style={{ fontSize: '0.82rem' }}>{st.company || '—'}</td>
                                            <td>
                                              <span className="badge-status badge-pending">
                                                {st.status || '—'}
                                              </span>
                                            </td>
                                            <td style={{ fontSize: '0.82rem' }}>{hoursLabel(st)}</td>
                                          </tr>
                                        ))}
                                      </tbody>
                                    </table>
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </Fragment>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default SupervisorsDirectory

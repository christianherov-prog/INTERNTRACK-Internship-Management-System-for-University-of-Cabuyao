import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

const ROLES = ['', 'student', 'faculty', 'coordinator', 'director', 'supervisor', 'admin']

function MisdUsers() {
  const [params, setParams] = useSearchParams()
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [search, setSearch] = useState(params.get('search') || '')
  const [role, setRole] = useState(params.get('role') || '')
  const [active, setActive] = useState(params.get('active') || '')

  const load = (page = 1) => {
    setLoading(true)
    setError(null)
    const query = { page, per_page: 25 }
    if (role) query.role = role
    if (active !== '') query.active = active
    if (search.trim()) query.search = search.trim()

    api.get('/admin/users', { params: query })
      .then((res) => {
        const { items, meta: m } = unwrapList(res.data)
        setRows(items)
        setMeta(m)
      })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load users.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    const next = {}
    if (role) next.role = role
    if (active !== '') next.active = active
    if (search.trim()) next.search = search.trim()
    setParams(next, { replace: true })
    load(1)
  }, [role, active])

  const applySearch = (e) => {
    e.preventDefault()
    load(1)
  }

  const toggleActive = async (row) => {
    if (!window.confirm(`${row.is_active ? 'Deactivate' : 'Activate'} ${row.username}?`)) return
    setMessage(null)
    try {
      await api.patch(`/admin/users/${row.id}/active`, { is_active: !row.is_active })
      setMessage({ type: 'success', text: `${row.username} updated.` })
      load(meta?.current_page || 1)
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || Object.values(err.response?.data?.errors || {})?.[0]?.[0] || 'Update failed.' })
    }
  }

  const resetPw = async (row) => {
    if (!window.confirm(`Reset password for ${row.username}?`)) return
    setMessage(null)
    try {
      await api.post(`/admin/users/${row.id}/reset-password`)
      setMessage({ type: 'success', text: `Password reset for ${row.username}.` })
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Reset failed.' })
    }
  }

  return (
    <Layout title="Users" subtitle="Account lifecycle & access control" icon="fa-users" bodyClass="admin-page">
      {error && <PageError message={error} onRetry={() => load()} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      <div className="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div className="card-body p-4">
          <form onSubmit={applySearch}>
            <div className="row g-3 align-items-end">
              <div className="col-12 col-md-6 col-lg-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Search User Accounts</label>
                <div className="input-group">
                  <span className="input-group-text bg-light border-end-0 text-muted rounded-start-3">
                    <i className="fa fa-magnifying-glass"></i>
                  </span>
                  <input
                    className="form-control border-start-0 rounded-end-3"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Username, full name, email…"
                  />
                </div>
              </div>
              <div className="col-12 col-sm-6 col-md-3 col-lg-3">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Role Filter</label>
                <select className="form-select rounded-3" value={role} onChange={(e) => setRole(e.target.value)}>
                  {ROLES.map((r) => <option key={r || 'all'} value={r}>{r ? r.charAt(0).toUpperCase() + r.slice(1) : 'All User Roles'}</option>)}
                </select>
              </div>
              <div className="col-12 col-sm-6 col-md-3 col-lg-3">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Account Status</label>
                <select className="form-select rounded-3" value={active} onChange={(e) => setActive(e.target.value)}>
                  <option value="">All Statuses</option>
                  <option value="true">Active Accounts Only</option>
                  <option value="false">Inactive Accounts Only</option>
                </select>
              </div>
              <div className="col-12 col-md-12 col-lg-2">
                <button className="btn btn-success w-100 rounded-3 fw-semibold py-2" type="submit">
                  <i className="fa fa-filter me-1.5"></i>Filter
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div className="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2 d-flex align-items-center justify-content-between">
          <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
            <i className="fa fa-users text-success"></i> Registered Accounts
          </h6>
          <span className="badge bg-light text-secondary border fw-semibold px-2.5 py-1" style={{ fontSize: '0.75rem' }}>
            {meta?.total != null ? `${meta.total} Total Users` : 'All Accounts'}
          </span>
        </div>
        <div className="card-body px-4 pt-2 pb-4">
          <div className="table-responsive border rounded-3 overflow-hidden">
            {loading ? (
              <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            ) : (
              <table className="table table-hover align-middle mb-0" style={{ fontSize: '0.86rem', minWidth: 780 }}>
                <thead style={{ background: '#f8fafc', borderBottom: '1px solid #eef2f6' }}>
                  <tr>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-4" style={{ fontSize: '0.72rem', letterSpacing: '0.05em', minWidth: 160 }}>User Name</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em', minWidth: 120 }}>Username / ID</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em', minWidth: 120 }}>Assigned Role</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em', minWidth: 120 }}>Account Status</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em', minWidth: 150 }}>Last Active</th>
                    <th className="text-uppercase text-muted fw-semibold py-3 px-4 text-center" style={{ fontSize: '0.72rem', letterSpacing: '0.05em', width: 110, whiteSpace: 'nowrap' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="text-center text-muted py-5">
                        <i className="fa-regular fa-folder-open fa-2x mb-2 d-block opacity-40"></i>
                        No user accounts found matching your query.
                      </td>
                    </tr>
                  ) : rows.map((row) => (
                    <tr key={row.id}>
                      <td className="px-4 py-3 fw-bold text-dark" style={{ whiteSpace: 'nowrap' }}>{row.name}</td>
                      <td className="px-3 py-3" style={{ whiteSpace: 'nowrap' }}>
                        <span className="badge bg-light text-dark border font-monospace fw-semibold" style={{ fontSize: '0.75rem' }}>
                          {row.username}
                        </span>
                      </td>
                      <td className="px-3 py-3" style={{ whiteSpace: 'nowrap' }}>
                        <span
                          className="badge fw-semibold px-2.5 py-1 rounded-pill"
                          style={{
                            fontSize: '0.75rem',
                            background: row.role === 'student' ? '#ecfdf5' : row.role === 'faculty' ? '#f0f9ff' : row.role === 'director' ? '#fffbeb' : row.role === 'coordinator' ? '#f5f3ff' : row.role === 'supervisor' ? '#f0fdfa' : '#f8fafc',
                            color: row.role === 'student' ? '#157938' : row.role === 'faculty' ? '#0284c7' : row.role === 'director' ? '#d97706' : row.role === 'coordinator' ? '#7c3aed' : row.role === 'supervisor' ? '#0d9488' : '#475569',
                            border: `1px solid ${row.role === 'student' ? '#a7f3d0' : row.role === 'faculty' ? '#bae6fd' : row.role === 'director' ? '#fde68a' : row.role === 'coordinator' ? '#ddd6fe' : row.role === 'supervisor' ? '#99f6e4' : '#e2e8f0'}`,
                          }}
                        >
                          {row.role}
                        </span>
                      </td>
                      <td className="px-3 py-3" style={{ whiteSpace: 'nowrap' }}>
                        <span className={`badge ${row.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} fw-bold px-2.5 py-1 rounded-pill`} style={{ fontSize: '0.75rem' }}>
                          {row.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-muted" style={{ whiteSpace: 'nowrap' }}>{row.last_login_at ? new Date(row.last_login_at).toLocaleString() : 'Never'}</td>
                      <td className="px-4 py-3 text-center" style={{ whiteSpace: 'nowrap' }}>
                        <div className="btn-group btn-group-sm">
                          <button className="btn btn-outline-secondary" title="Reset Password" onClick={() => resetPw(row)}>
                            <i className="fa fa-key"></i>
                          </button>
                          <button className={`btn btn-outline-${row.is_active ? 'warning' : 'success'}`} title={row.is_active ? 'Deactivate' : 'Activate'} onClick={() => toggleActive(row)}>
                            <i className={`fa fa-${row.is_active ? 'ban' : 'check'}`}></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
          {meta && meta.last_page > 1 && (
            <div className="pt-3.5 d-flex justify-content-between align-items-center">
              <button className="btn btn-sm btn-outline-success fw-semibold rounded-3 px-3" disabled={meta.current_page <= 1} onClick={() => load(meta.current_page - 1)}>
                <i className="fa fa-chevron-left me-1"></i>Previous
              </button>
              <span className="text-muted fw-semibold" style={{ fontSize: '0.85rem' }}>Page {meta.current_page} of {meta.last_page}</span>
              <button className="btn btn-sm btn-outline-success fw-semibold rounded-3 px-3" disabled={meta.current_page >= meta.last_page} onClick={() => load(meta.current_page + 1)}>
                Next<i className="fa fa-chevron-right ms-1"></i>
              </button>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default MisdUsers

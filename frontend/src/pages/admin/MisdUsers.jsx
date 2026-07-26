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

      <form className="content-card p-3 mb-3" onSubmit={applySearch}>
        <div className="row g-2 align-items-end">
          <div className="col-md-4">
            <label className="form-label fw-semibold">Search</label>
            <input className="form-control" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Username, name, email…" />
          </div>
          <div className="col-md-3">
            <label className="form-label fw-semibold">Role</label>
            <select className="form-select" value={role} onChange={(e) => setRole(e.target.value)}>
              {ROLES.map((r) => <option key={r || 'all'} value={r}>{r || 'All roles'}</option>)}
            </select>
          </div>
          <div className="col-md-3">
            <label className="form-label fw-semibold">Status</label>
            <select className="form-select" value={active} onChange={(e) => setActive(e.target.value)}>
              <option value="">All</option>
              <option value="true">Active</option>
              <option value="false">Inactive</option>
            </select>
          </div>
          <div className="col-md-2">
            <button className="btn btn-green w-100" type="submit">Filter</button>
          </div>
        </div>
      </form>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-table"></i>
          <h6>Accounts {meta?.total != null ? `(${meta.total})` : ''}</h6>
        </div>
        <div className="table-responsive">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <table className="table table-hover mb-0">
              <thead>
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th className="text-center">Actions</th></tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr><td colSpan={6} className="text-center text-muted py-4">No users found.</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id}>
                    <td className="fw-semibold">{row.name}</td>
                    <td><code>{row.username}</code></td>
                    <td><span className="badge bg-light text-dark text-uppercase">{row.role}</span></td>
                    <td>
                      <span className={`badge ${row.is_active ? 'bg-success' : 'bg-secondary'}`}>
                        {row.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td>{row.last_login_at ? new Date(row.last_login_at).toLocaleString() : 'Never'}</td>
                    <td className="text-center">
                      <div className="btn-group btn-group-sm">
                        <button className="btn btn-outline-secondary" title="Reset password" onClick={() => resetPw(row)}>
                          <i className="fa fa-key"></i>
                        </button>
                        <button className="btn btn-outline-warning" title={row.is_active ? 'Deactivate' : 'Activate'} onClick={() => toggleActive(row)}>
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
          <div className="p-3 d-flex justify-content-between align-items-center">
            <button className="btn btn-sm btn-outline-secondary" disabled={meta.current_page <= 1} onClick={() => load(meta.current_page - 1)}>Prev</button>
            <span className="text-muted">Page {meta.current_page} / {meta.last_page}</span>
            <button className="btn btn-sm btn-outline-secondary" disabled={meta.current_page >= meta.last_page} onClick={() => load(meta.current_page + 1)}>Next</button>
          </div>
        )}
      </div>
    </Layout>
  )
}

export default MisdUsers

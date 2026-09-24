import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import InternTrackLoader from '../../components/InternTrackLoader'

const ROLE_OPTIONS = [
  { value: '', label: 'All roles' },
  { value: 'admin', label: 'Admin' },
  { value: 'director', label: 'Director' },
  { value: 'coordinator', label: 'Coordinator' },
  { value: 'faculty', label: 'Faculty' },
  { value: 'supervisor', label: 'Supervisor' },
  { value: 'student', label: 'Student' },
]

function MisdAuditLogs() {
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [detail, setDetail] = useState(null)
  const [filters, setFilters] = useState({
    search: '',
    role: '',
    action: '',
    module: '',
    date_from: '',
    date_to: '',
    page: 1,
  })

  const load = async (nextFilters = filters) => {
    setLoading(true)
    setError(null)
    try {
      const params = {
        scope: 'all',
        per_page: 30,
        page: nextFilters.page || 1,
      }
      ;['search', 'role', 'action', 'module', 'date_from', 'date_to'].forEach((key) => {
        if (nextFilters[key]) params[key] = nextFilters[key]
      })
      const res = await api.get('/admin/audit-log', { params })
      const { items, meta: pageMeta } = unwrapList(res.data)
      setRows(items)
      setMeta(pageMeta)
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load audit logs.')
      setRows([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const applyFilters = (e) => {
    e?.preventDefault()
    const next = { ...filters, page: 1 }
    setFilters(next)
    load(next)
  }

  const goPage = (page) => {
    const next = { ...filters, page }
    setFilters(next)
    load(next)
  }

  return (
    <Layout title="Audit Logs" subtitle="System-wide activity history" icon="fa-clipboard-list" bodyClass="admin-page">
      {error && <PageError message={error} onRetry={() => load()} />}

      <form className="content-card p-3 mb-3" onSubmit={applyFilters}>
        <div className="row g-2 align-items-end">
          <div className="col-md-3">
            <label className="form-label small fw-semibold">Search</label>
            <input maxLength={100} className="form-control" value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value })} placeholder="User, action, subject" />
          </div>
          <div className="col-md-2">
            <label className="form-label small fw-semibold">Role</label>
            <select className="form-select" value={filters.role} onChange={(e) => setFilters({ ...filters, role: e.target.value })}>
              {ROLE_OPTIONS.map((o) => <option key={o.value || 'all'} value={o.value}>{o.label}</option>)}
            </select>
          </div>
          <div className="col-md-2">
            <label className="form-label small fw-semibold">Action</label>
            <input maxLength={255} className="form-control" value={filters.action} onChange={(e) => setFilters({ ...filters, action: e.target.value })} placeholder="document_approved" />
          </div>
          <div className="col-md-2">
            <label className="form-label small fw-semibold">Module</label>
            <input maxLength={255} className="form-control" value={filters.module} onChange={(e) => setFilters({ ...filters, module: e.target.value })} placeholder="Documents" />
          </div>
          <div className="col-md-1">
            <label className="form-label small fw-semibold">From</label>
            <input type="date" className="form-control" value={filters.date_from} onChange={(e) => setFilters({ ...filters, date_from: e.target.value })} />
          </div>
          <div className="col-md-1">
            <label className="form-label small fw-semibold">To</label>
            <input type="date" className="form-control" value={filters.date_to} onChange={(e) => setFilters({ ...filters, date_to: e.target.value })} />
          </div>
          <div className="col-md-1">
            <button type="submit" className="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div className="content-card">
        {loading ? (
          <div className="text-center py-5"><InternTrackLoader /></div>
        ) : (
          <div className="table-responsive">
            <table className="table table-hover mb-0 align-middle">
              <thead className="table-light">
                <tr>
                  <th>Date / Time</th>
                  <th>User</th>
                  <th>Role</th>
                  <th>Action</th>
                  <th>Module</th>
                  <th>Subject</th>
                  <th>Result</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr><td colSpan={8} className="text-center text-muted py-4">No audit events found.</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id}>
                    <td className="small">{row.created_at_display || row.created_at}</td>
                    <td className="small fw-semibold">{row.actor?.label || row.actor?.username || 'System'}</td>
                    <td className="small text-capitalize">{row.actor?.role || '—'}</td>
                    <td className="small"><code>{row.action}</code></td>
                    <td className="small">{row.module || 'System'}</td>
                    <td className="small" style={{ maxWidth: 280 }}>{row.summary || '—'}</td>
                    <td><span className="badge bg-success-subtle text-success">Success</span></td>
                    <td className="text-end">
                      <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setDetail(row)}>View Details</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {meta && (
          <div className="d-flex justify-content-between align-items-center p-3 border-top">
            <div className="small text-muted">
              Page {meta.current_page || filters.page} of {meta.last_page || 1} · {meta.total ?? rows.length} events
            </div>
            <div className="d-flex gap-2">
              <button type="button" className="btn btn-sm btn-outline-secondary" disabled={(meta.current_page || 1) <= 1} onClick={() => goPage((meta.current_page || 1) - 1)}>Previous</button>
              <button type="button" className="btn btn-sm btn-outline-secondary" disabled={(meta.current_page || 1) >= (meta.last_page || 1)} onClick={() => goPage((meta.current_page || 1) + 1)}>Next</button>
            </div>
          </div>
        )}
      </div>

      {detail && (
        <div className="modal show d-block" style={{ background: 'rgba(0,0,0,0.45)' }} role="dialog">
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Audit Event Details</h5>
                <button type="button" className="btn-close" onClick={() => setDetail(null)} aria-label="Close"></button>
              </div>
              <div className="modal-body">
                <div className="mb-2"><strong>Action:</strong> {detail.action}</div>
                <div className="mb-2"><strong>Module:</strong> {detail.module}</div>
                <div className="mb-2"><strong>Actor:</strong> {detail.actor?.label || detail.actor?.username || 'System'} ({detail.actor?.role || '—'})</div>
                <div className="mb-2"><strong>When:</strong> {detail.created_at_display || detail.created_at}</div>
                <div className="mb-2"><strong>Summary:</strong> {detail.summary}</div>
                {detail.new_values && (
                  <div className="mt-3">
                    <div className="fw-semibold mb-1">Safe details</div>
                    <pre className="bg-light border rounded p-2 small mb-0" style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(detail.new_values, null, 2)}</pre>
                  </div>
                )}
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setDetail(null)}>Close</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </Layout>
  )
}

export default MisdAuditLogs

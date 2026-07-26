import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

/**
 * Shared staff roster for Directors and Coordinators.
 * role: 'director' | 'coordinator'
 */
function MisdStaffPage({ role }) {
  const isDirector = role === 'director'
  const title = isDirector ? 'Directors' : 'Coordinators'
  const listPath = isDirector ? '/admin/directors' : '/admin/coordinators'
  const assignPath = listPath
  const subtitle = isDirector
    ? 'Assign and manage PALD Directors'
    : 'Assign and manage Practicum Coordinators'

  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [saving, setSaving] = useState(false)
  const [preview, setPreview] = useState(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [form, setForm] = useState({
    employee_number: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    contact_number: '',
    department: '',
    college: '',
    position: '',
  })

  const load = () => {
    setLoading(true)
    setError(null)
    api.get(listPath)
      .then((res) => setRows(unwrapList(res.data).items))
      .catch((err) => setError(err.response?.data?.message || `Failed to load ${title.toLowerCase()}.`))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [listPath])

  const openForm = () => {
    setForm({
      employee_number: '',
      first_name: '',
      middle_name: '',
      last_name: '',
      email: '',
      contact_number: '',
      department: '',
      college: '',
      position: isDirector ? 'PALD Director' : 'Practicum Coordinator',
    })
    setPreview(null)
    setShowForm(true)
    setMessage(null)
  }

  const lookupMisd = async () => {
    const emp = form.employee_number.trim().toUpperCase()
    if (!emp) return
    setPreviewLoading(true)
    setPreview(null)
    try {
      const res = await api.get(`/admin/misd/faculty/${encodeURIComponent(emp)}`)
      setPreview(res.data)
      const m = res.data.misd || {}
      setForm((p) => ({
        ...p,
        employee_number: emp,
        first_name: m.first_name || p.first_name,
        middle_name: m.middle_name || p.middle_name,
        last_name: m.last_name || p.last_name,
        email: m.email || p.email,
        contact_number: m.contact_number || p.contact_number,
        department: m.department || p.department,
        college: m.college || p.college,
        position: m.position || p.position,
      }))
    } catch (err) {
      setPreview({ found: false, message: err.response?.data?.message || 'Not found in MISD.' })
    } finally {
      setPreviewLoading(false)
    }
  }

  const handleAssign = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    try {
      await api.post(assignPath, {
        ...form,
        employee_number: form.employee_number.trim().toUpperCase(),
      })
      setMessage({ type: 'success', text: `${isDirector ? 'Director' : 'Coordinator'} assigned successfully.` })
      setShowForm(false)
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || Object.values(err.response?.data?.errors || {})?.[0]?.[0] || 'Assign failed.' })
    } finally {
      setSaving(false)
    }
  }

  const toggleActive = async (row) => {
    if (!window.confirm(`${row.is_active ? 'Deactivate' : 'Activate'} ${row.username}?`)) return
    setMessage(null)
    try {
      await api.put(`/admin/staff/${row.id}`, { is_active: !row.is_active })
      setMessage({ type: 'success', text: `${row.username} updated.` })
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || Object.values(err.response?.data?.errors || {})?.[0]?.[0] || 'Update failed.' })
    }
  }

  const revoke = async (row) => {
    if (!window.confirm(`Revoke ${row.username}? They will be deactivated.`)) return
    setMessage(null)
    try {
      await api.post(`/admin/staff/${row.id}/revoke`, { mode: 'deactivate' })
      setMessage({ type: 'success', text: `${row.username} revoked.` })
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || Object.values(err.response?.data?.errors || {})?.[0]?.[0] || 'Revoke failed.' })
    }
  }

  const sync = async (row) => {
    setMessage(null)
    try {
      await api.post(`/admin/staff/${row.id}/sync`)
      setMessage({ type: 'success', text: `${row.username} synced from MISD.` })
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Sync failed.' })
    }
  }

  const resetPw = async (row) => {
    if (!window.confirm(`Reset password for ${row.username} to the system default?`)) return
    setMessage(null)
    try {
      await api.post(`/admin/staff/${row.id}/reset-password`)
      setMessage({ type: 'success', text: `Password reset for ${row.username}.` })
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Reset failed.' })
    }
  }

  return (
    <Layout title={title} subtitle={subtitle} icon={isDirector ? 'fa-user-tie' : 'fa-user-check'} bodyClass="admin-page">
      {error && <PageError message={error} onRetry={load} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      <div className="d-flex justify-content-end mb-3">
        <button className="btn btn-green" onClick={openForm}>
          <i className="fa fa-plus me-2"></i>Assign {isDirector ? 'Director' : 'Coordinator'}
        </button>
      </div>

      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header">
            <i className="fa fa-user-plus"></i>
            <h6>Assign {isDirector ? 'Director' : 'Coordinator'}</h6>
          </div>
          <form className="p-3" onSubmit={handleAssign}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label fw-semibold">Employee Number <span className="text-danger">*</span></label>
                <div className="input-group">
                  <input
                    className="form-control"
                    value={form.employee_number}
                    onChange={(e) => setForm((p) => ({ ...p, employee_number: e.target.value }))}
                    placeholder={isDirector ? 'DIR-1002' : 'COR-1002'}
                    required
                  />
                  <button type="button" className="btn btn-outline-secondary" onClick={lookupMisd} disabled={previewLoading}>
                    {previewLoading ? <i className="fa fa-spinner fa-spin"></i> : 'Lookup MISD'}
                  </button>
                </div>
              </div>
              <div className="col-md-3">
                <label className="form-label fw-semibold">First Name</label>
                <input className="form-control" value={form.first_name} onChange={(e) => setForm((p) => ({ ...p, first_name: e.target.value }))} />
              </div>
              <div className="col-md-3">
                <label className="form-label fw-semibold">Last Name</label>
                <input className="form-control" value={form.last_name} onChange={(e) => setForm((p) => ({ ...p, last_name: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Email</label>
                <input type="email" className="form-control" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Department</label>
                <input className="form-control" value={form.department} onChange={(e) => setForm((p) => ({ ...p, department: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Position</label>
                <input className="form-control" value={form.position} onChange={(e) => setForm((p) => ({ ...p, position: e.target.value }))} />
              </div>
            </div>

            {preview && (
              <div className={`alert ${preview.found ? 'alert-info' : 'alert-warning'} mt-3 mb-0`}>
                {preview.found ? (
                  <>
                    MISD match: <strong>{preview.misd?.first_name} {preview.misd?.last_name}</strong>
                    {preview.existing && <> · Existing account role: <code>{preview.existing.role}</code></>}
                  </>
                ) : (
                  <>{preview.message} You can still assign with manual details.</>
                )}
              </div>
            )}

            <div className="mt-3">
              <button type="submit" className="btn btn-success me-2" disabled={saving}>
                <i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-2`}></i>
                {saving ? 'Saving…' : 'Confirm Assign'}
              </button>
              <button type="button" className="btn btn-secondary" onClick={() => setShowForm(false)}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-table"></i><h6>All {title}</h6></div>
        <div className="table-responsive">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Employee ID</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Last Login</th>
                  <th className="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr><td colSpan={6} className="text-center text-muted py-4">No {title.toLowerCase()} yet.</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id}>
                    <td className="fw-semibold">{row.name}</td>
                    <td><code>{row.username}</code></td>
                    <td>{row.email || '—'}</td>
                    <td>
                      <span className={`badge ${row.is_active ? 'bg-success' : 'bg-secondary'}`}>
                        {row.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td>{row.last_login_at ? new Date(row.last_login_at).toLocaleString() : 'Never'}</td>
                    <td className="text-center">
                      <div className="btn-group btn-group-sm">
                        <button className="btn btn-outline-green" title="Sync from MISD" onClick={() => sync(row)}>
                          <i className="fa fa-sync"></i>
                        </button>
                        <button className="btn btn-outline-secondary" title="Reset password" onClick={() => resetPw(row)}>
                          <i className="fa fa-key"></i>
                        </button>
                        <button className="btn btn-outline-warning" title={row.is_active ? 'Deactivate' : 'Activate'} onClick={() => toggleActive(row)}>
                          <i className={`fa fa-${row.is_active ? 'ban' : 'check'}`}></i>
                        </button>
                        <button className="btn btn-outline-danger" title="Revoke" onClick={() => revoke(row)}>
                          <i className="fa fa-user-minus"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default MisdStaffPage

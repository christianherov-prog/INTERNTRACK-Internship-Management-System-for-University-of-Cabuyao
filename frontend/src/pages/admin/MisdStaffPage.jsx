import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { useConfirm } from '../../contexts/ConfirmContext'

/**
 * Shared staff roster for Directors and Coordinators.
 * role: 'director' | 'coordinator'
 */
function MisdStaffPage({ role }) {
  const confirm = useConfirm()
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
    faculty_number: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    contact_number: '',
    department: '',
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
      faculty_number: '',
      first_name: '',
      middle_name: '',
      last_name: '',
      email: '',
      contact_number: '',
      department: '',
      position: isDirector ? 'PALD Director' : 'Practicum Coordinator',
    })
    setPreview(null)
    setShowForm(true)
    setMessage(null)
  }

  const lookupMisd = async () => {
    const emp = form.faculty_number.trim().toUpperCase()
    if (!emp) return
    setPreviewLoading(true)
    setPreview(null)
    try {
      const res = await api.get(`/admin/misd/faculty/${encodeURIComponent(emp)}`)
      setPreview(res.data)
      const m = res.data.misd || {}
      setForm((p) => ({
        ...p,
        faculty_number: emp,
        first_name: m.first_name || p.first_name,
        middle_name: m.middle_name || p.middle_name,
        last_name: m.last_name || p.last_name,
        email: m.email || p.email,
        contact_number: m.contact_number || p.contact_number,
        department: m.department || p.department,
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
        faculty_number: form.faculty_number.trim().toUpperCase(),
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
    if (!(await confirm({ message: `${row.is_active ? 'Deactivate' : 'Activate'} ${row.username}?` }))) return
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
    if (!(await confirm({ message: `Revoke ${row.username}? They will be deactivated.`, variant: 'danger' }))) return
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
    if (!(await confirm({ message: `Reset password for ${row.username} to the system default?`, variant: 'danger' }))) return
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
        <button className="btn btn-success rounded-3 fw-semibold px-3.5 py-2" onClick={openForm}>
          <i className="fa fa-plus me-2"></i>Assign {isDirector ? 'Director' : 'Coordinator'}
        </button>
      </div>

      {showForm && (
        <div className="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
          <div className="card-header bg-transparent border-0 px-4 pt-3.5 pb-2">
            <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
              <i className="fa fa-user-plus text-success"></i>
              Assign {isDirector ? 'Director' : 'Coordinator'}
            </h6>
          </div>
          <form className="p-4 pt-2" onSubmit={handleAssign}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>
                  Faculty / Employee Number <span className="text-danger">*</span>
                </label>
                <div className="input-group">
                  <input
                    className="form-control rounded-start-3"
                    value={form.faculty_number}
                    onChange={(e) => setForm((p) => ({ ...p, faculty_number: e.target.value }))}
                    placeholder={isDirector ? 'DIR-1002' : 'COR-1002'}
                    required
                  />
                  <button type="button" className="btn btn-outline-success fw-semibold rounded-end-3" onClick={lookupMisd} disabled={previewLoading}>
                    {previewLoading ? <i className="fa fa-spinner fa-spin me-1"></i> : <i className="fa fa-magnifying-glass me-1"></i>}
                    Lookup MISD
                  </button>
                </div>
              </div>
              <div className="col-md-3">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>First Name</label>
                <input className="form-control rounded-3" value={form.first_name} onChange={(e) => setForm((p) => ({ ...p, first_name: e.target.value }))} />
              </div>
              <div className="col-md-3">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Last Name</label>
                <input className="form-control rounded-3" value={form.last_name} onChange={(e) => setForm((p) => ({ ...p, last_name: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Email Address</label>
                <input type="email" className="form-control rounded-3" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Department / College</label>
                <input className="form-control rounded-3" value={form.department} onChange={(e) => setForm((p) => ({ ...p, department: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Official Position</label>
                <input className="form-control rounded-3" value={form.position} onChange={(e) => setForm((p) => ({ ...p, position: e.target.value }))} />
              </div>
            </div>

            {preview && (
              <div className={`alert ${preview.found ? 'alert-info' : 'alert-warning'} mt-3 mb-0 rounded-3 border-0`} style={{ fontSize: '0.86rem' }}>
                {preview.found ? (
                  <>
                    <i className="fa fa-circle-check me-1.5 text-info"></i>
                    MISD Match Found: <strong>{preview.misd?.first_name} {preview.misd?.last_name}</strong>
                    {preview.existing && <> · Existing Account Role: <code className="bg-white px-1.5 py-0.5 rounded">{preview.existing.role}</code></>}
                  </>
                ) : (
                  <>
                    <i className="fa fa-triangle-exclamation me-1.5"></i>
                    {preview.message} You can still assign with manual details.
                  </>
                )}
              </div>
            )}

            <div className="mt-3.5 d-flex gap-2">
              <button type="submit" className="btn btn-success rounded-3 fw-semibold px-3.5" disabled={saving}>
                <i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-1.5`}></i>
                {saving ? 'Saving…' : 'Confirm & Assign'}
              </button>
              <button type="button" className="btn btn-light border rounded-3 fw-semibold px-3" onClick={() => setShowForm(false)}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div className="card-header bg-transparent border-0 px-4 pt-3.5 pb-2 d-flex align-items-center justify-content-between">
          <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
            <i className={`fa ${isDirector ? 'fa-user-tie' : 'fa-user-check'} text-success`}></i>
            All Registered {title}
          </h6>
          <span className="badge bg-light text-secondary border fw-semibold px-2.5 py-1" style={{ fontSize: '0.75rem' }}>
            {rows.length} {rows.length === 1 ? 'account' : 'accounts'}
          </span>
        </div>
        <div className="table-responsive">
          {loading ? (
            <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <table className="table table-hover align-middle mb-0" style={{ fontSize: '0.86rem' }}>
              <thead style={{ background: '#f8fafc', borderBottom: '1px solid #eef2f6' }}>
                <tr>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-4" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Staff Name</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Employee ID</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Official Email</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Account Status</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Last Login</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-4 text-center" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center text-muted py-5">
                      <i className="fa-regular fa-folder-open fa-2x mb-2 d-block opacity-40"></i>
                      No {title.toLowerCase()} accounts registered yet.
                    </td>
                  </tr>
                ) : rows.map((row) => (
                  <tr key={row.id}>
                    <td className="px-4 py-3 fw-bold text-dark">{row.name}</td>
                    <td className="px-3 py-3">
                      <span className="badge bg-light text-dark border font-monospace fw-semibold" style={{ fontSize: '0.75rem' }}>
                        {row.username}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-muted">{row.email || '—'}</td>
                    <td className="px-3 py-3">
                      <span className={`badge ${row.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} fw-bold px-2.5 py-1 rounded-pill`} style={{ fontSize: '0.75rem' }}>
                        {row.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-muted">{row.last_login_at ? new Date(row.last_login_at).toLocaleString() : 'Never'}</td>
                    <td className="px-4 py-3 text-center">
                      <div className="btn-group btn-group-sm">
                        <button className="btn btn-outline-success" title="Sync from MISD" onClick={() => sync(row)}>
                          <i className="fa fa-rotate"></i>
                        </button>
                        <button className="btn btn-outline-secondary" title="Reset password" onClick={() => resetPw(row)}>
                          <i className="fa fa-key"></i>
                        </button>
                        <button className={`btn btn-outline-${row.is_active ? 'warning' : 'success'}`} title={row.is_active ? 'Deactivate' : 'Activate'} onClick={() => toggleActive(row)}>
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

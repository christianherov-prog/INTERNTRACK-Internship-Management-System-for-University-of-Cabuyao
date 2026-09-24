import { useState, useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'
import api from '../services/api'

function SupervisorProfileEditor() {
  const { user, updateUserLocal, refreshUser } = useAuth()
  const toast = useToast()
  const [saving, setSaving] = useState(false)
  const [companies, setCompanies] = useState([])
  const [companiesLoading, setCompaniesLoading] = useState(true)
  const [companiesError, setCompaniesError] = useState(null)
  const [form, setForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    contact: user?.contact || '',
    position: user?.position || '',
    company_id: user?.company_id ? String(user.company_id) : '',
    sex: user?.sex || '',
    login_username: user?.login_username || '',
  })

  const usernameLocked = Boolean(user?.login_username)

  useEffect(() => {
    setForm({
      name: user?.name || '',
      email: user?.email || '',
      contact: user?.contact || '',
      position: user?.position || '',
      company_id: user?.company_id ? String(user.company_id) : '',
      sex: user?.sex || '',
      login_username: user?.login_username || '',
    })
  }, [user?.id, user?.name, user?.email, user?.contact, user?.position, user?.company_id, user?.sex, user?.login_username])

  useEffect(() => {
    setCompaniesLoading(true)
    setCompaniesError(null)
    api.get('/supervisor/companies')
      .then((res) => {
        const list = res.data?.companies || res.data?.data || (Array.isArray(res.data) ? res.data : [])
        setCompanies(list)
      })
      .catch(() => {
        setCompanies([])
        setCompaniesError('Could not load host companies.')
      })
      .finally(() => setCompaniesLoading(false))
  }, [])

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const payload = {
        name: form.name,
        email: form.email,
        contact: form.contact,
        position: form.position,
        company_id: form.company_id || null,
        sex: form.sex || undefined,
      }
      if (!usernameLocked && form.login_username?.trim()) {
        payload.login_username = form.login_username.trim()
      }
      const res = await api.put('/auth/profile', payload)
      if (res.data?.user) updateUserLocal(res.data.user)
      await refreshUser()
      toast.success('Profile saved successfully')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to save profile.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="content-card mb-4">
      <div className="content-card-header">
        <i className="fa fa-user-pen"></i>
        <h6>Account Details</h6>
      </div>
      <form className="p-3" onSubmit={handleSave}>
        <p className="text-muted mb-3" style={{ fontSize: '0.88rem' }}>
          Update your own supervisor account — including a new host company or job title — without waiting on a coordinator.
        </p>
        <div className="row g-3">
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Full Name</label>
            <input maxLength={200} name="name" className="form-control" value={form.name} onChange={handleChange} required />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Email</label>
            <input maxLength={255} type="email" name="email" className="form-control" value={form.email} onChange={handleChange} required />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Username</label>
            <input
              name="login_username"
              className="form-control"
              value={form.login_username}
              onChange={handleChange}
              placeholder={usernameLocked ? undefined : 'Choose a username for login'}
              readOnly={usernameLocked}
              disabled={usernameLocked}
              minLength={3}
              maxLength={40}
              autoComplete="username"
            />
            <small className="text-muted">
              {usernameLocked
                ? 'Username is set and cannot be changed. You can still sign in with Supervisor ID or email.'
                : 'Optional while blank — set it once here. Letters, numbers, dots, underscores, and hyphens only.'}
            </small>
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Contact Number</label>
            <input maxLength={40} name="contact" className="form-control" value={form.contact} onChange={handleChange} />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Position / Title</label>
            <input maxLength={255} name="position" className="form-control" value={form.position} onChange={handleChange} placeholder="Position" />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Host Company</label>
            <select name="company_id" className="form-select" value={form.company_id} onChange={handleChange} disabled={companiesLoading}>
              <option value="">{companiesLoading ? 'Loading companies…' : 'Select company…'}</option>
              {companies.map((c) => (
                <option key={c.id} value={c.id}>{c.company_name || c.name}</option>
              ))}
            </select>
            {companiesError && <small className="text-danger">{companiesError}</small>}
            {!companiesLoading && companies.length === 0 && !companiesError && (
              <small className="text-muted">No host companies are available yet.</small>
            )}
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Sex</label>
            <select name="sex" className="form-select" value={form.sex} onChange={handleChange}>
              <option value="">Select…</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
        </div>
        <button type="submit" className="btn-green mt-3" disabled={saving}>
          <i className={`fa fa-${saving ? 'spinner fa-spin' : 'save'} me-2`}></i>
          {saving ? 'Saving…' : 'Save Profile'}
        </button>
      </form>
    </div>
  )
}

export default SupervisorProfileEditor

import { useState, useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'
import api from '../services/api'

function SupervisorProfileEditor() {
  const { user, updateUserLocal, refreshUser } = useAuth()
  const toast = useToast()
  const [saving, setSaving] = useState(false)
  const [companies, setCompanies] = useState([])
  const [form, setForm] = useState({
    name: user?.name || '',
    email: user?.email || '',
    contact: user?.contact || '',
    position: user?.position || '',
    company_id: user?.company_id ? String(user.company_id) : '',
    sex: user?.sex || '',
  })

  useEffect(() => {
    setForm({
      name: user?.name || '',
      email: user?.email || '',
      contact: user?.contact || '',
      position: user?.position || '',
      company_id: user?.company_id ? String(user.company_id) : '',
      sex: user?.sex || '',
    })
  }, [user?.id, user?.name, user?.email, user?.contact, user?.position, user?.company_id, user?.sex])

  useEffect(() => {
    api.get('/supervisor/companies')
      .then((res) => setCompanies(res.data.companies || []))
      .catch(() => {})
  }, [])

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const res = await api.put('/auth/profile', {
        name: form.name,
        email: form.email,
        contact: form.contact,
        position: form.position,
        company_id: form.company_id || null,
        sex: form.sex || undefined,
      })
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
            <input name="name" className="form-control" value={form.name} onChange={handleChange} required />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Email</label>
            <input type="email" name="email" className="form-control" value={form.email} onChange={handleChange} required />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Contact Number</label>
            <input name="contact" className="form-control" value={form.contact} onChange={handleChange} />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Position / Title</label>
            <input name="position" className="form-control" value={form.position} onChange={handleChange} placeholder="e.g. IT Manager" />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">Host Company</label>
            <select name="company_id" className="form-select" value={form.company_id} onChange={handleChange}>
              <option value="">Select company…</option>
              {companies.map((c) => (
                <option key={c.id} value={c.id}>{c.company_name}</option>
              ))}
            </select>
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

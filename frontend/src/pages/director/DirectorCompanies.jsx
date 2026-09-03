import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import EmptyState from '../../components/EmptyState'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function DirectorCompanies() {
  const [data, setData]       = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [editItem, setEditItem] = useState(null)
  const [saving, setSaving]   = useState(false)
  const [message, setMessage] = useState(null)
  const [form, setForm] = useState({ company_name: '', address: '', industry: '', contact_person: '', contact_email: '', contact_number: '', moa_status: 'active', moa_start_date: '', moa_expiry_date: '', slots_available: 0, notes: '' })

  const fetchCompanies = () => {
    setLoading(true)
    setError(null)
    api.get('/director/companies')
      .then(res => setData(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load companies.')
        setData([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchCompanies() }, [])

  const openCreate = () => {
    setEditItem(null)
    setForm({ company_name: '', address: '', industry: '', contact_person: '', contact_email: '', contact_number: '', moa_status: 'active', moa_start_date: '', moa_expiry_date: '', slots_available: 0, notes: '' })
    setShowForm(true)
  }

  const openEdit = (c) => {
    setEditItem(c)
    setForm({ company_name: c.company_name, address: c.address ?? '', industry: c.industry ?? '', contact_person: c.contact_person ?? '', contact_email: c.contact_email ?? '', contact_number: c.contact_number ?? '', moa_status: c.moa_status, moa_start_date: c.moa_start_date ?? '', moa_expiry_date: c.moa_expiry_date ?? '', slots_available: c.slots_available ?? 0, notes: c.notes ?? '' })
    setShowForm(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault(); setSaving(true); setMessage(null)
    try {
      if (editItem) {
        await api.put(`/director/companies/${editItem.id}`, form)
        setMessage({ type: 'success', text: 'Company updated successfully.' })
      } else {
        await api.post('/director/companies', form)
        setMessage({ type: 'success', text: 'Company added successfully.' })
      }
      setShowForm(false); fetchCompanies()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Failed to save.' })
    } finally { setSaving(false) }
  }

  const moaBadge = { active: 'badge-active', pending: 'badge-pending', expired: 'badge-inactive', for_renewal: 'badge-pending', 'on-process': 'badge-pending' }
  const moaLabel = { active: 'Active', pending: 'Pending', expired: 'Expired', for_renewal: 'For Renewal', 'on-process': 'On-Process' }

  return (
    <Layout title="Partner Companies" subtitle="MOA Management" icon="fa-building" bodyClass="director-page">
      {error && <PageError message={error} onRetry={fetchCompanies} />}

      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

      <div className="d-flex justify-content-end mb-3">
        <button className="btn btn-primary" onClick={openCreate}><i className="fa fa-plus me-2"></i>Add Company</button>
      </div>

      {/* Form */}
      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header"><i className="fa fa-pen"></i><h6>{editItem ? 'Edit Company' : 'Add Partner Company'}</h6></div>
          <form className="p-3" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-md-6"><label className="form-label fw-semibold">Company Name <span className="text-danger">*</span></label><input className="form-control" value={form.company_name} onChange={e => setForm(p => ({...p, company_name: e.target.value}))} required /></div>
              <div className="col-md-6"><label className="form-label fw-semibold">Industry</label><input className="form-control" value={form.industry} onChange={e => setForm(p => ({...p, industry: e.target.value}))} /></div>
              <div className="col-12"><label className="form-label fw-semibold">Address</label><input className="form-control" value={form.address} onChange={e => setForm(p => ({...p, address: e.target.value}))} /></div>
              <div className="col-md-4"><label className="form-label fw-semibold">Contact Person</label><input className="form-control" value={form.contact_person} onChange={e => setForm(p => ({...p, contact_person: e.target.value}))} /></div>
              <div className="col-md-4"><label className="form-label fw-semibold">Contact Email</label><input type="email" className="form-control" value={form.contact_email} onChange={e => setForm(p => ({...p, contact_email: e.target.value}))} /></div>
              <div className="col-md-4"><label className="form-label fw-semibold">Contact Number</label><input className="form-control" value={form.contact_number} onChange={e => setForm(p => ({...p, contact_number: e.target.value}))} /></div>
              <div className="col-md-3"><label className="form-label fw-semibold">MOA Status <span className="text-danger">*</span></label><select className="form-select" value={form.moa_status} onChange={e => setForm(p => ({...p, moa_status: e.target.value}))}><option value="active">Active</option><option value="pending">Pending</option><option value="on-process">On-Process</option><option value="for_renewal">For Renewal</option><option value="expired">Expired</option></select></div>
              <div className="col-md-3"><label className="form-label fw-semibold">MOA Start</label><input type="date" className="form-control" value={form.moa_start_date} onChange={e => setForm(p => ({...p, moa_start_date: e.target.value}))} /></div>
              <div className="col-md-3">
                <label className="form-label fw-semibold">MOA Expiry</label>
                <input type="date" className="form-control" value={form.moa_expiry_date} onChange={e => setForm(p => ({...p, moa_expiry_date: e.target.value}))} />
                {form.moa_status === 'active' && !form.moa_expiry_date && (
                  <div className="form-text text-warning">Recommended: set an expiry date for active MOAs so deployment eligibility stays clear.</div>
                )}
              </div>
              <div className="col-md-3"><label className="form-label fw-semibold">Available Slots</label><input type="number" className="form-control" value={form.slots_available} min={0} onChange={e => setForm(p => ({...p, slots_available: e.target.value}))} /></div>
            </div>
            <div className="mt-3">
              <button type="submit" className="btn btn-success me-2" disabled={saving}><i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-2`}></i>{saving ? 'Saving…' : 'Save'}</button>
              <button type="button" className="btn btn-secondary" onClick={() => setShowForm(false)}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      {/* Table */}
      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-table"></i><h6>All Partner Companies</h6></div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : data.length === 0 && !error ? (
            <EmptyState icon="fa-building" title="No companies registered" message="Add a partner company to start tracking MOAs and internship slots." />
          ) : data.length === 0 ? null : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead><tr><th>Company</th><th>Industry</th><th>Contact</th><th>MOA Status</th><th>Expiry</th><th>Slots</th><th className="text-center">Actions</th></tr></thead>
                <tbody>
                  {data.map(c => (
                    <tr key={c.id}>
                      <td className="fw-semibold">{c.company_name}</td>
                      <td style={{fontSize:'0.82rem'}}>{c.industry ?? '—'}</td>
                      <td style={{fontSize:'0.82rem'}}>{c.contact_person ?? '—'}</td>
                      <td><span className={`badge-status ${moaBadge[c.moa_status]}`}>{moaLabel[c.moa_status]}</span></td>
                      <td style={{fontSize:'0.82rem',color: c.moa_status === 'expired' ? '#dc2626' : '#64748b'}}>{c.moa_expiry_date ?? '—'}</td>
                      <td>{c.slots_available}</td>
                      <td className="text-center">
                        <button className="btn btn-sm btn-outline-primary" onClick={() => openEdit(c)}><i className="fa fa-pen"></i></button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default DirectorCompanies

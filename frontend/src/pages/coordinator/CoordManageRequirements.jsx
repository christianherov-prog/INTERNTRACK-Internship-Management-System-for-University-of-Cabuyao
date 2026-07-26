import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'

const CATEGORIES = ['pre-ojt', 'during', 'post-ojt', 'general']
const EMPTY_FORM = { name: '', description: '', category: 'pre-ojt', sort_order: '', is_active: true }

function CoordManageRequirements() {
  const [requirements, setRequirements] = useState([])
  const [loading, setLoading]           = useState(true)
  const [error, setError]               = useState(null)
  const [message, setMessage]           = useState(null)
  const [showForm, setShowForm]         = useState(false)
  const [editItem, setEditItem]         = useState(null)
  const [form, setForm]                 = useState(EMPTY_FORM)
  const [saving, setSaving]             = useState(false)

  const fetch = () => {
    setLoading(true)
    setError(null)
    api.get('/coordinator/requirements')
      .then(res => setRequirements(res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to load requirements.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetch() }, [])

  const openAdd = () => {
    setForm(EMPTY_FORM)
    setEditItem(null)
    setShowForm(true)
    setMessage(null)
  }

  const openEdit = (req) => {
    setForm({
      name: req.name,
      description: req.description || '',
      category: req.category,
      sort_order: req.sort_order ?? '',
      is_active: req.is_active,
    })
    setEditItem(req)
    setShowForm(true)
    setMessage(null)
  }

  const handleChange = e => {
    const { name, value, type, checked } = e.target
    setForm(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }))
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    try {
      if (editItem) {
        await api.put(`/coordinator/requirements/${editItem.id}`, form)
        setMessage({ type: 'success', text: 'Requirement updated successfully.' })
      } else {
        await api.post('/coordinator/requirements', form)
        setMessage({ type: 'success', text: 'Requirement added successfully.' })
      }
      setShowForm(false)
      fetch()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Save failed.' })
    } finally {
      setSaving(false)
    }
  }

  const handleDisable = async (req) => {
    if (!window.confirm(`Disable "${req.name}"? Students will no longer see this requirement.`)) return
    try {
      await api.delete(`/coordinator/requirements/${req.id}`)
      setMessage({ type: 'success', text: `"${req.name}" has been disabled.` })
      fetch()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Action failed.' })
    }
  }

  const handleToggleActive = async (req) => {
    try {
      await api.put(`/coordinator/requirements/${req.id}`, { is_active: !req.is_active })
      fetch()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to toggle status.' })
    }
  }

  const grouped = CATEGORIES.reduce((acc, cat) => {
    acc[cat] = requirements.filter(r => r.category === cat)
    return acc
  }, {})

  const categoryLabel = { 'pre-ojt': 'Pre-OJT', during: 'During OJT', 'post-ojt': 'Post-OJT', general: 'General' }
  const categoryColor = { 'pre-ojt': 'text-primary', during: 'text-success', 'post-ojt': 'text-warning', general: 'text-secondary' }

  return (
    <Layout
      title="Manage OJT Requirements"
      subtitle="Add, edit, or disable document requirements dynamically"
      icon="fa-list-check"
      bodyClass="coordinator-page"
    >
      {error && <PageError message={error} onRetry={fetch} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          <i className={`fa fa-${message.type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2`}></i>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {/* Toolbar */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <p className="text-muted mb-0" style={{ maxWidth: 560 }}>
          These requirements are shown dynamically to students in the Document Submission portal.
          Changes take effect immediately — <strong>no code changes needed</strong>.
        </p>
        <button className="btn btn-green" onClick={openAdd}>
          <i className="fa fa-plus me-2"></i>Add Requirement
        </button>
      </div>

      {/* â”€â”€ Add/Edit Form â”€â”€ */}
      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header">
            <i className="fa fa-pen-to-square"></i>
            <h6>{editItem ? `Edit: ${editItem.name}` : 'New Requirement'}</h6>
          </div>
          <form className="p-3 p-md-4" onSubmit={handleSave}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label fw-semibold">Requirement Name <span className="text-danger">*</span></label>
                <input
                  name="name" className="form-control"
                  placeholder="e.g. Medical Certificate"
                  value={form.name} onChange={handleChange} required
                />
              </div>
              <div className="col-md-3">
                <label className="form-label fw-semibold">Category <span className="text-danger">*</span></label>
                <select name="category" className="form-select" value={form.category} onChange={handleChange} required>
                  {CATEGORIES.map(c => (
                    <option key={c} value={c}>{categoryLabel[c]}</option>
                  ))}
                </select>
              </div>
              <div className="col-md-3">
                <label className="form-label fw-semibold">Sort Order</label>
                <input
                  name="sort_order" type="number" className="form-control"
                  placeholder="e.g. 1"
                  value={form.sort_order} onChange={handleChange}
                />
              </div>
              <div className="col-12">
                <label className="form-label fw-semibold">Description / Instructions</label>
                <textarea
                  name="description" className="form-control" rows={2}
                  placeholder="Instructions for students on what to submit…"
                  value={form.description} onChange={handleChange}
                />
              </div>
              <div className="col-12">
                <div className="form-check">
                  <input
                    className="form-check-input" type="checkbox" id="is_active"
                    name="is_active" checked={form.is_active} onChange={handleChange}
                  />
                  <label className="form-check-label" htmlFor="is_active">
                    Active (visible to students)
                  </label>
                </div>
              </div>
            </div>
            <div className="mt-3 d-flex gap-2">
              <button type="submit" className="btn btn-success" disabled={saving}>
                <i className={`fa fa-${saving ? 'spinner fa-spin' : 'save'} me-2`}></i>
                {saving ? 'Saving…' : 'Save Requirement'}
              </button>
              <button
                type="button" className="btn btn-outline-secondary"
                onClick={() => { setShowForm(false); setEditItem(null) }}
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* â”€â”€ Requirements by Category â”€â”€ */}
      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        CATEGORIES.map(cat => {
          const reqs = grouped[cat] || []
          return (
            <div key={cat} className="content-card mb-4">
              <div className="content-card-header">
                <i className={`fa fa-folder ${categoryColor[cat]}`}></i>
                <h6 className={categoryColor[cat]}>{categoryLabel[cat]} Requirements</h6>
                <span className="ms-auto badge bg-secondary">{reqs.length}</span>
              </div>
              <div className="table-card">
                {reqs.length === 0 ? (
                  <div className="text-center py-3 text-muted" style={{ fontSize: '0.88rem' }}>
                    No requirements in this category.
                  </div>
                ) : (
                  <div className="table-responsive">
                    <table className="table table-hover mb-0">
                      <thead>
                        <tr>
                          <th style={{ width: 40 }}>#</th>
                          <th>Requirement</th>
                          <th>Description</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {reqs.sort((a, b) => (a.sort_order ?? 999) - (b.sort_order ?? 999)).map(req => (
                          <tr key={req.id} className={!req.is_active ? 'table-secondary' : ''}>
                            <td className="text-muted">{req.sort_order ?? '—'}</td>
                            <td className="fw-semibold">
                              {req.name}
                              {!req.is_active && <span className="ms-2 badge bg-secondary">Disabled</span>}
                            </td>
                            <td className="text-muted" style={{ fontSize: '0.85rem' }}>
                              {req.description || '—'}
                            </td>
                            <td>
                              <div className="form-check form-switch mb-0">
                                <input
                                  className="form-check-input" type="checkbox"
                                  checked={req.is_active}
                                  onChange={() => handleToggleActive(req)}
                                  title={req.is_active ? 'Click to disable' : 'Click to enable'}
                                />
                              </div>
                            </td>
                            <td>
                              <button
                                className="btn btn-sm btn-outline-green me-1"
                                onClick={() => openEdit(req)}
                              >
                                <i className="fa fa-edit"></i>
                              </button>
                              {req.is_active && (
                                <button
                                  className="btn btn-sm btn-outline-danger"
                                  onClick={() => handleDisable(req)}
                                  title="Disable this requirement"
                                >
                                  <i className="fa fa-ban"></i>
                                </button>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          )
        })
      )}
    </Layout>
  )
}

export default CoordManageRequirements

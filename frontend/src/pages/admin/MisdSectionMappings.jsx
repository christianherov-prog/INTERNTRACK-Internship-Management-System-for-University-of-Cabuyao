import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

const emptyForm = {
  program: 'BS Information Technology',
  section: '',
  academic_year: '2025-2026',
  semester: 1,
  faculty_user_id: '',
  is_active: true,
}

function MisdSectionMappings() {
  const [rows, setRows] = useState([])
  const [faculty, setFaculty] = useState([])
  const [unmapped, setUnmapped] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [filters, setFilters] = useState({ academic_year: '', semester: '', section: '' })

  const load = () => {
    setLoading(true)
    setError(null)
    const params = {}
    if (filters.academic_year) params.academic_year = filters.academic_year
    if (filters.semester) params.semester = filters.semester
    if (filters.section) params.section = filters.section

    Promise.all([
      api.get('/admin/section-assignments', { params }),
      api.get('/admin/faculty-options'),
      api.get('/admin/misd/unmapped-sections'),
    ])
      .then(([a, f, u]) => {
        setRows(unwrapList(a.data).items)
        setFaculty(unwrapList(f.data).items)
        setUnmapped(u.data?.data || [])
      })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load section mappings.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const openCreate = (seed = null) => {
    setEditId(null)
    setForm({
      ...emptyForm,
      ...(seed || {}),
      faculty_user_id: seed?.faculty_user_id || '',
      semester: seed?.semester || 1,
      is_active: true,
    })
    setShowForm(true)
    setMessage(null)
  }

  const openEdit = (row) => {
    setEditId(row.id)
    setForm({
      program: (typeof row.program === 'string' ? row.program : row.program?.code || row.program?.name) || '',
      section: row.section || '',
      academic_year: row.academic_year || '',
      semester: row.semester || 1,
      faculty_user_id: row.faculty_user_id || '',
      is_active: !!row.is_active,
    })
    setShowForm(true)
    setMessage(null)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    const payload = {
      ...form,
      faculty_user_id: Number(form.faculty_user_id),
      semester: Number(form.semester),
      section: form.section.trim().toUpperCase(),
    }
    try {
      if (editId) {
        await api.put(`/admin/section-assignments/${editId}`, payload)
        setMessage({ type: 'success', text: 'Mapping updated.' })
      } else {
        await api.post('/admin/section-assignments', payload)
        setMessage({ type: 'success', text: 'Mapping created.' })
      }
      setShowForm(false)
      load()
    } catch (err) {
      setMessage({
        type: 'danger',
        text: err.response?.data?.message || Object.values(err.response?.data?.errors || {})?.[0]?.[0] || 'Save failed.',
      })
    } finally {
      setSaving(false)
    }
  }

  const remove = async (row) => {
    if (!window.confirm(`Delete mapping for ${formatYearSection(row.section)}?`)) return
    setMessage(null)
    try {
      await api.delete(`/admin/section-assignments/${row.id}`)
      setMessage({ type: 'success', text: 'Mapping deleted.' })
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Delete failed.' })
    }
  }

  return (
    <Layout title="Section Mappings" subtitle="Faculty ↔ section assignments by term" icon="fa-sitemap" bodyClass="admin-page">
      {error && <PageError message={error} onRetry={load} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {unmapped.length > 0 && (
        <div className="content-card mb-3">
          <div className="content-card-header"><i className="fa fa-exclamation-triangle text-warning"></i><h6>Unmapped Sections ({unmapped.length})</h6></div>
          <div className="table-responsive">
            <table className="table table-sm mb-0">
              <thead><tr><th>Section</th><th>Program</th><th>Term</th><th>Students</th><th></th></tr></thead>
              <tbody>
                {unmapped.map((u, i) => (
                  <tr key={i}>
                    <td><code>{formatYearSection(u.section)}</code></td>
                    <td>{(typeof u.program === 'string' ? u.program : u.program?.code || u.program?.name) || '—'}</td>
                    <td>{u.academic_year || '—'} · Sem {u.semester || '—'}</td>
                    <td>{u.student_count}</td>
                    <td>
                      <button
                        className="btn btn-sm btn-outline-primary"
                        onClick={() => openCreate({
                          section: u.section,
                          program: (typeof u.program === 'string' ? u.program : u.program?.code || u.program?.name) || '',
                          academic_year: u.academic_year || emptyForm.academic_year,
                          semester: u.semester || 1,
                        })}
                      >
                        Map now
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <div className="d-flex flex-wrap gap-2 justify-content-between mb-3">
        <div className="d-flex flex-wrap gap-2">
          <input
            className="form-control form-control-sm"
            style={{ width: 140 }}
            placeholder="AY e.g. 2025-2026"
            value={filters.academic_year}
            onChange={(e) => setFilters((p) => ({ ...p, academic_year: e.target.value }))}
          />
          <select
            className="form-select form-select-sm"
            style={{ width: 120 }}
            value={filters.semester}
            onChange={(e) => setFilters((p) => ({ ...p, semester: e.target.value }))}
          >
            <option value="">All sem</option>
            <option value="1">Sem 1</option>
            <option value="2">Sem 2</option>
          </select>
          <input
            className="form-control form-control-sm"
            style={{ width: 100 }}
            placeholder="Section"
            value={filters.section}
            onChange={(e) => setFilters((p) => ({ ...p, section: e.target.value }))}
          />
          <button className="btn btn-sm btn-outline-secondary" onClick={load}>Apply</button>
        </div>
        <button className="btn btn-primary btn-sm" onClick={() => openCreate()}>
          <i className="fa fa-plus me-1"></i>Add Mapping
        </button>
      </div>

      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header"><i className="fa fa-pen"></i><h6>{editId ? 'Edit Mapping' : 'Add Mapping'}</h6></div>
          <form className="p-3" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label fw-semibold">Section <span className="text-danger">*</span></label>
                <input className="form-control" value={form.section} onChange={(e) => setForm((p) => ({ ...p, section: e.target.value }))} placeholder="4ITA" required />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Academic Year <span className="text-danger">*</span></label>
                <input className="form-control" value={form.academic_year} onChange={(e) => setForm((p) => ({ ...p, academic_year: e.target.value }))} required />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Semester <span className="text-danger">*</span></label>
                <select className="form-select" value={form.semester} onChange={(e) => setForm((p) => ({ ...p, semester: e.target.value }))}>
                  <option value={1}>1</option>
                  <option value={2}>2</option>
                </select>
              </div>
              <div className="col-md-6">
                <label className="form-label fw-semibold">Program</label>
                <input className="form-control" value={(typeof form.program === 'string' ? form.program : form.program?.code || form.program?.name) || ''} onChange={(e) => setForm((p) => ({ ...p, program: e.target.value }))} />
              </div>
              <div className="col-md-6">
                <label className="form-label fw-semibold">Faculty <span className="text-danger">*</span></label>
                <select className="form-select" value={form.faculty_user_id} onChange={(e) => setForm((p) => ({ ...p, faculty_user_id: e.target.value }))} required>
                  <option value="">Select faculty…</option>
                  {faculty.map((f) => (
                    <option key={f.id} value={f.id}>{f.name} ({f.username})</option>
                  ))}
                </select>
              </div>
              <div className="col-md-4 d-flex align-items-end">
                <div className="form-check">
                  <input className="form-check-input" type="checkbox" id="mapActive" checked={form.is_active} onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))} />
                  <label className="form-check-label" htmlFor="mapActive">Active</label>
                </div>
              </div>
            </div>
            <div className="mt-3">
              <button type="submit" className="btn btn-success me-2" disabled={saving}>
                <i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-2`}></i>{saving ? 'Saving…' : 'Save'}
              </button>
              <button type="button" className="btn btn-secondary" onClick={() => setShowForm(false)}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-table"></i><h6>Current Mappings</h6></div>
        <div className="table-responsive">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <table className="table table-hover mb-0">
              <thead>
                <tr><th>Section</th><th>Program</th><th>Term</th><th>Faculty</th><th>Status</th><th className="text-center">Actions</th></tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr><td colSpan={6} className="text-center text-muted py-4">No mappings found.</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id}>
                    <td><code>{formatYearSection(row.section)}</code></td>
                    <td>{(typeof row.program === 'string' ? row.program : row.program?.code || row.program?.name) || '—'}</td>
                    <td>{row.academic_year} · Sem {row.semester}</td>
                    <td>{row.faculty?.name || '—'} <span className="text-muted">({row.faculty?.username})</span></td>
                    <td>
                      <span className={`badge ${row.is_active ? 'bg-success' : 'bg-secondary'}`}>
                        {row.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="text-center">
                      <div className="btn-group btn-group-sm">
                        <button className="btn btn-outline-primary" onClick={() => openEdit(row)}><i className="fa fa-pen"></i></button>
                        <button className="btn btn-outline-danger" onClick={() => remove(row)}><i className="fa fa-trash"></i></button>
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

export default MisdSectionMappings

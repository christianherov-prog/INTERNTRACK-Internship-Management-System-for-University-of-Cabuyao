import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import ConfirmModal from '../../components/modals/ConfirmModal'

const emptyForm = {
  program: 'BS Information Technology',
  section: '',
  academic_year: '2025-2026',
  semester: 2,
  faculty_user_id: '',
  is_active: true,
}

const formatTerm = (ay, sem) => {
  const year = ay && ay !== '—' ? ay : '2025-2026'
  let semStr = 'Sem 2'
  if (sem === 1 || sem === '1' || sem === '1st' || sem === '1st Semester') semStr = 'Sem 1'
  else if (sem === 2 || sem === '2' || sem === '2nd' || sem === '2nd Semester') semStr = 'Sem 2'
  else if (sem) semStr = String(sem).replace(/^sem\s*/i, 'Sem ')
  return `AY ${year}, ${semStr}`
}

const formatProgram = (p) => {
  if (!p) return 'BS Information Technology'
  if (typeof p === 'string') {
    if (p === 'BACHELORO' || p === '') return 'BS Information Technology'
    return p
  }
  if (p.name) return p.name
  if (p.code && p.code !== 'BACHELORO') return p.code
  return 'BS Information Technology'
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
  const [deletingRow, setDeletingRow] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [filters, setFilters] = useState({ academic_year: '', semester: '', section: '' })

  const load = () => {
    setLoading(true)
    setError(null)
    const params = {}
    if (filters.academic_year) params.school_year = filters.academic_year
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
      program: formatProgram(seed?.program),
      academic_year: seed?.academic_year || seed?.school_year || emptyForm.academic_year,
      faculty_user_id: seed?.faculty_user_id || '',
      semester: seed?.semester || 2,
      is_active: true,
    })
    setShowForm(true)
    setMessage(null)
  }

  const openEdit = (row) => {
    setEditId(row.id)
    setForm({
      program: formatProgram(row.program),
      section: row.section || '',
      academic_year: row.academic_year || row.school_year || '2025-2026',
      semester: row.semester ? (String(row.semester).includes('1') ? 1 : 2) : 2,
      faculty_user_id: row.faculty_user_id || row.faculty?.id || '',
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
      school_year: form.academic_year,
      faculty_user_id: Number(form.faculty_user_id),
      semester: Number(form.semester),
      section: form.section.trim().toUpperCase(),
    }
    try {
      if (editId) {
        await api.put(`/admin/section-assignments/${editId}`, payload)
        setMessage({ type: 'success', text: 'Mapping updated successfully.' })
      } else {
        await api.post('/admin/section-assignments', payload)
        setMessage({ type: 'success', text: 'Mapping created successfully.' })
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

  const handleConfirmDelete = async () => {
    if (!deletingRow) return
    setIsDeleting(true)
    setMessage(null)
    try {
      await api.delete(`/admin/section-assignments/${deletingRow.id}`)
      setMessage({ type: 'success', text: 'Mapping deleted.' })
      setDeletingRow(null)
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Delete failed.' })
    } finally {
      setIsDeleting(false)
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
        <div className="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden border-start border-warning border-4">
          <div className="card-header bg-transparent border-0 px-4 pt-3.5 pb-2 d-flex align-items-center justify-content-between">
            <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
              <i className="fa fa-triangle-exclamation text-warning"></i>
              Unmapped Section Groups ({unmapped.length})
            </h6>
            <span className="badge bg-warning-subtle text-warning-emphasis fw-semibold px-2.5 py-1" style={{ fontSize: '0.75rem' }}>
              Action Required
            </span>
          </div>
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0" style={{ fontSize: '0.86rem' }}>
              <thead style={{ background: '#f8fafc', borderBottom: '1px solid #eef2f6' }}>
                <tr>
                  <th className="text-uppercase text-muted fw-semibold py-2.5 px-4" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Section</th>
                  <th className="text-uppercase text-muted fw-semibold py-2.5 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Program</th>
                  <th className="text-uppercase text-muted fw-semibold py-2.5 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Academic Term</th>
                  <th className="text-uppercase text-muted fw-semibold py-2.5 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Enrolled Students</th>
                  <th className="text-uppercase text-muted fw-semibold py-2.5 px-4 text-center" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {unmapped.map((u, i) => (
                  <tr key={i}>
                    <td className="px-4 py-2.5">
                      <span className="badge bg-light text-dark border font-monospace fw-bold" style={{ fontSize: '0.8rem' }}>
                        {u.section}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 fw-medium text-dark">{formatProgram(u.program)}</td>
                    <td className="px-3 py-2.5 text-muted">{formatTerm(u.school_year || u.academic_year, u.semester)}</td>
                    <td className="px-3 py-2.5">
                      <span className="badge bg-success-subtle text-success fw-bold px-2 py-0.5 rounded-pill">
                        {u.student_count} {u.student_count === 1 ? 'student' : 'students'}
                      </span>
                    </td>
                    <td className="px-4 py-2.5 text-center">
                      <button
                        className="btn btn-sm btn-outline-success rounded-3 fw-semibold px-3"
                        onClick={() => openCreate({
                          section: u.section,
                          program: formatProgram(u.program),
                          academic_year: u.school_year || u.academic_year || emptyForm.academic_year,
                          semester: u.semester || 2,
                        })}
                      >
                        <i className="fa fa-sitemap me-1.5"></i>Map Now
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Filter and Action Bar */}
      <div className="card border-0 shadow-sm rounded-4 mb-4 bg-white p-3">
        <div className="d-flex flex-wrap gap-2.5 justify-content-between align-items-center">
          <div className="d-flex flex-wrap gap-2 align-items-center">
            <input
              className="form-control rounded-3"
              style={{ width: 160 }}
              placeholder="AY (e.g. 2025-2026)"
              value={filters.academic_year}
              onChange={(e) => setFilters((p) => ({ ...p, academic_year: e.target.value }))}
            />
            <select
              className="form-select rounded-3"
              style={{ width: 130 }}
              value={filters.semester}
              onChange={(e) => setFilters((p) => ({ ...p, semester: e.target.value }))}
            >
              <option value="">All Semesters</option>
              <option value="1">Sem 1</option>
              <option value="2">Sem 2</option>
            </select>
            <input
              className="form-control rounded-3"
              style={{ width: 120 }}
              placeholder="Section"
              value={filters.section}
              onChange={(e) => setFilters((p) => ({ ...p, section: e.target.value }))}
            />
            <button className="btn btn-outline-success fw-semibold rounded-3 px-3" onClick={load}>
              <i className="fa fa-filter me-1.5"></i>Apply Filter
            </button>
          </div>
          <button className="btn btn-success rounded-3 fw-semibold px-3.5 py-2" onClick={() => openCreate()}>
            <i className="fa fa-plus me-1.5"></i>Add New Mapping
          </button>
        </div>
      </div>

      {showForm && (
        <div className="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
          <div className="card-header bg-transparent border-0 px-4 pt-3.5 pb-2">
            <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
              <i className="fa fa-pen-to-square text-success"></i>
              {editId ? 'Edit Section Mapping' : 'Add New Section Mapping'}
            </h6>
          </div>
          <form className="p-4 pt-2" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>
                  Section Code <span className="text-danger">*</span>
                </label>
                <input className="form-control rounded-3 font-monospace" value={form.section} onChange={(e) => setForm((p) => ({ ...p, section: e.target.value }))} placeholder="4ITA" required />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>
                  Academic Year <span className="text-danger">*</span>
                </label>
                <input className="form-control rounded-3" value={form.academic_year} onChange={(e) => setForm((p) => ({ ...p, academic_year: e.target.value }))} required />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>
                  Semester <span className="text-danger">*</span>
                </label>
                <select className="form-select rounded-3" value={form.semester} onChange={(e) => setForm((p) => ({ ...p, semester: e.target.value }))}>
                  <option value={1}>Semester 1</option>
                  <option value={2}>Semester 2</option>
                </select>
              </div>
              <div className="col-md-6">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>Degree Program</label>
                <input className="form-control rounded-3" value={form.program} onChange={(e) => setForm((p) => ({ ...p, program: e.target.value }))} />
              </div>
              <div className="col-md-6">
                <label className="form-label fw-semibold text-muted" style={{ fontSize: '0.82rem' }}>
                  Assigned Faculty Supervisor <span className="text-danger">*</span>
                </label>
                <select className="form-select rounded-3" value={form.faculty_user_id} onChange={(e) => setForm((p) => ({ ...p, faculty_user_id: e.target.value }))} required>
                  <option value="">Select faculty supervisor…</option>
                  {faculty.map((f) => (
                    <option key={f.id} value={f.id}>{f.name} ({f.username})</option>
                  ))}
                </select>
              </div>
              <div className="col-md-4 d-flex align-items-center pt-2">
                <div className="form-check form-switch">
                  <input className="form-check-input" type="checkbox" id="mapActive" checked={form.is_active} onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))} />
                  <label className="form-check-label fw-semibold" htmlFor="mapActive">Active Mapping</label>
                </div>
              </div>
            </div>
            <div className="mt-3.5 d-flex gap-2">
              <button type="submit" className="btn btn-success rounded-3 fw-semibold px-3.5" disabled={saving}>
                <i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-1.5`}></i>{saving ? 'Saving…' : 'Save Mapping'}
              </button>
              <button type="button" className="btn btn-light border rounded-3 fw-semibold px-3" onClick={() => setShowForm(false)}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <div className="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div className="card-header bg-transparent border-0 px-4 pt-3.5 pb-2 d-flex align-items-center justify-content-between">
          <h6 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1rem' }}>
            <i className="fa fa-sitemap text-success"></i> Current Section Mappings
          </h6>
          <span className="badge bg-light text-secondary border fw-semibold px-2.5 py-1" style={{ fontSize: '0.75rem' }}>
            {rows.length} {rows.length === 1 ? 'mapping' : 'mappings'}
          </span>
        </div>
        <div className="table-responsive">
          {loading ? (
            <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <table className="table table-hover align-middle mb-0" style={{ fontSize: '0.86rem' }}>
              <thead style={{ background: '#f8fafc', borderBottom: '1px solid #eef2f6' }}>
                <tr>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-4" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Section</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Program</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Academic Term</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Faculty Supervisor</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-3" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Status</th>
                  <th className="text-uppercase text-muted fw-semibold py-3 px-4 text-center" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center text-muted py-5">
                      <i className="fa-regular fa-folder-open fa-2x mb-2 d-block opacity-40"></i>
                      No section mappings registered for this query.
                    </td>
                  </tr>
                ) : rows.map((row) => (
                  <tr key={row.id}>
                    <td className="px-4 py-3">
                      <span className="badge bg-light text-dark border font-monospace fw-bold" style={{ fontSize: '0.8rem' }}>
                        {row.section}
                      </span>
                    </td>
                    <td className="px-3 py-3 fw-medium text-dark">{formatProgram(row.program)}</td>
                    <td className="px-3 py-3 text-muted">{formatTerm(row.school_year || row.academic_year, row.semester)}</td>
                    <td className="px-3 py-3 fw-semibold text-dark">
                      {row.faculty?.name || '—'} <span className="text-muted fw-normal">({row.faculty?.username})</span>
                    </td>
                    <td className="px-3 py-3">
                      <span className={`badge ${row.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} fw-bold px-2.5 py-1 rounded-pill`} style={{ fontSize: '0.75rem' }}>
                        {row.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-center">
                      <div className="btn-group btn-group-sm">
                        <button className="btn btn-outline-success" title="Edit Mapping" onClick={() => openEdit(row)}>
                          <i className="fa fa-pen"></i>
                        </button>
                        <button className="btn btn-outline-danger" title="Delete Mapping" onClick={() => setDeletingRow(row)}>
                          <i className="fa fa-trash"></i>
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

      <ConfirmModal
        open={!!deletingRow}
        title="Delete Section Mapping?"
        message={`Are you sure you want to delete the mapping for section "${deletingRow?.section}"?`}
        confirmLabel="Delete Mapping"
        cancelLabel="Cancel"
        variant="danger"
        loading={isDeleting}
        onCancel={() => setDeletingRow(null)}
        onConfirm={handleConfirmDelete}
      />
    </Layout>
  )
}

export default MisdSectionMappings

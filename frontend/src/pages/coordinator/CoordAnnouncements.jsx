import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import ConfirmModal from '../../components/modals/ConfirmModal'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function CoordAnnouncements() {
  const [announcements, setAnnouncements] = useState([])
  const [loading, setLoading]   = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [saving, setSaving]     = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [message, setMessage]   = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [editItem, setEditItem] = useState(null)
  const [form, setForm] = useState({ title: '', content: '', target_role: 'all', is_pinned: false, expires_at: '' })

  const fetchAnnouncements = () => {
    setLoading(true)
    setLoadError(null)
    api.get('/coordinator/announcements')
      .then(res => setAnnouncements(unwrapList(res.data).items))
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load announcements.')
        setAnnouncements([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAnnouncements() }, [])

  const openCreate = () => { setEditItem(null); setForm({ title: '', content: '', target_role: 'all', is_pinned: false, expires_at: '' }); setShowForm(true) }
  const openEdit   = (a)  => { setEditItem(a); setForm({ title: a.title, content: a.content, target_role: a.target_role, is_pinned: a.is_pinned, expires_at: a.expires_at?.split('T')[0] ?? '' }); setShowForm(true) }

  const handleSubmit = async (e) => {
    e.preventDefault(); setSaving(true); setMessage(null)
    try {
      if (editItem) {
        await api.put(`/coordinator/announcements/${editItem.id}`, form)
        setMessage({ type: 'success', text: 'Announcement updated.' })
      } else {
        await api.post('/coordinator/announcements', form)
        setMessage({ type: 'success', text: 'Announcement posted.' })
      }
      setShowForm(false); fetchAnnouncements()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Failed to save.' })
    } finally { setSaving(false) }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(deleteTarget)
    try {
      await api.delete(`/coordinator/announcements/${deleteTarget}`)
      setDeleteTarget(null)
      fetchAnnouncements()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to delete.' })
    } finally {
      setDeleting(null)
    }
  }

  const roleBadge = { all: 'bg-secondary', student: 'bg-success', supervisor: 'bg-info', faculty: 'bg-warning', coordinator: 'bg-primary', director: 'bg-dark' }

  return (
    <Layout title="Announcements" subtitle="AY 2024-2025, Sem 2" icon="fa-bullhorn" bodyClass="coordinator-page">
      {loadError && <PageError message={loadError} onRetry={fetchAnnouncements} />}
      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

      <ConfirmModal
        open={!!deleteTarget}
        title="Delete announcement?"
        message="This announcement will be permanently removed."
        confirmLabel="Delete"
        variant="danger"
        loading={!!deleting}
        onCancel={() => !deleting && setDeleteTarget(null)}
        onConfirm={handleDelete}
      />

      <div className="d-flex justify-content-end mb-3">
        <button className="btn btn-primary" onClick={openCreate}><i className="fa fa-plus me-2"></i>New Announcement</button>
      </div>

      {/* Form */}
      {showForm && (
        <div className="content-card mb-4">
          <div className="content-card-header"><i className="fa fa-pen"></i><h6>{editItem ? 'Edit Announcement' : 'Create Announcement'}</h6></div>
          <form className="p-3" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-12">
                <label className="form-label fw-semibold">Title <span className="text-danger">*</span></label>
                <input className="form-control" value={form.title} onChange={e => setForm(p => ({...p, title: e.target.value}))} required maxLength={255} />
              </div>
              <div className="col-12">
                <label className="form-label fw-semibold">Content <span className="text-danger">*</span></label>
                <textarea className="form-control" rows={4} value={form.content} onChange={e => setForm(p => ({...p, content: e.target.value}))} required></textarea>
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Target Audience</label>
                <select className="form-select" value={form.target_role} onChange={e => setForm(p => ({...p, target_role: e.target.value}))}>
                  <option value="all">All Users</option>
                  <option value="student">Students Only</option>
                  <option value="supervisor">Supervisors Only</option>
                  <option value="faculty">Faculty Only</option>
                  <option value="coordinator">Coordinators Only</option>
                </select>
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Expiry Date (optional)</label>
                <input type="date" className="form-control" value={form.expires_at} onChange={e => setForm(p => ({...p, expires_at: e.target.value}))} />
              </div>
              <div className="col-md-4 d-flex align-items-end">
                <div className="form-check">
                  <input type="checkbox" className="form-check-input" id="pinCheck" checked={form.is_pinned} onChange={e => setForm(p => ({...p, is_pinned: e.target.checked}))} />
                  <label className="form-check-label" htmlFor="pinCheck">📌 Pin this announcement</label>
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

      {/* List */}
      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-list"></i><h6>All Announcements</h6></div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : announcements.length === 0 ? (
            <div className="text-center py-4 text-muted">No announcements yet.</div>
          ) : announcements.map(a => (
            <div key={a.id} className="p-3 border-bottom">
              <div className="d-flex align-items-start justify-content-between">
                <div className="flex-grow-1">
                  <div className="d-flex align-items-center gap-2 mb-1">
                    {a.is_pinned && <span style={{fontSize:'0.9rem'}}>📌</span>}
                    <strong>{a.title}</strong>
                    <span className={`badge ${roleBadge[a.target_role] ?? 'bg-secondary'} ms-1`} style={{fontSize:'0.7rem'}}>{a.target_role}</span>
                    <span className="ms-auto text-muted" style={{fontSize:'0.75rem'}}>{new Date(a.created_at).toLocaleDateString()}</span>
                  </div>
                  <p className="text-muted mb-0" style={{fontSize:'0.87rem'}}>{a.content}</p>
                </div>
                <div className="ms-3 d-flex gap-2 flex-shrink-0">
                  <button className="btn btn-sm btn-outline-primary" onClick={() => openEdit(a)}><i className="fa fa-pen"></i></button>
                  <button className="btn btn-sm btn-outline-danger" onClick={() => setDeleteTarget(a.id)} disabled={deleting === a.id}><i className={`fa fa-${deleting === a.id ? 'spinner fa-spin' : 'trash'}`}></i></button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </Layout>
  )
}

export default CoordAnnouncements

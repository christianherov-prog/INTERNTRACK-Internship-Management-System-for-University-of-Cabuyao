import { useState, useEffect, useRef } from 'react'
import Layout from '../../components/Layout'
import ConfirmModal from '../../components/modals/ConfirmModal'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import {
  AnnouncementAttachmentView,
  AnnouncementAttachPreview,
  ATTACH_ACCEPT,
  isImageFile,
  validateAttachFile,
} from '../../components/AnnouncementAttachment'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'

function CoordAnnouncements({ apiBase = '/coordinator', bodyClass = 'coordinator-page' }) {
  const [announcements, setAnnouncements] = useState([])
  const [loading, setLoading]   = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [saving, setSaving]     = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [message, setMessage]   = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [editItem, setEditItem] = useState(null)
  const [form, setForm] = useState({ title: '', content: '', target_role: 'all', category: 'general', is_pinned: false, expires_at: '' })
  const [attachFile, setAttachFile] = useState(null)
  const [attachPreviewUrl, setAttachPreviewUrl] = useState(null)
  const [existingAttachment, setExistingAttachment] = useState(null)
  const [removeExisting, setRemoveExisting] = useState(false)
  const fileInputRef = useRef(null)

  const clearLocalAttach = () => {
    setAttachFile(null)
    setAttachPreviewUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev)
      return null
    })
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const fetchAnnouncements = () => {
    setLoading(true)
    setLoadError(null)
    api.get(`${apiBase}/announcements`)
      .then(res => setAnnouncements(unwrapList(res.data).items))
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load announcements.')
        setAnnouncements([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchAnnouncements() }, [apiBase])

  const openCreate = () => {
    setEditItem(null)
    setForm({ title: '', content: '', target_role: 'all', category: 'general', is_pinned: false, expires_at: '' })
    clearLocalAttach()
    setExistingAttachment(null)
    setRemoveExisting(false)
    setShowForm(true)
  }

  const openEdit = (a) => {
    setEditItem(a)
    setForm({
      title: a.title,
      content: a.content,
      target_role: a.target_role,
      category: a.category || 'general',
      is_pinned: a.is_pinned,
      expires_at: a.expires_at?.split('T')[0] ?? '',
    })
    clearLocalAttach()
    setExistingAttachment(a.attachment || null)
    setRemoveExisting(false)
    setShowForm(true)
  }

  const onPickFile = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const err = validateAttachFile(file)
    if (err) {
      setMessage({ type: 'danger', text: err })
      e.target.value = ''
      return
    }
    setMessage(null)
    setAttachPreviewUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev)
      return isImageFile(file) ? URL.createObjectURL(file) : null
    })
    setAttachFile(file)
    setRemoveExisting(false)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    try {
      const needsMultipart = Boolean(attachFile) || (editItem && removeExisting)

      if (needsMultipart) {
        const fd = new FormData()
        fd.append('title', form.title)
        fd.append('content', form.content)
        fd.append('target_role', form.target_role)
        fd.append('category', form.category || 'general')
        fd.append('is_pinned', form.is_pinned ? '1' : '0')
        if (form.expires_at) fd.append('expires_at', form.expires_at)
        if (attachFile) fd.append('attachment', attachFile)
        if (editItem && removeExisting && !attachFile) fd.append('remove_attachment', '1')

        if (editItem) {
          await api.post(`${apiBase}/announcements/${editItem.id}`, fd)
          setMessage({ type: 'success', text: 'Announcement updated.' })
        } else {
          await api.post(`${apiBase}/announcements`, fd)
          setMessage({ type: 'success', text: 'Announcement posted.' })
        }
      } else if (editItem) {
        await api.put(`${apiBase}/announcements/${editItem.id}`, form)
        setMessage({ type: 'success', text: 'Announcement updated.' })
      } else {
        await api.post(`${apiBase}/announcements`, form)
        setMessage({ type: 'success', text: 'Announcement posted.' })
      }

      setShowForm(false)
      clearLocalAttach()
      fetchAnnouncements()
    } catch (err) {
      const status = err.response?.status
      const data = err.response?.data
      const text = data?.errors?.attachment?.[0]
        || data?.message
        || 'Failed to save.'
      if (status === 413) {
        setMessage({ type: 'danger', text: text || 'File is too large. Maximum size is 10 MB.' })
      } else {
        setMessage({ type: 'danger', text })
      }
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(deleteTarget)
    try {
      await api.delete(`${apiBase}/announcements/${deleteTarget}`)
      setDeleteTarget(null)
      fetchAnnouncements()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to delete.' })
    } finally {
      setDeleting(null)
    }
  }

  const roleBadge = {
    all: 'ann-badge ann-badge-all',
    student: 'ann-badge ann-badge-student',
    supervisor: 'ann-badge ann-badge-supervisor',
    faculty: 'ann-badge ann-badge-faculty',
    coordinator: 'ann-badge ann-badge-coordinator',
    director: 'ann-badge ann-badge-director',
  }
  const showExisting = existingAttachment && !removeExisting && !attachFile

  return (
    <Layout title="Announcements" subtitle={CURRENT_TERM} icon="fa-bullhorn" bodyClass={bodyClass}>
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
        <button className="btn btn-green" onClick={openCreate}><i className="fa fa-plus me-2"></i>New Announcement</button>
      </div>

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
                <label className="form-label fw-semibold">Category</label>
                <select className="form-select" value={form.category} onChange={e => setForm(p => ({...p, category: e.target.value}))}>
                  <option value="general">General</option>
                  <option value="policy_update">Policy Update</option>
                </select>
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold">Expiry Date (optional)</label>
                <input type="date" className="form-control" value={form.expires_at} onChange={e => setForm(p => ({...p, expires_at: e.target.value}))} />
              </div>
              <div className="col-md-4 d-flex align-items-end">
                <div className="form-check">
                  <input type="checkbox" className="form-check-input" id="pinCheck" checked={form.is_pinned} onChange={e => setForm(p => ({...p, is_pinned: e.target.checked}))} />
                  <label className="form-check-label" htmlFor="pinCheck">
                    <i className="fa fa-thumbtack me-1 text-success" aria-hidden="true"></i>
                    Pin this announcement
                  </label>
                </div>
              </div>
              <div className="col-12">
                <label className="form-label fw-semibold">Attachment (optional)</label>
                <div className="d-flex flex-wrap align-items-center gap-2">
                  <input
                    ref={fileInputRef}
                    type="file"
                    className="visually-hidden"
                    accept={ATTACH_ACCEPT}
                    onChange={onPickFile}
                    tabIndex={-1}
                    aria-hidden="true"
                  />
                  <button
                    type="button"
                    className="btn btn-outline-secondary"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={saving}
                  >
                    <i className="fa fa-paperclip me-2" aria-hidden="true" />
                    {attachFile || showExisting ? 'Replace file' : 'Attach file or image'}
                  </button>
                  <span className="text-muted" style={{ fontSize: '0.8rem' }}>Max 10 MB · images &amp; PDF/DOC/XLS</span>
                </div>
                {attachFile && (
                  <AnnouncementAttachPreview
                    file={attachFile}
                    previewUrl={attachPreviewUrl}
                    onRemove={clearLocalAttach}
                    disabled={saving}
                  />
                )}
                {showExisting && (
                  <div className="ann-attach-block">
                    <AnnouncementAttachmentView attachment={existingAttachment} />
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-danger mt-2"
                      onClick={() => setRemoveExisting(true)}
                      disabled={saving}
                    >
                      Remove attachment
                    </button>
                  </div>
                )}
                {editItem && removeExisting && !attachFile && (
                  <p className="text-muted mb-0 mt-2" style={{ fontSize: '0.82rem' }}>
                    Existing attachment will be removed on save.
                  </p>
                )}
              </div>
            </div>
            <div className="mt-3">
              <button type="submit" className="btn btn-success me-2" disabled={saving}>
                <i className={`fa fa-${saving ? 'spinner fa-spin' : 'check'} me-2`}></i>
                {saving ? 'Posting…' : 'Save'}
              </button>
              <button type="button" className="btn btn-secondary" onClick={() => { setShowForm(false); clearLocalAttach() }} disabled={saving}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-list"></i><h6>All Announcements</h6></div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : announcements.length === 0 ? (
            <EmptyState
              icon="fa-bullhorn"
              title="No announcements yet"
              message="Create a pinned or role-targeted announcement for students and staff."
            />
          ) : announcements.map(a => (
            <div key={a.id} className="p-3 border-bottom ann-list-item">
              <div className="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                <div className="flex-grow-1 min-w-0">
                  <div className="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    {a.is_pinned && (
                      <span className="ann-pin-flag" title="Pinned">
                        <i className="fa fa-thumbtack" aria-hidden="true"></i>
                      </span>
                    )}
                    <strong className="ann-title">{a.title}</strong>
                    {a.category === 'policy_update' && (
                      <span className="ann-badge ann-badge-policy">Policy Update</span>
                    )}
                    <span className={roleBadge[a.target_role] ?? 'ann-badge ann-badge-all'}>{a.target_role}</span>
                    <span className="ms-md-auto text-muted ann-date">{new Date(a.created_at).toLocaleDateString()}</span>
                  </div>
                  <p className="text-muted mb-0 ann-body">{a.content}</p>
                  {a.attachment && (
                    <div className="ann-attach-block">
                      <AnnouncementAttachmentView attachment={a.attachment} />
                    </div>
                  )}
                </div>
                <div className="d-flex gap-2 flex-shrink-0">
                  <button className="btn btn-sm btn-outline-green" onClick={() => openEdit(a)}><i className="fa fa-pen"></i></button>
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

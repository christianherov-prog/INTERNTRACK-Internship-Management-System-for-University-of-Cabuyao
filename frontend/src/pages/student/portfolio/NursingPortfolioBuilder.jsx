import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import PageError from '../../../components/PageError'
import api from '../../../services/api'
import { AuthenticatedFileLink } from '../../../components/AuthenticatedFile'
import ConfirmModal from '../../../components/modals/ConfirmModal'
import {
  NUR_COURSE,
  NUR_ROTATIONS,
  NUR_COLLEGE,
  emptyNursingFields,
  ROTATION_UPLOADS,
  GLOBAL_UPLOADS,
  rotationDocType,
  globalDocType,
} from './nursingPortfolioStructure'

function NursingPortfolioBuilder() {
  const [data, setData] = useState(null)
  const [loadError, setLoadError] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [activeTab, setActiveTab] = useState('front')
  const [form, setForm] = useState(emptyNursingFields())
  const [deletingItem, setDeletingItem] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const fetchPortfolio = () => {
    api.get('/student/portfolio')
      .then((res) => {
        setLoadError(null)
        setData(res.data)
        const saved = res.data.internship?.portfolio?.custom_fields?.nursing || {}
        const empty = emptyNursingFields()
        setForm({
          ...empty,
          ...saved,
          rotations: {
            1: { ...empty.rotations[1], ...(saved.rotations?.[1] || saved.rotations?.['1'] || {}) },
            2: { ...empty.rotations[2], ...(saved.rotations?.[2] || saved.rotations?.['2'] || {}) },
            3: { ...empty.rotations[3], ...(saved.rotations?.[3] || saved.rotations?.['3'] || {}) },
            4: { ...empty.rotations[4], ...(saved.rotations?.[4] || saved.rotations?.['4'] || {}) },
            5: { ...empty.rotations[5], ...(saved.rotations?.[5] || saved.rotations?.['5'] || {}) },
          },
        })
      })
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load portfolio.')
      })
  }

  useEffect(() => { fetchPortfolio() }, [])

  const setFrontField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }))
  const setRotationField = (rotationId, key, value) => {
    setForm((prev) => ({
      ...prev,
      rotations: {
        ...prev.rotations,
        [rotationId]: { ...prev.rotations[rotationId], [key]: value },
      },
    }))
  }

  const handleSave = async (e) => {
    e?.preventDefault?.()
    setSaving(true)
    setMessage(null)
    try {
      await api.post('/student/portfolio', { custom_fields: { nursing: form } })
      setMessage({ type: 'success', text: 'Portfolio data saved successfully.' })
      fetchPortfolio()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Failed to save.' })
    } finally {
      setSaving(false)
      window.scrollTo(0, 0)
    }
  }

  const handleFileUpload = async (e, type) => {
    const file = e.target.files[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)
    formData.append('label', file.name)
    try {
      await api.post('/student/portfolio/photos', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      fetchPortfolio()
    } catch (err) {
      alert('Failed to upload file: ' + (err.response?.data?.message || err.message))
    } finally {
      e.target.value = ''
    }
  }

  const handleConfirmDeleteFile = async () => {
    if (!deletingItem) return
    setIsDeleting(true)
    try {
      await api.delete(`/student/portfolio/photos/${deletingItem.id}`)
      fetchPortfolio()
      setDeletingItem(null)
    } catch {
      alert('Failed to delete file.')
    } finally {
      setIsDeleting(false)
    }
  }

  const p = data?.internship?.portfolio
  const photos = p?.photos || []
  const user = data?.user
  const profile = user?.student_profile
  const programName = typeof user?.program === 'string'
    ? user.program
    : (user?.program?.name || profile?.program?.name || 'Bachelor of Science in Nursing')
  const collegeName = typeof user?.department === 'object'
    ? (user.department?.name || NUR_COLLEGE)
    : (user?.department || profile?.department?.name || NUR_COLLEGE)
  const studentName = profile
    ? `${profile.last_name || ''}, ${profile.first_name || ''}`.replace(/^,\s*/, '').trim() || user?.name
    : (user?.name || 'Student')

  const matchesType = (item, type) => item.type === type || item.document_type === type || item.original_type === type

  const renderFileList = (type, title, tip = '') => {
    const items = photos.filter((photo) => matchesType(photo, type))
    return (
      <div className="content-card portfolio-upload-tile">
        <div className="portfolio-upload-tile-head">
          <h6 className="mb-0">{title}</h6>
          {tip && <span className="portfolio-upload-tip" title={tip}><i className="fa fa-circle-info"></i></span>}
        </div>
        <div className="portfolio-upload-tile-body">
          <div className="portfolio-upload-tile-content">
            {items.length > 0 ? (
              <div className="portfolio-upload-files">
                {items.map((item) => (
                  <div key={item.id} className="portfolio-upload-file-row">
                    <div className="text-truncate flex-grow-1 me-2 small">
                      {item.file_path && String(item.file_path).toLowerCase().endsWith('.pdf')
                        ? <i className="fa fa-file-pdf text-danger me-1"></i>
                        : <i className="fa fa-image text-primary me-1"></i>}
                      {item.label || item.file_name || 'Uploaded'}
                    </div>
                    <div className="d-flex gap-1 flex-shrink-0">
                      <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}>
                        <i className="fa fa-eye"></i>
                      </AuthenticatedFileLink>
                      <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => setDeletingItem(item)}>
                        <i className="fa fa-trash"></i>
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="portfolio-upload-empty">No files yet</p>
            )}
            {tip && <p className="portfolio-upload-hint">{tip}</p>}
          </div>
          <div className="portfolio-upload-btn-wrap">
            <input type="file" id={`upload-${type}`} className="d-none" accept="image/*,.pdf,.png,.jpg,.jpeg,.webp,.gif" onChange={(e) => handleFileUpload(e, type)} />
            <label htmlFor={`upload-${type}`} className="btn btn-outline-primary btn-sm w-100 portfolio-upload-btn mb-0">
              <i className="fa fa-upload me-1"></i>{items.length > 0 ? 'Upload More' : 'Upload'}
            </label>
          </div>
        </div>
      </div>
    )
  }

  const renderUploadGroup = (items, typeFn) => (
    <div className="portfolio-upload-grid">
      {items.map((item) => renderFileList(typeFn(item.suffix), item.label, item.tip))}
    </div>
  )

  const rotationTextKeys = ['hte_name', 'hte_address', 'hte_profile', 'hte_vision', 'hte_mission', 'hte_values']
  const frontKeys = ['bio_sketch', 'acknowledgement', 'narrative', 'rec_students', 'rec_program', 'rec_curriculum', 'rec_hte']
  const textDone = frontKeys.filter((k) => (form[k] || '').trim()).length
    + NUR_ROTATIONS.reduce((n, r) => n + rotationTextKeys.filter((k) => (form.rotations[r.id]?.[k] || '').trim()).length, 0)
  const textTotal = frontKeys.length + NUR_ROTATIONS.length * rotationTextKeys.length
  const allUploadTypes = [
    ...GLOBAL_UPLOADS.map((i) => globalDocType(i.suffix)),
    ...NUR_ROTATIONS.flatMap((r) => ROTATION_UPLOADS.map((i) => rotationDocType(r.id, i.suffix))),
  ]
  const uploadsDone = allUploadTypes.filter((t) => photos.some((ph) => matchesType(ph, t))).length
  const uploadsTotal = allUploadTypes.length

  const tabs = [
    { key: 'front', label: 'Front Matter', icon: 'fa-book-open' },
    ...NUR_ROTATIONS.map((r) => ({ key: `r${r.id}`, label: `Rotation ${r.id}`, icon: 'fa-rotate' })),
    { key: 'proper', label: 'Internship Proper', icon: 'fa-notes-medical' },
    { key: 'appendices', label: 'Appendices', icon: 'fa-folder-open' },
  ]

  const saveBar = (
    <div className="portfolio-form-actions">
      <button type="submit" className="btn btn-success px-5" disabled={saving}>
        {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Text Fields</>}
      </button>
    </div>
  )

  const renderRotation = (rotationId) => {
    const fields = form.rotations[rotationId] || emptyNursingFields().rotations[rotationId]
    const rotationMeta = NUR_ROTATIONS.find((r) => r.id === rotationId)
    return (
      <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
        <div className="d-flex flex-column gap-4">
          <div className="alert alert-light border mb-0">
            <strong>{rotationMeta.title}</strong>
            <span className="text-muted small d-block mt-1">Write this site’s profile from your own placement. Do not paste another student’s write-up.</span>
          </div>
          <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
            <div className="content-card-header bg-light"><h6 className="mb-0">Host Training Establishment Profile</h6></div>
            <div className="p-3 p-lg-4">
              <div className="portfolio-hte-row mb-3">
                <div>
                  <label className="portfolio-field-label">HTE / Cooperating Site</label>
                  <input className="form-control portfolio-field-input" value={fields.hte_name} onChange={(e) => setRotationField(rotationId, 'hte_name', e.target.value)} placeholder="Site name for this rotation" />
                </div>
                <div>
                  <label className="portfolio-field-label">Address</label>
                  <input className="form-control portfolio-field-input" value={fields.hte_address} onChange={(e) => setRotationField(rotationId, 'hte_address', e.target.value)} placeholder="Address" />
                </div>
              </div>
              <div className="mb-3">
                <label className="portfolio-field-label">Company / Site Profile</label>
                <textarea className="form-control portfolio-field-input" rows={5} value={fields.hte_profile} onChange={(e) => setRotationField(rotationId, 'hte_profile', e.target.value)} />
              </div>
              <div className="mb-3">
                <label className="portfolio-field-label">Vision</label>
                <textarea className="form-control portfolio-field-input" rows={3} value={fields.hte_vision} onChange={(e) => setRotationField(rotationId, 'hte_vision', e.target.value)} />
              </div>
              <div className="mb-3">
                <label className="portfolio-field-label">Mission</label>
                <textarea className="form-control portfolio-field-input" rows={3} value={fields.hte_mission} onChange={(e) => setRotationField(rotationId, 'hte_mission', e.target.value)} />
              </div>
              <div className="mb-3">
                <label className="portfolio-field-label">Core Values / Objectives</label>
                <textarea className="form-control portfolio-field-input" rows={3} value={fields.hte_values} onChange={(e) => setRotationField(rotationId, 'hte_values', e.target.value)} />
              </div>
              {renderUploadGroup(ROTATION_UPLOADS, (suffix) => rotationDocType(rotationId, suffix))}
            </div>
          </div>
        </div>
        {saveBar}
      </form>
    )
  }

  if (loadError) {
    return (
      <Layout title="My Portfolio" subtitle="BS Nursing · NCM 122" icon="fa-folder" bodyClass="student-page">
        <PageError message={loadError} onRetry={fetchPortfolio} />
      </Layout>
    )
  }

  if (!data) {
    return (
      <Layout title="My Portfolio" subtitle="BS Nursing · NCM 122" icon="fa-folder" bodyClass="student-page">
        <div className="text-center py-5 mt-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      </Layout>
    )
  }

  return (
    <Layout title="My Portfolio" subtitle="BS Nursing · NCM 122" icon="fa-folder" bodyClass="student-page">
      <div className="portfolio-builder">
        <div className="content-card mb-0">
          <div className="p-3 p-lg-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div className="fw-bold">{studentName}</div>
              <div className="text-muted small">{programName} · {NUR_COURSE}</div>
              <div className="text-muted small">{collegeName}</div>
            </div>
            <div className="d-flex flex-wrap gap-3">
              <div><div className="fw-bold lh-1">{textDone}/{textTotal}</div><div className="text-muted" style={{ fontSize: '0.72rem' }}>TEXT FIELDS</div></div>
              <div><div className="fw-bold lh-1">{uploadsDone}/{uploadsTotal}</div><div className="text-muted" style={{ fontSize: '0.72rem' }}>UPLOADS</div></div>
              <div><div className="fw-bold lh-1">{Math.round(((textDone + uploadsDone) / Math.max(textTotal + uploadsTotal, 1)) * 100)}%</div><div className="text-muted" style={{ fontSize: '0.72rem' }}>COMPLETION</div></div>
            </div>
            <Link to="/student/portfolio/preview" className="btn btn-primary btn-sm px-3 shadow-sm">
              <i className="fa fa-eye me-1"></i>Preview Portfolio
            </Link>
          </div>
        </div>

        {message && (
          <div className={`alert alert-${message.type} alert-dismissible fade show shadow-sm`} role="alert">
            {message.text}
            <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
          </div>
        )}

        <div className="d-flex justify-content-center mb-4" style={{ overflowX: 'auto' }}>
          <div className="placement-tabs-bar">
            {tabs.map((tab) => (
              <button key={tab.key} className={`placement-tab-btn${activeTab === tab.key ? ' active' : ''}`} onClick={(e) => { e.preventDefault(); setActiveTab(tab.key) }}>
                <i className={`fa ${tab.icon}`}></i>
                <span>{tab.label}</span>
              </button>
            ))}
          </div>
        </div>

        {activeTab === 'front' && (
          <form onSubmit={handleSave} className="bg-white p-4 border rounded shadow-sm mb-4">
            <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
              <div className="content-card-header bg-light"><h6 className="mb-0">Front matter</h6></div>
              <div className="p-3 p-lg-4">
                <p className="mb-3">Cover uses your live student record. Write your own biographical sketch and acknowledgement — do not copy another intern’s text.</p>
                <div className="mb-3">
                  <label className="portfolio-field-label">Biographical Sketch</label>
                  <textarea className="form-control portfolio-field-input" rows={8} value={form.bio_sketch} onChange={(e) => setFrontField('bio_sketch', e.target.value)} placeholder="Your background, education, and clinical preparation." />
                </div>
                <div className="mb-0">
                  <label className="portfolio-field-label">Acknowledgement</label>
                  <textarea className="form-control portfolio-field-input" rows={8} value={form.acknowledgement} onChange={(e) => setFrontField('acknowledgement', e.target.value)} placeholder="Thank your supervisors, faculty, family, and host sites." />
                </div>
              </div>
            </div>
            {saveBar}
          </form>
        )}

        {activeTab === 'r1' && renderRotation(1)}
        {activeTab === 'r2' && renderRotation(2)}
        {activeTab === 'r3' && renderRotation(3)}
        {activeTab === 'r4' && renderRotation(4)}
        {activeTab === 'r5' && renderRotation(5)}

        {activeTab === 'proper' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="content-card portfolio-chapter-card border-0 shadow-none mb-3">
              <div className="content-card-header bg-light"><h6 className="mb-0">Narrative &amp; Insights of Internship Learning Experiences</h6></div>
              <div className="p-3 p-lg-4">
                <textarea className="form-control portfolio-field-input" rows={10} value={form.narrative} onChange={(e) => setFrontField('narrative', e.target.value)} placeholder="Write one narrative covering all five rotations from your own duty experiences." />
              </div>
            </div>
            <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
              <div className="content-card-header bg-light"><h6 className="mb-0">Recommendations</h6></div>
              <div className="p-3 p-lg-4">
                <div className="portfolio-fields-2">
                  <div>
                    <label className="portfolio-field-label">a. Students</label>
                    <textarea className="form-control portfolio-field-input" rows={4} value={form.rec_students} onChange={(e) => setFrontField('rec_students', e.target.value)} />
                  </div>
                  <div>
                    <label className="portfolio-field-label">b. Internship Program</label>
                    <textarea className="form-control portfolio-field-input" rows={4} value={form.rec_program} onChange={(e) => setFrontField('rec_program', e.target.value)} />
                  </div>
                  <div>
                    <label className="portfolio-field-label">c. Curriculum</label>
                    <textarea className="form-control portfolio-field-input" rows={4} value={form.rec_curriculum} onChange={(e) => setFrontField('rec_curriculum', e.target.value)} />
                  </div>
                  <div>
                    <label className="portfolio-field-label">d. Host Training Establishments</label>
                    <textarea className="form-control portfolio-field-input" rows={4} value={form.rec_hte} onChange={(e) => setFrontField('rec_hte', e.target.value)} />
                  </div>
                </div>
              </div>
            </div>
            {saveBar}
          </form>
        )}

        {activeTab === 'appendices' && (
          <div className="bg-white p-4 border rounded shadow-sm mb-4">
            <div className="portfolio-appendix-group mb-0">
              <h6 className="portfolio-appendix-group-title">Shared appendices</h6>
              <p className="text-muted small">Per-rotation letters, journals, and evaluations are uploaded on each Rotation tab.</p>
              {renderUploadGroup(GLOBAL_UPLOADS, globalDocType)}
            </div>
          </div>
        )}
      </div>

      <ConfirmModal
        open={!!deletingItem}
        title="Delete Uploaded File?"
        message={`Are you sure you want to delete "${deletingItem?.label || deletingItem?.file_name || 'this file'}"? It will be removed from your portfolio attachments.`}
        confirmLabel="Delete File"
        cancelLabel="Cancel"
        variant="danger"
        loading={isDeleting}
        onCancel={() => setDeletingItem(null)}
        onConfirm={handleConfirmDeleteFile}
      />
    </Layout>
  )
}

export default NursingPortfolioBuilder

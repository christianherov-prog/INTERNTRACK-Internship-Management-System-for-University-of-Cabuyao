import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import PageError from '../../../components/PageError'
import api from '../../../services/api'
import { AuthenticatedFileLink } from '../../../components/AuthenticatedFile'
import ConfirmModal from '../../../components/modals/ConfirmModal'
import {
  PSY_COURSE,
  PSY_ROTATIONS,
  emptyPsychologyFields,
  PRE_INTERNSHIP_UPLOADS,
  INTERNSHIP_UPLOADS,
  POST_INTERNSHIP_UPLOADS,
  APPENDIX_UPLOADS,
  extrasForRotation,
  docType,
} from './psychologyPortfolioStructure'

function PsychologyPortfolioBuilder() {
  const [data, setData] = useState(null)
  const [loadError, setLoadError] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [activeTab, setActiveTab] = useState('front')
  const [form, setForm] = useState(emptyPsychologyFields())
  const [deletingItem, setDeletingItem] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const fetchPortfolio = () => {
    api.get('/student/portfolio')
      .then((res) => {
        setLoadError(null)
        setData(res.data)
        const saved = res.data.internship?.portfolio?.custom_fields?.psychology?.rotations
        setForm({
          ...emptyPsychologyFields(),
          1: { ...emptyPsychologyFields()[1], ...(saved?.[1] || saved?.['1'] || {}) },
          2: { ...emptyPsychologyFields()[2], ...(saved?.[2] || saved?.['2'] || {}) },
          3: { ...emptyPsychologyFields()[3], ...(saved?.[3] || saved?.['3'] || {}) },
        })
      })
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load portfolio.')
      })
  }

  useEffect(() => { fetchPortfolio() }, [])

  const setRotationField = (rotationId, key, value) => {
    setForm((prev) => ({
      ...prev,
      [rotationId]: { ...prev[rotationId], [key]: value },
    }))
  }

  const handleSave = async (e) => {
    e?.preventDefault?.()
    setSaving(true)
    setMessage(null)
    try {
      await api.post('/student/portfolio', {
        custom_fields: { psychology: { rotations: form } },
      })
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
    : (user?.program?.name || profile?.program?.name || 'Bachelor of Science in Psychology')
  const collegeName = typeof user?.department === 'object'
    ? (user.department?.name || 'College of Arts and Sciences')
    : (user?.department || profile?.department?.name || 'College of Arts and Sciences')
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
            <input
              type="file"
              id={`upload-${type}`}
              className="d-none"
              accept="image/*,.pdf,.png,.jpg,.jpeg,.webp,.gif"
              onChange={(e) => handleFileUpload(e, type)}
            />
            <label htmlFor={`upload-${type}`} className="btn btn-outline-primary btn-sm w-100 portfolio-upload-btn mb-0">
              <i className="fa fa-upload me-1"></i>{items.length > 0 ? 'Upload More' : 'Upload'}
            </label>
          </div>
        </div>
      </div>
    )
  }

  const renderUploadGroup = (rotationId, items) => (
    <div className="portfolio-upload-grid">
      {items.map((item) => renderFileList(docType(rotationId, item.suffix), item.label, item.tip))}
    </div>
  )

  const renderEvaluationRow = (formType, formTitle) => {
    const ev = data?.internship?.evaluations?.find((e) => e.form_type === formType)
    let statusBadge = <span className="badge bg-secondary">Not Yet Started</span>
    if (ev) {
      if (ev.status === 'completed') statusBadge = <span className="badge bg-success">Completed</span>
      else if (ev.status === 'pending') statusBadge = <span className="badge bg-warning text-dark">In Progress</span>
    }
    return (
      <tr key={formType}>
        <td>{formTitle}</td>
        <td className="text-center">{statusBadge}</td>
      </tr>
    )
  }

  const uploadTypesForRotation = (rotationId) => [
    ...PRE_INTERNSHIP_UPLOADS,
    ...INTERNSHIP_UPLOADS,
    ...POST_INTERNSHIP_UPLOADS,
    ...APPENDIX_UPLOADS,
    ...extrasForRotation(rotationId),
  ].map((item) => docType(rotationId, item.suffix))

  const textKeys = ['hte_name', 'hte_address', 'hte_profile', 'narrative', 'rec_students', 'rec_program', 'rec_curriculum', 'rec_hte']
  const textDone = PSY_ROTATIONS.reduce((n, r) => n + textKeys.filter((k) => (form[r.id]?.[k] || '').trim()).length, 0)
  const textTotal = PSY_ROTATIONS.length * textKeys.length
  const allUploadTypes = PSY_ROTATIONS.flatMap((r) => uploadTypesForRotation(r.id))
  const uploadsDone = allUploadTypes.filter((t) => photos.some((ph) => matchesType(ph, t))).length
  const uploadsTotal = allUploadTypes.length

  const tabs = [
    { key: 'front', label: 'Front Matter', icon: 'fa-book-open' },
    { key: 'r1', label: 'Rotation 1', icon: 'fa-rotate' },
    { key: 'r2', label: 'Rotation 2', icon: 'fa-rotate' },
    { key: 'r3', label: 'Rotation 3', icon: 'fa-rotate' },
  ]

  const renderRotation = (rotationId) => {
    const fields = form[rotationId] || emptyPsychologyFields()[rotationId]
    const extras = extrasForRotation(rotationId)
    const rotationMeta = PSY_ROTATIONS.find((r) => r.id === rotationId)
    return (
      <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
        <div className="d-flex flex-column gap-4">
          <div className="alert alert-light border mb-0">
            <strong>{rotationMeta.title}</strong>
            <span className="text-muted small d-block mt-1">Upload scans and write narratives for this rotation only. Content is saved to your account.</span>
          </div>

          <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
            <div className="content-card-header bg-light"><h6 className="mb-0">Pre-Internship Phase</h6></div>
            <div className="p-3 p-lg-4">
              <div className="portfolio-hte-row mb-3">
                <div>
                  <label className="portfolio-field-label">Host Training Establishment</label>
                  <input className="form-control portfolio-field-input" value={fields.hte_name} onChange={(e) => setRotationField(rotationId, 'hte_name', e.target.value)} placeholder="HTE / cooperating site name" />
                </div>
                <div>
                  <label className="portfolio-field-label">HTE Address</label>
                  <input className="form-control portfolio-field-input" value={fields.hte_address} onChange={(e) => setRotationField(rotationId, 'hte_address', e.target.value)} placeholder="Address" />
                </div>
              </div>
              <div className="mb-3">
                <label className="portfolio-field-label">Host Training Establishment Profile</label>
                <textarea className="form-control portfolio-field-input" rows={4} value={fields.hte_profile} onChange={(e) => setRotationField(rotationId, 'hte_profile', e.target.value)} placeholder="Describe this rotation's host establishment. Do not paste another student's write-up." />
              </div>
              {renderUploadGroup(rotationId, PRE_INTERNSHIP_UPLOADS)}
            </div>
          </div>

          <div className="content-card portfolio-tips-card">
            <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Pre-Internship Writing Tips</h6></div>
            <div className="p-3 p-lg-4">
              <ul className="portfolio-tips-list">
                <li><strong>HTE Profile:</strong> Use your own placement — history, services, and how psychology is practiced on site.</li>
                <li><strong>Letters and FO-29:</strong> Upload the signed scans you received for this rotation.</li>
              </ul>
            </div>
          </div>

          <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
            <div className="content-card-header bg-light"><h6 className="mb-0">Internship Phase</h6></div>
            <div className="p-3 p-lg-4">
              <div className="mb-3">
                <label className="portfolio-field-label">Narrative and Insights of Internship Learning Experiences</label>
                <textarea className="form-control portfolio-field-input" rows={6} value={fields.narrative} onChange={(e) => setRotationField(rotationId, 'narrative', e.target.value)} placeholder="Write your own narrative and insights for this rotation." />
              </div>
              {renderUploadGroup(rotationId, INTERNSHIP_UPLOADS)}
            </div>
          </div>

          <div className="content-card portfolio-tips-card">
            <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Internship Phase Tips</h6></div>
            <div className="p-3 p-lg-4">
              <ul className="portfolio-tips-list">
                <li><strong>Narrative:</strong> Ground insights in duties you actually performed this rotation.</li>
                <li><strong>FO-25.5 / FO-31 / FO-30:</strong> Upload accomplished official forms, not blank templates.</li>
              </ul>
            </div>
          </div>

          <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
            <div className="content-card-header bg-light"><h6 className="mb-0">Post-Internship Phase</h6></div>
            <div className="p-3 p-lg-4">
              <p className="portfolio-field-label mb-2">Recommendations (Students, Internship Program, Curriculum, HTE)</p>
              <div className="portfolio-fields-2">
                <div>
                  <label className="portfolio-field-label">Students</label>
                  <textarea className="form-control portfolio-field-input" rows={3} value={fields.rec_students} onChange={(e) => setRotationField(rotationId, 'rec_students', e.target.value)} />
                </div>
                <div>
                  <label className="portfolio-field-label">Internship Program</label>
                  <textarea className="form-control portfolio-field-input" rows={3} value={fields.rec_program} onChange={(e) => setRotationField(rotationId, 'rec_program', e.target.value)} />
                </div>
                <div>
                  <label className="portfolio-field-label">Curriculum</label>
                  <textarea className="form-control portfolio-field-input" rows={3} value={fields.rec_curriculum} onChange={(e) => setRotationField(rotationId, 'rec_curriculum', e.target.value)} />
                </div>
                <div>
                  <label className="portfolio-field-label">HTE</label>
                  <textarea className="form-control portfolio-field-input" rows={3} value={fields.rec_hte} onChange={(e) => setRotationField(rotationId, 'rec_hte', e.target.value)} />
                </div>
              </div>
              <div className="mt-3">{renderUploadGroup(rotationId, POST_INTERNSHIP_UPLOADS)}</div>
              <div className="table-responsive mt-4">
                <table className="table table-bordered table-hover bg-white mb-0" style={{ fontSize: '0.9rem' }}>
                  <thead className="table-light">
                    <tr><th>Live evaluation (if submitted in INTERNTRACK)</th><th className="text-center">Status</th></tr>
                  </thead>
                  <tbody>
                    {renderEvaluationRow('FO-22', 'Internship Host Training Establishment Evaluation Form (PNC:AA-FO-22)')}
                    {renderEvaluationRow('FO-23', 'Internship Program Evaluation Form (PNC:AA-FO-23)')}
                    {renderEvaluationRow('FO-24', 'Student Intern Performance Evaluation Form (PNC:AA-FO-24)')}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div className="content-card portfolio-tips-card">
            <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Post-Internship Tips</h6></div>
            <div className="p-3 p-lg-4">
              <ul className="portfolio-tips-list">
                <li>Write four distinct recommendation areas; keep them specific to this rotation.</li>
                <li>Still upload signed FO-22 / FO-23 / FO-24 scans even if the live evaluation status is shown above.</li>
              </ul>
            </div>
          </div>

          <div className="portfolio-appendix-group">
            <h6 className="portfolio-appendix-group-title">Appendices</h6>
            {renderUploadGroup(rotationId, APPENDIX_UPLOADS)}
          </div>

          {extras.length > 0 && (
            <div className="portfolio-appendix-group">
              <h6 className="portfolio-appendix-group-title">
                {rotationId === 1 ? 'Rotation 1 additional certificates' : 'Rotation 2 clinical extra'}
              </h6>
              {renderUploadGroup(rotationId, extras)}
            </div>
          )}
        </div>
        <div className="portfolio-form-actions">
          <button type="submit" className="btn btn-success px-5" disabled={saving}>
            {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Text Fields</>}
          </button>
        </div>
      </form>
    )
  }

  if (loadError) {
    return (
      <Layout title="My Portfolio" subtitle="BS Psychology · PSE 106" icon="fa-folder" bodyClass="student-page">
        <PageError message={loadError} onRetry={fetchPortfolio} />
      </Layout>
    )
  }

  if (!data) {
    return (
      <Layout title="My Portfolio" subtitle="BS Psychology · PSE 106" icon="fa-folder" bodyClass="student-page">
        <div className="text-center py-5 mt-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      </Layout>
    )
  }

  return (
    <Layout title="My Portfolio" subtitle="BS Psychology · PSE 106" icon="fa-folder" bodyClass="student-page">
      <div className="portfolio-builder">
        <div className="content-card mb-0">
          <div className="p-3 p-lg-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div className="fw-bold">{studentName}</div>
              <div className="text-muted small">{programName} · {PSY_COURSE}</div>
              <div className="text-muted small">{collegeName}</div>
            </div>
            <div className="d-flex flex-wrap gap-3">
              <div><div className="fw-bold lh-1">{textDone}/{textTotal}</div><div className="text-muted" style={{ fontSize: '0.72rem' }}>TEXT FIELDS</div></div>
              <div><div className="fw-bold lh-1">{uploadsDone}/{uploadsTotal}</div><div className="text-muted" style={{ fontSize: '0.72rem' }}>UPLOADS</div></div>
              <div><div className="fw-bold lh-1">{Math.round(((textDone + uploadsDone) / Math.max(textTotal + uploadsTotal, 1)) * 100)}%</div><div className="text-muted" style={{ fontSize: '0.72rem' }}>COMPLETION</div></div>
            </div>
            <div className="d-flex gap-2">
              <Link to="/student/portfolio/preview" className="btn btn-primary btn-sm px-3 shadow-sm">
                <i className="fa fa-eye me-1"></i>Preview Portfolio
              </Link>
            </div>
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
              <button
                key={tab.key}
                className={`placement-tab-btn${activeTab === tab.key ? ' active' : ''}`}
                onClick={(e) => { e.preventDefault(); setActiveTab(tab.key) }}
              >
                <i className={`fa ${tab.icon}`}></i>
                <span>{tab.label}</span>
              </button>
            ))}
          </div>
        </div>

        {activeTab === 'front' && (
          <div className="bg-white p-4 border rounded shadow-sm mb-4">
            <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
              <div className="content-card-header bg-light"><h6 className="mb-0">Front matter</h6></div>
              <div className="p-3 p-lg-4">
                <p className="mb-2">The printed cover and institutional pages use your live student record. No extra typing is required here.</p>
                <ul className="portfolio-tips-list mb-0">
                  <li><strong>Cover:</strong> Internship Portfolio, {studentName || 'your name'}, {programName}, {PSY_COURSE}, month/year from your internship dates, {collegeName}.</li>
                  <li><strong>Included in preview:</strong> University Mission, Vision, Quality Policy, Core Values, and Quality Objectives.</li>
                  <li><strong>Table of Contents:</strong> generated per rotation from the sections below.</li>
                </ul>
                <div className="mt-3">
                  <Link to="/student/portfolio/preview" className="btn btn-outline-primary btn-sm">
                    <i className="fa fa-eye me-1"></i>Open cover preview
                  </Link>
                </div>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'r1' && renderRotation(1)}
        {activeTab === 'r2' && renderRotation(2)}
        {activeTab === 'r3' && renderRotation(3)}
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

export default PsychologyPortfolioBuilder

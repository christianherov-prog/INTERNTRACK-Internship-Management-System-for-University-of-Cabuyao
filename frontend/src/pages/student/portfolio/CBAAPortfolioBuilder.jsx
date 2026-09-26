import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import Layout from '../../../components/Layout'
import PageError from '../../../components/PageError'
import InternTrackLoader from '../../../components/InternTrackLoader'
import api from '../../../services/api'
import { cacheGet, cacheSet, invalidateOfficialFormCaches } from '../../../utils/pageCache'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../../components/AuthenticatedFile'
import ConfirmModal from '../../../components/modals/ConfirmModal'
import { useConfirm } from '../../../contexts/ConfirmContext'
import { useToast } from '../../../contexts/ToastContext'
import { UPLOAD_MAX_MB } from '../../../config/uploads'
import { uploadErrorMessage, validateUploadFiles } from '../../../utils/uploadValidation'
import { displayLabel } from '../../../utils/displayLabel'
import { plainTextToHtml } from '../../../utils/sanitizeHtml'
import RichTextEditor from '../../../components/portfolio/RichTextEditor'
import {
  APPENDICES,
  BUILDER_TABS,
  COMPANY_EXTRA_FIELDS,
  COMPANY_LOGO_TYPE,
  CBAA_COLLEGE,
  HTE_PHOTO_TYPE,
  STATUS,
  TEXT_SECTIONS,
  TRAINING_PLAN_ALIASES,
  appendixLetter,
  appendixUploadType,
  authoritativeAttendance,
  buildPortfolioState,
  buildToc,
  findDocs,
  groupStatus,
  htmlHasText,
  isStudentUpload,
} from './cbaaPortfolioStructure'
import '../../../styles/cbaa-portfolio.css'

const AUTOSAVE_MS = 2500
const IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'

const EMPTY_COMPANY = {
  company_name: '',
  company_address: '',
  company_vision: '',
  company_mission: '',
  extras: Object.fromEntries(COMPANY_EXTRA_FIELDS.map((f) => [f.key, ''])),
}

const isPdf = (doc) => String(doc?.mime_type || '').includes('pdf') || /\.pdf$/i.test(String(doc?.file_path || doc?.file_name || ''))
const escapeHtml = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

function StatusPill({ status, compact = false }) {
  const s = STATUS[status] || STATUS.incomplete
  return (
    <span className={`cbaa-pill tone-${s.tone}`} title={s.label}>
      <i className={`fa ${s.icon}`}></i>
      {!compact && <span>{s.short || s.label}</span>}
    </span>
  )
}

/** Card with the same look as the other colleges' chapter cards; title comes from the shared TOC. */
function ChapterCard({ icon, title, status, actions, children }) {
  return (
    <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
      <div className="content-card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 className="mb-0"><i className={`fa ${icon} me-2 text-primary`}></i>{title}</h6>
        <div className="d-flex align-items-center gap-2 flex-wrap">
          {status && <StatusPill status={status} />}
          {actions}
        </div>
      </div>
      <div className="p-3 p-lg-4">{children}</div>
    </div>
  )
}

function UploadLabel({ type, label, accept = IMAGE_ACCEPT, multiple = false, busy, onFiles, className = 'btn btn-outline-primary btn-sm w-100 portfolio-upload-btn mb-0' }) {
  const inputId = `cbaa-up-${type}`
  return (
    <>
      <input
        id={inputId}
        type="file"
        className="d-none"
        accept={accept}
        multiple={multiple}
        disabled={busy}
        onChange={(e) => { onFiles(e.target.files, type); e.target.value = '' }}
      />
      <label htmlFor={inputId} className={`${className}${busy ? ' disabled' : ''}`}>
        {busy ? <><i className="fa fa-spinner fa-spin me-1"></i>Uploading...</> : <><i className="fa fa-upload me-1"></i>{label}</>}
      </label>
    </>
  )
}

/** Upload tile in the same style as the other colleges' appendix tiles. */
function FileTile({ title, status, note, docs, upload, onRemove }) {
  return (
    <div className="content-card portfolio-upload-tile">
      <div className="portfolio-upload-tile-head d-flex align-items-start justify-content-between gap-2">
        <h6 className="mb-0">{title}</h6>
        {status && <StatusPill status={status} compact />}
      </div>
      <div className="portfolio-upload-tile-body">
        <div className="portfolio-upload-tile-content">
          {docs.length > 0 ? (
            <div className="portfolio-upload-files">
              {docs.map((item) => (
                <div key={item.id} className="portfolio-upload-file-row">
                  <div className="text-truncate flex-grow-1 me-2 small">
                    {isPdf(item) ? <i className="fa fa-file-pdf text-danger me-1"></i> : <i className="fa fa-image text-primary me-1"></i>}
                    {item.label || item.file_name || 'Uploaded'}
                  </div>
                  <div className="d-flex gap-1 flex-shrink-0">
                    <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }} title="View">
                      <i className="fa fa-eye"></i>
                    </AuthenticatedFileLink>
                    {isStudentUpload(item) && (
                      <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} title="Remove" aria-label="Remove file" onClick={() => onRemove(item)}>
                        <i className="fa fa-trash"></i>
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="portfolio-upload-empty">{status === 'missing' || !STATUS[status] ? 'No files yet' : STATUS[status].label}</p>
          )}
          {note && <p className="cbaa-tile-note">{note}</p>}
        </div>
        {upload && <div className="portfolio-upload-btn-wrap">{upload}</div>}
      </div>
    </div>
  )
}

function CBAAPortfolioBuilder() {
  const confirm = useConfirm()
  const toast = useToast()
  const navigate = useNavigate()

  const [data, setData] = useState(() => cacheGet('student:portfolio') ?? null)
  const [loadError, setLoadError] = useState(null)
  const [sections, setSections] = useState({})
  const [company, setCompany] = useState(EMPTY_COMPANY)
  const [activeTab, setActiveTab] = useState('bio_sketch')
  const [saveState, setSaveState] = useState('idle')
  const [lastSavedAt, setLastSavedAt] = useState(null)
  const [dirtyTick, setDirtyTick] = useState(0)
  const [uploadingType, setUploadingType] = useState(null)
  const [deletingItem, setDeletingItem] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [captions, setCaptions] = useState({})

  const hydrated = useRef(false)
  const sectionsRef = useRef(sections)
  const companyRef = useRef(company)
  const dirtySections = useRef(new Set())
  const companyDirty = useRef(false)
  const savingRef = useRef(false)
  const historyRef = useRef('')

  sectionsRef.current = sections
  companyRef.current = company

  const hasUnsaved = () => dirtySections.current.size > 0 || companyDirty.current

  const hydrate = (payload) => {
    const p = payload?.internship?.portfolio || {}
    const hte = payload?.internship?.company || {}
    const saved = p.sections || {}
    const next = {}
    Object.keys(TEXT_SECTIONS).forEach((key) => { next[key] = saved[key]?.content || '' })
    historyRef.current = p.company_history || ''

    // Prefill the company profile from the HTE record when the student has not written one yet.
    if (!next.company_profile && p.company_history) {
      next.company_profile = plainTextToHtml(p.company_history)
      dirtySections.current.add('company_profile')
    }

    const extrasSaved = p.custom_fields?.cbaa?.company || {}
    setSections(next)
    setCompany({
      company_name: p.company_name || '',
      company_address: p.company_address || '',
      company_vision: p.company_vision || '',
      company_mission: p.company_mission || '',
      extras: {
        ...EMPTY_COMPANY.extras,
        industry: hte.industry || '',
        contact_person: hte.contact_person || payload?.identity?.supervisor_name || '',
        contact_number: hte.contact_number || '',
        email: hte.contact_email || '',
        ...Object.fromEntries(Object.entries(extrasSaved).filter(([, v]) => v)),
      },
    })
    if (dirtySections.current.size) setDirtyTick((t) => t + 1)
  }

  const fetchPortfolio = useCallback(() => api.get('/student/portfolio')
    .then((res) => {
      setLoadError(null)
      cacheSet('student:portfolio', res.data)
      setData(res.data)
      if (!hydrated.current) {
        hydrated.current = true
        hydrate(res.data)
      }
      return res.data
    })
    .catch((err) => {
      setLoadError(err.response?.data?.message || 'Failed to load portfolio.')
    }), [])

  useEffect(() => { fetchPortfolio() }, [fetchPortfolio])

  const saveDraft = useCallback(async ({ silent = false } = {}) => {
    if (savingRef.current) return
    if (!hasUnsaved()) {
      if (!silent) toast.success('Everything is already saved.')
      return
    }
    savingRef.current = true
    const keys = [...dirtySections.current]
    const companyWasDirty = companyDirty.current
    dirtySections.current = new Set()
    companyDirty.current = false
    setSaveState('saving')
    try {
      if (keys.length) {
        await api.put('/student/portfolio/sections', {
          sections: Object.fromEntries(keys.map((k) => [k, sectionsRef.current[k] || ''])),
        })
      }
      if (companyWasDirty) {
        const c = companyRef.current
        await api.post('/student/portfolio', {
          company_name: c.company_name,
          company_address: c.company_address,
          company_vision: c.company_vision,
          company_mission: c.company_mission,
          company_history: historyRef.current,
          custom_fields: { cbaa: { company: c.extras } },
        })
      }
      setSaveState('saved')
      setLastSavedAt(new Date())
      if (!silent) toast.success('Portfolio draft saved.')
      fetchPortfolio()
    } catch (err) {
      keys.forEach((k) => dirtySections.current.add(k))
      if (companyWasDirty) companyDirty.current = true
      setSaveState('error')
      toast.error(err.response?.data?.message || 'Could not save your draft. Your changes are kept on this page — try again.')
    } finally {
      savingRef.current = false
      if (hasUnsaved()) setDirtyTick((t) => t + 1)
    }
  }, [fetchPortfolio, toast])

  // Debounced autosave for editable text.
  useEffect(() => {
    if (!dirtyTick) return undefined
    const timer = setTimeout(() => saveDraft({ silent: true }), AUTOSAVE_MS)
    return () => clearTimeout(timer)
  }, [dirtyTick, saveDraft])

  useEffect(() => {
    const warn = (e) => {
      if (!hasUnsaved()) return
      e.preventDefault()
      e.returnValue = ''
    }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [])

  const markDirty = () => {
    setSaveState('dirty')
    setDirtyTick((t) => t + 1)
  }
  const setSection = (key, html) => {
    setSections((prev) => ({ ...prev, [key]: html }))
    dirtySections.current.add(key)
    markDirty()
  }
  const setCompanyField = (key, value) => {
    setCompany((prev) => ({ ...prev, [key]: value }))
    companyDirty.current = true
    markDirty()
  }
  const setCompanyExtra = (key, value) => {
    setCompany((prev) => ({ ...prev, extras: { ...prev.extras, [key]: value } }))
    companyDirty.current = true
    markDirty()
  }

  // ---------- Derived data ----------
  const internship = data?.internship
  const portfolio = internship?.portfolio
  const photos = portfolio?.photos || []
  const identity = data?.identity || {}
  const profile = data?.user?.student_profile || data?.user?.studentProfile
  const programName = displayLabel(identity.program || profile?.program)
  const programCode = profile?.program?.code || data?.user?.program_code || ''
  const journals = internship?.journals || []
  const attendance = internship?.attendance || []
  const recordStatus = internship?.record_status || {}

  const state = useMemo(() => buildPortfolioState({
    sections, company, photos, recordStatus, evaluations: internship?.evaluations, journals, attendance, programCode, programName,
  }), [sections, company, photos, recordStatus, internship?.evaluations, journals, attendance, programCode, programName])

  const nodeById = useMemo(() => {
    const map = {}
    const walk = (list) => list.forEach((n) => {
      map[n.id] = n
      if (n.children) walk(n.children)
    })
    walk(state.nodes)
    return map
  }, [state.nodes])

  const tpTitle = nodeById.training_plan?.fullTitle
  const toc = useMemo(() => buildToc({
    trainingPlanTitle: tpTitle,
    journals: [...journals].sort((a, b) => (a.week_number || 0) - (b.week_number || 0)),
  }), [tpTitle, journals])
  const tocLabel = (id) => toc.find((r) => r.id === id)?.label || ''

  const tabStatus = (key) => {
    if (key === 'toc') return 'auto'
    if (key === 'appendices') return state.appendixDone === APPENDICES.length ? 'complete' : 'incomplete'
    const node = nodeById[key]
    if (!node) return 'incomplete'
    return node.children ? groupStatus(node.children) : node.status
  }

  const rowStatus = (row) => {
    if (row.week) return 'auto'
    if (row.appendixKey) return state.appendices.find((a) => a.key === row.appendixKey)?.status
    return tabStatus(row.id)
  }

  const htePhotos = useMemo(() => findDocs(photos, [HTE_PHOTO_TYPE]), [photos])
  const logoDocs = useMemo(() => findDocs(photos, [COMPANY_LOGO_TYPE, 'logo']), [photos])

  const textKeys = Object.keys(TEXT_SECTIONS)
  const textDone = textKeys.filter((k) => htmlHasText(sections[k])).length

  // ---------- Files ----------
  const uploadFiles = async (fileList, type) => {
    const files = Array.from(fileList || [])
    if (!files.length) return
    for (const file of files) {
      const image = /^image\/(jpeg|png|webp)$/.test(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name)
      if (!image) {
        toast.error(`${file.name}: upload a JPG, PNG, or WEBP image only.`)
        return
      }
    }
    const sizeCheck = validateUploadFiles(files)
    if (!sizeCheck.ok) {
      toast.error(sizeCheck.error)
      return
    }
    setUploadingType(type)
    try {
      for (const file of files) {
        const form = new FormData()
        form.append('file', file)
        form.append('type', type)
        form.append('label', file.name.replace(/\.[^.]+$/, ''))
        await api.post('/student/portfolio/photos', form)
      }
      toast.success(files.length > 1 ? `${files.length} files uploaded.` : 'File uploaded.')
      invalidateOfficialFormCaches()
      await fetchPortfolio()
    } catch (err) {
      toast.error(uploadErrorMessage(err))
      fetchPortfolio()
    } finally {
      setUploadingType(null)
    }
  }

  const handleConfirmDelete = async () => {
    if (!deletingItem) return
    setIsDeleting(true)
    try {
      await api.delete(`/student/portfolio/photos/${deletingItem.id}`)
      invalidateOfficialFormCaches()
      await fetchPortfolio()
      setDeletingItem(null)
    } catch {
      toast.error('Failed to delete file.')
    } finally {
      setIsDeleting(false)
    }
  }

  const saveCaption = async (doc) => {
    if (captions[doc.id] === undefined) return
    const value = captions[doc.id].trim()
    if (value === (doc.label || '')) return
    if (!value) {
      toast.error('Caption cannot be empty.')
      return
    }
    try {
      await api.patch(`/student/portfolio/photos/${doc.id}`, { label: value })
      await fetchPortfolio()
      setCaptions((prev) => {
        const next = { ...prev }
        delete next[doc.id]
        return next
      })
      toast.success('Caption saved.')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Could not save caption.')
    }
  }

  const movePhoto = async (index, delta) => {
    const target = index + delta
    if (target < 0 || target >= htePhotos.length) return
    const ids = htePhotos.map((p) => p.id)
    ;[ids[index], ids[target]] = [ids[target], ids[index]]
    setData((prev) => {
      if (!prev) return prev
      const all = prev.internship.portfolio.photos
      const byId = Object.fromEntries(all.map((p) => [p.id, p]))
      const others = all.filter((p) => !ids.includes(p.id))
      const reordered = ids.map((id, i) => ({ ...byId[id], sort_order: i + 1 }))
      return { ...prev, internship: { ...prev.internship, portfolio: { ...prev.internship.portfolio, photos: [...reordered, ...others] } } }
    })
    try {
      await api.post('/student/portfolio/photos/reorder', { ids })
    } catch (err) {
      toast.error(err.response?.data?.message || 'Could not save the new order.')
      fetchPortfolio()
    }
  }

  const insertProfileDetails = async () => {
    const name = escapeHtml(identity.student_name_natural || identity.student_name || 'Student')
    const yr = profile?.year_level ? `${profile.year_level}${['th', 'st', 'nd', 'rd'][profile.year_level] || 'th'}-year ` : ''
    const starter = [
      `<p>I am <strong>${name}</strong>, a ${yr}student of ${escapeHtml(programName || 'the program')}${identity.section ? ` (${escapeHtml(identity.section)})` : ''} at the University of Cabuyao, ${CBAA_COLLEGE}.</p>`,
      identity.company_name ? `<p>I completed my internship at <strong>${escapeHtml(identity.company_name)}</strong>${identity.training_period ? ` from ${escapeHtml(identity.training_period)}` : ''}${identity.target_hours ? `, rendering the required ${escapeHtml(identity.target_hours)} hours` : ''}.</p>` : '',
      '<p>[Describe your personal background, education, skills, organizations, achievements, and career goals.]</p>',
    ].join('')
    const current = sections.bio_sketch || ''
    if (current && !(await confirm({ message: 'Add your profile details to the end of your current biographical sketch?' }))) return
    setSection('bio_sketch', current + starter)
  }

  const goPreview = async (e) => {
    if (!hasUnsaved()) return
    e.preventDefault()
    await saveDraft({ silent: true })
    navigate('/student/portfolio/preview')
  }

  // ---------- Render helpers ----------
  const upload = (type, label, opts = {}) => (
    <UploadLabel type={type} label={label} busy={uploadingType === type} onFiles={uploadFiles} {...opts} />
  )

  const editorCard = (key, icon, extra = null) => (
    <ChapterCard key={key} icon={icon} title={tocLabel(key)} status={nodeById[key]?.status} actions={extra}>
      <RichTextEditor id={`rte-${key}`} value={sections[key] || ''} placeholder={TEXT_SECTIONS[key].placeholder} onChange={(html) => setSection(key, html)} minHeight={280} />
    </ChapterCard>
  )

  const tipsCard = (title, tips) => (
    <div className="content-card portfolio-tips-card">
      <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>{title}</h6></div>
      <div className="p-3 p-lg-4">
        <ul className="portfolio-tips-list">{tips.map((t, i) => <li key={i}>{t}</li>)}</ul>
      </div>
    </div>
  )

  const saveBar = (
    <div className="portfolio-form-actions">
      <button type="button" className="btn btn-success px-5" onClick={() => saveDraft()} disabled={saveState === 'saving'}>
        {saveState === 'saving' ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Draft</>}
      </button>
    </div>
  )

  // ---------- Tabs ----------
  const renderTab = () => {
    switch (activeTab) {
      case 'bio_sketch':
        return (
          <>
            {editorCard('bio_sketch', 'fa-user', (
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={insertProfileDetails}>
                <i className="fa fa-id-card me-1"></i>Insert profile details
              </button>
            ))}
            {tipsCard('Biographical Sketch Guidelines', [
              <><strong>Content:</strong> Introduce your background, education, skills, organizations, achievements, and career goals.</>,
              <><strong>Profile details:</strong> &quot;Insert profile details&quot; adds your name, program, section, and host company from your InternTrack records.</>,
              <><strong>Formatting:</strong> Use headings, bold, lists, and alignment from the toolbar. The preview keeps your formatting.</>,
            ])}
            {saveBar}
          </>
        )

      case 'acknowledgment':
        return (
          <>
            {editorCard('acknowledgment', 'fa-hands-praying')}
            {tipsCard('Acknowledgment Guidelines', [
              <><strong>Who to thank:</strong> Your host training establishment, supervisor, internship adviser, coordinator, family, and classmates.</>,
              <><strong>Tone:</strong> Keep it sincere and professional; one page is usually enough.</>,
            ])}
            {saveBar}
          </>
        )

      case 'toc':
        return (
          <ChapterCard icon="fa-list-ol" title={tocLabel('toc')} status="auto" actions={(
            <Link to="/student/portfolio/preview" className="btn btn-outline-primary btn-sm" onClick={goPreview}><i className="fa fa-eye me-1"></i>View with page numbers</Link>
          )}>
            <p className="portfolio-panel-text mb-3">
              Generated automatically. These are exactly the entries printed in the Portfolio Preview; page numbers are calculated
              there and update whenever your content changes. Click an entry to edit it.
            </p>
            <div className="cbaa-toc-builder">
              {toc.map((row) => (
                <button
                  key={row.id}
                  type="button"
                  className={`cbaa-toc-builder-row level-${row.level || 0}${row.bold ? ' bold' : ''}`}
                  onClick={() => setActiveTab(row.tab)}
                >
                  <span className="text-truncate">{row.label}</span>
                  <StatusPill status={rowStatus(row) || 'incomplete'} compact />
                </button>
              ))}
            </div>
          </ChapterCard>
        )

      case 'hte':
        return (
          <>
            <ChapterCard icon="fa-building" title={tocLabel('company_profile')} status={nodeById.company_profile?.status}>
              <div className="portfolio-hte-row mb-3">
                <div>
                  <label className="portfolio-field-label" htmlFor="cbaa-cname">Host Company Name <span className="text-danger">*</span></label>
                  <input id="cbaa-cname" maxLength={255} className="form-control portfolio-field-input" value={company.company_name} onChange={(e) => setCompanyField('company_name', e.target.value)} placeholder="Company Name" />
                  <div className="text-muted" style={{ fontSize: '11px', marginTop: '3px' }}>Appears on the title page &amp; header.</div>
                </div>
                <div>
                  <label className="portfolio-field-label" htmlFor="cbaa-caddr">Host Company Address <span className="text-danger">*</span></label>
                  <input id="cbaa-caddr" maxLength={255} className="form-control portfolio-field-input" value={company.company_address} onChange={(e) => setCompanyField('company_address', e.target.value)} placeholder="Company Address" />
                  <div className="text-muted" style={{ fontSize: '11px', marginTop: '3px' }}>Prefilled from your placement record.</div>
                </div>
              </div>
              <div className="portfolio-fields-2 mb-3">
                {COMPANY_EXTRA_FIELDS.map((f) => (
                  <div key={f.key}>
                    <label className="portfolio-field-label" htmlFor={`cbaa-x-${f.key}`}>{f.label}</label>
                    <input id={`cbaa-x-${f.key}`} maxLength={f.max} className="form-control portfolio-field-input" value={company.extras[f.key] || ''} onChange={(e) => setCompanyExtra(f.key, e.target.value)} />
                  </div>
                ))}
              </div>
              <div className="mb-3">
                <label className="portfolio-field-label" htmlFor="rte-company_profile">Company Background / Description <span className="text-danger">*</span></label>
                <RichTextEditor id="rte-company_profile" value={sections.company_profile || ''} placeholder={TEXT_SECTIONS.company_profile.placeholder} onChange={(html) => setSection('company_profile', html)} minHeight={200} />
              </div>
              <div className="portfolio-fields-2 mb-3">
                <div>
                  <label className="portfolio-field-label" htmlFor="cbaa-vision">Company Vision</label>
                  <textarea id="cbaa-vision" maxLength={10000} className="form-control portfolio-field-input" rows={3} value={company.company_vision} onChange={(e) => setCompanyField('company_vision', e.target.value)} placeholder="Company Vision" />
                </div>
                <div>
                  <label className="portfolio-field-label" htmlFor="cbaa-mission">Company Mission</label>
                  <textarea id="cbaa-mission" maxLength={10000} className="form-control portfolio-field-input" rows={3} value={company.company_mission} onChange={(e) => setCompanyField('company_mission', e.target.value)} placeholder="Company Mission" />
                </div>
              </div>
              <div className="portfolio-upload-grid">
                <FileTile
                  title="Company Logo (HTE)"
                  status={logoDocs.length ? 'complete' : 'incomplete'}
                  note="Authorized logo only. Shown on page headers."
                  docs={logoDocs}
                  onRemove={setDeletingItem}
                  upload={upload(COMPANY_LOGO_TYPE, logoDocs.length ? 'Replace' : 'Upload', { accept: IMAGE_ACCEPT })}
                />
              </div>
            </ChapterCard>

            <ChapterCard
              icon="fa-images"
              title={tocLabel('photos')}
              status={nodeById.photos?.status}
              actions={upload(HTE_PHOTO_TYPE, 'Add Photos', { accept: IMAGE_ACCEPT, multiple: true, className: 'btn btn-primary btn-sm mb-0' })}
            >
              {htePhotos.length === 0 ? (
                <p className="portfolio-upload-empty mb-0">No photos yet. Add photos of your workplace, tasks, and activities, then write a caption for each.</p>
              ) : (
                <div className="cbaa-gallery">
                  {htePhotos.map((photo, i) => (
                    <figure key={photo.id} className="cbaa-photo-card">
                      <div className="cbaa-photo-thumb">
                        <AuthenticatedFileImage path={photo.file_path} alt={photo.label || `Figure ${i + 1}`} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                        <span className="cbaa-photo-index">Figure {i + 1}</span>
                      </div>
                      <figcaption>
                        <label className="visually-hidden" htmlFor={`cap-${photo.id}`}>Caption for figure {i + 1}</label>
                        <textarea
                          id={`cap-${photo.id}`}
                          className="form-control form-control-sm"
                          rows={2}
                          maxLength={255}
                          placeholder="Caption (e.g., Assisting in the monthly inventory count)"
                          value={captions[photo.id] ?? photo.label ?? ''}
                          onChange={(e) => setCaptions((prev) => ({ ...prev, [photo.id]: e.target.value }))}
                          onBlur={() => saveCaption(photo)}
                        />
                        <div className="cbaa-photo-actions">
                          <button type="button" className="btn btn-light btn-sm" title="Move earlier" aria-label="Move earlier" disabled={i === 0} onClick={() => movePhoto(i, -1)}><i className="fa fa-arrow-up"></i></button>
                          <button type="button" className="btn btn-light btn-sm" title="Move later" aria-label="Move later" disabled={i === htePhotos.length - 1} onClick={() => movePhoto(i, 1)}><i className="fa fa-arrow-down"></i></button>
                          <AuthenticatedFileLink path={photo.file_path} className="btn btn-light btn-sm" title="Preview"><i className="fa fa-eye"></i></AuthenticatedFileLink>
                          <button type="button" className="btn btn-light btn-sm text-danger ms-auto" title="Remove" aria-label="Remove photo" onClick={() => setDeletingItem(photo)}><i className="fa fa-trash"></i></button>
                        </div>
                      </figcaption>
                    </figure>
                  ))}
                </div>
              )}
            </ChapterCard>
            {saveBar}
          </>
        )

      case 'proper': {
        const tpDocs = findDocs(photos, TRAINING_PLAN_ALIASES)
        const tpFromRecords = tpDocs.some((d) => !isStudentUpload(d))
        const tpStatus = nodeById.training_plan?.status
        const validated = authoritativeAttendance(attendance).filter((r) => r.validated || r.status === 'validated')
        const hours = validated.reduce((sum, r) => sum + (Number(r.hours_rendered) || 0), 0)
        const pendingDays = recordStatus.attendance?.pending || 0
        const pendingJournals = recordStatus.journals?.submitted || 0
        return (
          <>
            {editorCard('narrative', 'fa-pen-nib')}

            <ChapterCard icon="fa-comments" title={tocLabel('recommendations')} status={groupStatus(nodeById.recommendations.children)}>
              <div className="d-flex flex-column gap-3">
                {['rec_students', 'rec_program', 'rec_hte'].map((key) => (
                  <div key={key}>
                    <div className="d-flex align-items-center justify-content-between mb-1">
                      <label className="portfolio-field-label mb-0" htmlFor={`rte-${key}`}>{tocLabel(key)}</label>
                      <StatusPill status={nodeById[key]?.status} compact />
                    </div>
                    <RichTextEditor id={`rte-${key}`} value={sections[key] || ''} placeholder={TEXT_SECTIONS[key].placeholder} onChange={(html) => setSection(key, html)} minHeight={150} />
                  </div>
                ))}
              </div>
            </ChapterCard>

            <div className="row g-4">
              <div className="col-12 col-lg-4">
                <ChapterCard icon="fa-clipboard-list" title={tocLabel('training_plan')} status={tpStatus}>
                  <FileTile
                    title="Training Plan"
                    status={tpStatus}
                    note={tpFromRecords
                      ? 'Retrieved from your approved Requirements.'
                      : tpStatus === 'awaiting'
                        ? 'Submitted in Requirements — awaiting approval.'
                        : `Image only (JPG, PNG, WEBP), max ${UPLOAD_MAX_MB} MB.`}
                    docs={tpDocs}
                    onRemove={setDeletingItem}
                    upload={!tpFromRecords && tpStatus !== 'awaiting'
                      ? upload('cbaa_training_plan', tpDocs.length ? 'Replace' : 'Upload', { accept: IMAGE_ACCEPT })
                      : null}
                  />
                </ChapterCard>
              </div>
              <div className="col-12 col-lg-4">
                <ChapterCard icon="fa-clock" title={tocLabel('dtr')} status={nodeById.dtr?.status}>
                  <div className="portfolio-chapter2-stat mb-2"><strong>{validated.length}</strong><span>validated day{validated.length === 1 ? '' : 's'} · {hours.toFixed(2)} hrs</span></div>
                  <p className="portfolio-panel-text small mb-2">
                    Generated from supervisor-validated attendance only{pendingDays ? `; ${pendingDays} day${pendingDays === 1 ? ' is' : 's are'} awaiting validation` : ''}. Corrections are made in Attendance.
                  </p>
                  <Link to="/student/attendance" className="btn btn-outline-primary btn-sm"><i className="fa fa-arrow-right me-1"></i>Go to Attendance</Link>
                </ChapterCard>
              </div>
              <div className="col-12 col-lg-4">
                <ChapterCard icon="fa-book-open" title={tocLabel('journal')} status={nodeById.journal?.status}>
                  <div className="portfolio-chapter2-stat mb-2"><strong>{journals.length}</strong><span>approved week{journals.length === 1 ? '' : 's'} ready for the portfolio</span></div>
                  <p className="portfolio-panel-text small mb-2">
                    Approved journals are included in chronological order{pendingJournals ? `; ${pendingJournals} awaiting approval` : ''}. Journals are written in the Logbook.
                  </p>
                  <Link to="/student/logbook" className="btn btn-outline-primary btn-sm"><i className="fa fa-arrow-right me-1"></i>{journals.length ? 'Manage Weekly Journals' : 'Go to Logbook'}</Link>
                </ChapterCard>
              </div>
            </div>
            {saveBar}
          </>
        )
      }

      case 'appendices':
        return (
          <section className="portfolio-appendix-section">
            <div className="portfolio-appendix-section-head">
              <h5 className="mb-0 text-primary"><i className="fa fa-folder-open me-2"></i>{tocLabel('appendices')}</h5>
              <span className="text-muted small">
                {state.appendixDone} of {APPENDICES.length} available. Documents already approved in InternTrack and submitted evaluations are attached automatically; upload only what is not yet on file.
              </span>
            </div>
            <div className="portfolio-upload-grid">
              {state.appendices.map((a, i) => {
                const uploads = a.docs.filter(isStudentUpload)
                const canUpload = a.upload && a.source !== 'record' && a.status !== 'awaiting'
                let note = `Image only (JPG, PNG, WEBP), max ${UPLOAD_MAX_MB} MB.`
                if (a.evaluation) note = a.status === 'auto' ? `Generated from the submitted ${a.evaluation} evaluation.` : a.unavailableHint
                else if (a.source === 'record') note = 'Retrieved from your approved InternTrack records.'
                else if (a.status === 'awaiting') note = 'Submitted in Requirements — awaiting approval.'
                else if (a.status === 'unavailable') note = a.unavailableHint
                return (
                  <FileTile
                    key={a.key}
                    title={`${appendixLetter(i)}. ${a.title}`}
                    status={a.status}
                    note={note}
                    docs={a.docs}
                    onRemove={setDeletingItem}
                    upload={canUpload
                      ? upload(appendixUploadType(a.key), a.multiple ? (uploads.length ? 'Upload More' : 'Upload') : (uploads.length ? 'Replace' : 'Upload'), { accept: IMAGE_ACCEPT, multiple: Boolean(a.multiple) })
                      : null}
                  />
                )
              })}
            </div>
          </section>
        )

      default:
        return null
    }
  }

  // ---------- Page ----------
  if (!data && loadError) {
    return (
      <Layout title="My Portfolio" subtitle="Student" icon="fa-folder-plus" bodyClass="student-page">
        <PageError message={loadError} onRetry={fetchPortfolio} />
      </Layout>
    )
  }
  if (!data) {
    return (
      <Layout title="My Portfolio" subtitle="Loading…" icon="fa-folder-plus" bodyClass="student-page">
        <div className="text-center py-5 mt-5"><InternTrackLoader /></div>
      </Layout>
    )
  }

  const savedTime = lastSavedAt ? lastSavedAt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : ''
  const saveLabel = {
    idle: savedTime ? `Saved ${savedTime}` : 'All changes saved',
    dirty: 'Unsaved changes',
    saving: 'Saving…',
    saved: `Saved ${savedTime}`,
    error: 'Save failed',
  }[saveState]

  const stat = (icon, tone, value, label) => (
    <div className="d-flex align-items-center gap-2">
      <div className={`rounded-3 bg-light text-${tone} d-flex align-items-center justify-content-center`} style={{ width: 38, height: 38, fontSize: '1rem' }}>
        <i className={`fa ${icon}`}></i>
      </div>
      <div>
        <div className="fw-bold text-dark lh-1" style={{ fontSize: '1rem' }}>{value}</div>
        <div className="text-muted" style={{ fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{label}</div>
      </div>
    </div>
  )

  return (
    <Layout title="My Portfolio" subtitle="Student" icon="fa-folder-plus" bodyClass="student-page">
      <div className="portfolio-builder cbaa-builder">
        {/* ── Summary header (same layout as the other colleges) ── */}
        <div className="card shadow-sm border-0 mb-4" style={{ borderRadius: '12px', background: '#fff' }}>
          <div className="card-body p-3 p-lg-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div className="d-flex align-items-center gap-3 flex-wrap">
              {stat('fa-chart-pie', 'primary', `${state.completion}%`, 'Completion')}
              <div className="vr d-none d-sm-block text-muted opacity-25" style={{ height: 30 }}></div>
              {stat('fa-file-lines', 'primary', `${textDone}/${textKeys.length}`, 'Text Sections')}
              <div className="vr d-none d-sm-block text-muted opacity-25" style={{ height: 30 }}></div>
              {stat('fa-cloud-arrow-up', 'success', `${state.appendixDone}/${APPENDICES.length}`, 'Appendices')}
              <div className="vr d-none d-sm-block text-muted opacity-25" style={{ height: 30 }}></div>
              {stat('fa-book-bookmark', 'warning', journals.length, 'Journals')}
            </div>
            <div className="d-flex align-items-center gap-2 flex-wrap">
              <span className={`cbaa-save-state state-${saveState}`} aria-live="polite">
                {saveState === 'saving' ? <i className="fa fa-spinner fa-spin me-1"></i> : <i className={`fa ${saveState === 'error' ? 'fa-triangle-exclamation' : saveState === 'dirty' ? 'fa-pen' : 'fa-cloud'} me-1`}></i>}
                {saveLabel}
              </span>
              <button type="button" className="btn btn-outline-secondary btn-sm px-3" onClick={() => saveDraft()} disabled={saveState === 'saving'}>
                <i className="fa fa-floppy-disk me-1"></i>Save Draft
              </button>
              <Link to="/student/portfolio/preview" className="btn btn-primary btn-sm px-3 shadow-sm" onClick={goPreview}>
                <i className="fa fa-eye me-1"></i>Preview Portfolio
              </Link>
            </div>
          </div>
          <div className="progress mx-3 mx-lg-4 mb-3" style={{ height: 6 }} role="progressbar" aria-label="Portfolio completion" aria-valuenow={state.completion} aria-valuemin="0" aria-valuemax="100">
            <div className="progress-bar bg-success" style={{ width: `${state.completion}%` }}></div>
          </div>
        </div>

        {/* ── Tab bar mirrors the Table of Contents (I–VI) ── */}
        <div className="d-flex justify-content-center mb-4 cbaa-tabs-wrap">
          <div className="placement-tabs-bar" role="tablist" aria-label="Portfolio sections">
            {BUILDER_TABS.map((tab) => (
              <button
                key={tab.key}
                type="button"
                role="tab"
                aria-selected={activeTab === tab.key}
                className={`placement-tab-btn${activeTab === tab.key ? ' active' : ''}`}
                onClick={() => setActiveTab(tab.key)}
                title={`${tab.num}. ${tab.label}`}
              >
                <i className={`fa ${tab.icon}`}></i>
                <span>{tab.num}. {tab.label}</span>
                <StatusPill status={tabStatus(tab.key)} compact />
              </button>
            ))}
          </div>
        </div>

        <div className="portfolio-form-section bg-white p-3 p-lg-4 border rounded shadow-sm mb-4 d-flex flex-column gap-4">
          {renderTab()}
        </div>
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
        onConfirm={handleConfirmDelete}
      />
    </Layout>
  )
}

export default CBAAPortfolioBuilder

import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import api from '../../../services/api'

/** Per-field limit for Chapter III so answers fit cleanly in the A4 PDF pages. */
const CHAPTER3_MAX = 500

const CHAPTER3_FIELDS = [
  { key: 'prof_ethical_responsibilities', label: 'Professional, Ethical, and Legal Responsibilities' },
  { key: 'things_learned', label: 'Things I Learned as a Future IT Professional' },
  { key: 'experience_with_people', label: 'My Experience with People Around Me' },
  { key: 'industry_best_practices', label: 'Industry-aligned Best Practices and Standards I Learned' },
  { key: 'recommendations', label: 'My Recommendation for Improvement of the Internship Program' },
  { key: 'advice', label: 'My Advice to Those Who Will Take Their Internship in the Near Future' },
]

function PortfolioBuilder() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)

  const [form, setForm] = useState({
    company_background: '',
    company_vision: '',
    company_mission: '',
    prof_ethical_responsibilities: '',
    things_learned: '',
    experience_with_people: '',
    industry_best_practices: '',
    recommendations: '',
    advice: ''
  })

  const fetchPortfolio = () => {
    setLoading(true)
    api.get('/student/portfolio')
      .then(res => {
        setData(res.data)
        const p = res.data.internship?.portfolio
        if (p) {
          setForm({
            company_background: p.company_background ?? '',
            company_vision: p.company_vision ?? '',
            company_mission: p.company_mission ?? '',
            prof_ethical_responsibilities: p.prof_ethical_responsibilities ?? '',
            things_learned: p.things_learned ?? '',
            experience_with_people: p.experience_with_people ?? '',
            industry_best_practices: p.industry_best_practices ?? '',
            recommendations: p.recommendations ?? '',
            advice: p.advice ?? ''
          })
        }
      })
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchPortfolio() }, [])

  const setChapter3Field = (key, value) => {
    setForm(prev => ({ ...prev, [key]: value.slice(0, CHAPTER3_MAX) }))
  }

  const handleSave = async (e) => {
    e.preventDefault()
    const overLimit = CHAPTER3_FIELDS.find(f => (form[f.key] || '').length > CHAPTER3_MAX)
    if (overLimit) {
      setMessage({ type: 'danger', text: `Chapter III "${overLimit.label}" exceeds ${CHAPTER3_MAX} characters. Shorten it before saving.` })
      window.scrollTo(0, 0)
      return
    }
    setSaving(true)
    setMessage(null)
    try {
      await api.post('/student/portfolio', form)
      setMessage({ type: 'success', text: 'Portfolio data saved successfully.' })
      fetchPortfolio()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Failed to save.' })
    } finally {
      setSaving(false)
      window.scrollTo(0, 0)
    }
  }

  const handleLogoUpload = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', 'company_logo')
    try {
      await api.post('/student/portfolio/photos', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      fetchPortfolio()
    } catch (err) {
      alert("Failed to upload logo: " + (err.response?.data?.message || err.message))
    } finally {
      e.target.value = ''
    }
  }

  const handleFileUpload = async (e, type, requiresWeek = false, requiresLabel = false) => {
    const file = e.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)

    if (requiresWeek) {
      const week = window.prompt("Enter the Week Number for this photo (e.g. 1, 2, 3...):")
      if (!week || isNaN(parseInt(week))) { alert("Please enter a valid number."); return }
      formData.append('week_number', parseInt(week))
    }

    if (requiresLabel) {
      const label = window.prompt("Enter a label/caption for this item:")
      if (label === null) return
      formData.append('label', label)
    }

    try {
      await api.post('/student/portfolio/photos', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      fetchPortfolio()
    } catch (err) {
      alert("Failed to upload file: " + (err.response?.data?.message || err.message))
    } finally {
      e.target.value = ''
    }
  }

  const handleDeleteFile = async (id) => {
    if (!window.confirm("Delete this file?")) return
    try {
      await api.delete(`/student/portfolio/photos/${id}`)
      fetchPortfolio()
    } catch (err) {
      alert("Failed to delete file.")
    }
  }

  if (loading) return <Layout title="Portfolio Builder"><div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x"></i></div></Layout>

  const p = data?.internship?.portfolio
  const photos = p?.photos || []
  const journals = data?.internship?.journals || []

  const textChecks = [
    { key: 'company_background', label: 'Company Background' },
    { key: 'company_vision', label: 'Company Vision' },
    { key: 'company_mission', label: 'Company Mission' },
    { key: 'prof_ethical_responsibilities', label: 'Ethical Responsibilities' },
    { key: 'things_learned', label: 'Things Learned' },
    { key: 'experience_with_people', label: 'Experience with People' },
    { key: 'industry_best_practices', label: 'Best Practices' },
    { key: 'recommendations', label: 'Recommendations' },
    { key: 'advice', label: 'Advice for Future Interns' },
  ]
  const uploadChecks = [
    { type: 'org_chart', label: 'Organizational Chart' },
    { type: 'ojt_photo', label: 'Photos During OJT' },
    { type: 'training_certificate', label: 'Training Certificate' },
    { type: 'training_test_result', label: 'Training Test Result' },
    { type: 'training_documentation', label: 'Training Documentation' },
    { type: 'exam_certificate', label: 'Exam Certification' },
    { type: 'exam_test_result', label: 'Exam Test Result' },
    { type: 'exam_documentation', label: 'Exam Documentation' },
    { type: 'registration_form', label: 'Registration Form' },
    { type: 'visitation_form', label: 'Visitation Form' },
    { type: 'hte_evaluation', label: 'HTE Evaluation' },
    { type: 'program_evaluation', label: 'Program Evaluation' },
  ]

  const textDone = textChecks.filter(c => (form[c.key] || '').trim().length > 0).length
  const logoDone = Boolean(p?.company_logo_path)
  const uploadsDone = uploadChecks.filter(c => photos.some(ph => ph.type === c.type)).length + (logoDone ? 1 : 0)
  const uploadsTotal = uploadChecks.length + 1 // + company logo
  const journalCount = journals.length
  const overallPct = Math.round(((textDone + uploadsDone) / (textChecks.length + uploadsTotal)) * 100)
  const missingText = textChecks.filter(c => !(form[c.key] || '').trim())
  const missingUploads = [
    ...(!logoDone ? [{ label: 'Company Logo' }] : []),
    ...uploadChecks.filter(c => !photos.some(ph => ph.type === c.type)),
  ]

  // Compact upload tile — tip text is a one-line muted hint (not a tall yellow alert)
  // so every card stays roughly the same height and packs cleanly in the grid.
  const renderFileList = (type, title, requiresWeek = false, requiresLabel = false, accept = "image/*,application/pdf", tip = "") => {
    const items = p?.photos?.filter(photo => photo.type === type) || []
    return (
      <div className="content-card portfolio-upload-tile">
        <div className="portfolio-upload-tile-head">
          <h6 className="mb-0">{title}</h6>
          {tip && <span className="portfolio-upload-tip" title={tip}><i className="fa fa-circle-info"></i></span>}
        </div>
        <div className="portfolio-upload-tile-body">
          {items.length > 0 ? (
            <div className="portfolio-upload-files">
              {items.map(item => (
                <div key={item.id} className="portfolio-upload-file-row">
                  <div className="text-truncate flex-grow-1 me-2 small">
                    {requiresWeek && <span className="badge bg-primary me-1">W{item.week_number}</span>}
                    {item.file_path.endsWith('.pdf')
                      ? <i className="fa fa-file-pdf text-danger me-1"></i>
                      : <i className="fa fa-image text-primary me-1"></i>}
                    {item.label || 'Uploaded'}
                  </div>
                  <div className="d-flex gap-1">
                    <a href={`http://localhost:8001/storage/${item.file_path}`} target="_blank" rel="noreferrer" className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}><i className="fa fa-eye"></i></a>
                    <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => handleDeleteFile(item.id)}><i className="fa fa-trash"></i></button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="portfolio-upload-empty">No files yet</p>
          )}
          {tip && <p className="portfolio-upload-hint">{tip}</p>}
          <input type="file" id={`upload-${type}`} className="d-none" accept={accept} onChange={(e) => handleFileUpload(e, type, requiresWeek, requiresLabel)} />
          <label htmlFor={`upload-${type}`} className="btn btn-outline-primary btn-sm w-100 portfolio-upload-btn">
            <i className="fa fa-upload me-1"></i>Upload
          </label>
        </div>
      </div>
    )
  }

  return (
    <Layout title="Portfolio Builder" subtitle="Automated Document Generator" icon="fa-book" bodyClass="student-page">
      {message && <div className={`alert alert-${message.type}`}>{message.text}</div>}

      <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <p className="text-muted mb-0">Fill in the text fields, then upload appendix files below.</p>
        <Link to="/student/portfolio/preview" className="btn btn-primary px-4"><i className="fa fa-print me-2"></i>Generate Preview & PDF</Link>
      </div>

      {/*
        Stacked layout (not side-by-side): form chapters on top, appendices
        full-width below. Side-by-side always left a large empty gap under the
        shorter form column — stacking removes that imbalance entirely.
        Scoped to .portfolio-builder-* classes only.
      */}
      <div className="portfolio-builder">

        <form onSubmit={handleSave} className="portfolio-form-section">
          {/* Chapters stack full-width — no side-by-side height mismatch / left gap */}
          <div className="content-card portfolio-chapter-card">
            <div className="content-card-header bg-light"><h6 className="mb-0">Chapter I: Host Company Profile</h6></div>
            <div className="p-3 p-lg-4">
              <div className="mb-3">
                <label className="portfolio-field-label">Company Background</label>
                <textarea className="form-control portfolio-field-input" rows={3} value={form.company_background} onChange={e => setForm({ ...form, company_background: e.target.value })}></textarea>
              </div>
              <div className="portfolio-fields-2">
                <div>
                  <label className="portfolio-field-label">Company Vision</label>
                  <textarea className="form-control portfolio-field-input" rows={3} value={form.company_vision} onChange={e => setForm({ ...form, company_vision: e.target.value })}></textarea>
                </div>
                <div>
                  <label className="portfolio-field-label">Company Mission</label>
                  <textarea className="form-control portfolio-field-input" rows={3} value={form.company_mission} onChange={e => setForm({ ...form, company_mission: e.target.value })}></textarea>
                </div>
              </div>
            </div>
          </div>

          <div className="content-card portfolio-chapter-card">
            <div className="content-card-header bg-light">
              <h6 className="mb-0">Chapter III: Assessment of the Program</h6>
            </div>
            <div className="p-3 p-lg-4">
              <div className="portfolio-chapter3-note mb-3">
                <i className="fa fa-circle-info"></i>
                <span>
                  Each answer is limited to <strong>{CHAPTER3_MAX} characters</strong> so Chapter III fits the portfolio PDF without overlapping the page footer. Aim for 1–2 short paragraphs per section.
                </span>
              </div>
              <div className="portfolio-fields-2">
                {CHAPTER3_FIELDS.map(field => {
                  const len = (form[field.key] || '').length
                  const nearLimit = len >= CHAPTER3_MAX * 0.9
                  return (
                    <div key={field.key}>
                      <label className="portfolio-field-label" htmlFor={`ch3-${field.key}`}>{field.label}</label>
                      <textarea
                        id={`ch3-${field.key}`}
                        className="form-control portfolio-field-input"
                        rows={4}
                        maxLength={CHAPTER3_MAX}
                        value={form[field.key]}
                        onChange={e => setChapter3Field(field.key, e.target.value)}
                      ></textarea>
                      <div className={`portfolio-char-count${nearLimit ? ' warn' : ''}${len >= CHAPTER3_MAX ? ' over' : ''}`}>
                        {len}/{CHAPTER3_MAX}
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          </div>

          {/* Utility panels in one even row — no empty column beside a taller neighbor */}
          <div className="portfolio-utility-row">
            <div className="content-card portfolio-progress-card">
              <div className="content-card-header bg-light">
                <h6 className="mb-0"><i className="fa fa-chart-simple me-2"></i>Portfolio Progress</h6>
              </div>
              <div className="p-3">
                <div className="portfolio-progress-top">
                  <div className="portfolio-progress-pct">{overallPct}%</div>
                  <div className="portfolio-progress-meta">
                    <strong>PDF readiness</strong>
                    <span>{textDone}/{textChecks.length} text · {uploadsDone}/{uploadsTotal} uploads · {journalCount} journals</span>
                  </div>
                </div>
                <div className="portfolio-progress-bar" aria-hidden="true">
                  <div className="portfolio-progress-bar-fill" style={{ width: `${overallPct}%` }}></div>
                </div>
                {(missingText.length > 0 || missingUploads.length > 0) ? (
                  <div className="portfolio-missing-list">
                    <div className="portfolio-missing-label">Still needed</div>
                    <ul>
                      {missingText.slice(0, 3).map(item => (
                        <li key={item.key}><i className="fa fa-pen me-1"></i>{item.label}</li>
                      ))}
                      {missingUploads.slice(0, 3).map(item => (
                        <li key={item.label}><i className="fa fa-cloud-arrow-up me-1"></i>{item.label}</li>
                      ))}
                      {(missingText.length + missingUploads.length) > 6 && (
                        <li className="text-muted">+{(missingText.length + missingUploads.length) - 6} more…</li>
                      )}
                    </ul>
                  </div>
                ) : (
                  <div className="portfolio-ready-banner">
                    <i className="fa fa-circle-check"></i>
                    All tracked sections look complete — ready to preview.
                  </div>
                )}
              </div>
            </div>

            <div className="content-card portfolio-chapter2-card">
              <div className="content-card-header bg-light">
                <h6 className="mb-0"><i className="fa fa-book-open me-2"></i>Chapter II: Weekly Journals</h6>
              </div>
              <div className="p-3">
                <p className="portfolio-panel-text">
                  Weekly journals are pulled automatically from your Logbook into the portfolio PDF. You do not re-upload them here.
                </p>
                <div className="portfolio-chapter2-stat">
                  <strong>{journalCount}</strong>
                  <span>journal week{journalCount === 1 ? '' : 's'} ready for PDF</span>
                </div>
                <Link to="/student/logbook" className="btn btn-outline-primary btn-sm mt-3">
                  <i className="fa fa-arrow-right me-1"></i>
                  {journalCount === 0 ? 'Go to Logbook' : 'Manage journals'}
                </Link>
              </div>
            </div>

            <div className="content-card portfolio-tips-card">
              <div className="content-card-header bg-light">
                <h6 className="mb-0"><i className="fa fa-lightbulb me-2"></i>Writing Tips</h6>
              </div>
              <ul className="portfolio-tips-list">
                <li>Write Chapter I in paragraph form — company history, what they do, and who they serve.</li>
                <li>For Chapter III, use specific OJT examples (tools, problems solved, teammates).</li>
                <li>Save text fields, upload appendices below, then generate your PDF preview.</li>
                <li>School forms (CV, consent, acceptance, etc.) stay on the Documents page.</li>
              </ul>
            </div>
          </div>

          <div className="portfolio-form-actions">
            <button type="submit" className="btn btn-success px-5" disabled={saving}>
              {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Text Fields</>}
            </button>
            <Link to="/student/portfolio/preview" className="btn btn-outline-primary px-4">
              <i className="fa fa-eye me-2"></i>Preview PDF
            </Link>
            <div className="portfolio-info-note">
              <i className="fa fa-circle-info"></i>
              <span>Your portfolio will be compiled into a single PDF using the information and files provided here. Make sure all required sections are filled before generating your preview.</span>
            </div>
          </div>
        </form>

        <section className="portfolio-appendix-section">
          <div className="portfolio-appendix-section-head">
            <h5 className="mb-0 text-primary"><i className="fa fa-folder-open me-2"></i>Appendices & Uploads</h5>
            <span className="text-muted small">Upload files for your portfolio PDF. Documents already tracked under Documents &amp; Requirements are not listed here.</span>
          </div>

          {/* Company Logo — featured slim strip, not a tall full card */}
          <div className="content-card portfolio-logo-strip">
            <div className="portfolio-logo-strip-left">
              <h6 className="mb-1"><i className="fa fa-building me-2"></i>Company Logo (HTE)</h6>
              <p className="mb-0 text-muted small">Appears in the PDF header. Prefer a clear PNG/JPG.</p>
            </div>
            <div className="portfolio-logo-strip-right">
              {p?.company_logo_path ? (
                <img src={`http://localhost:8001/storage/${p.company_logo_path}`} alt="Company Logo" className="portfolio-logo-thumb" />
              ) : (
                <span className="text-muted small">No logo yet</span>
              )}
              <input type="file" id="upload-company_logo" className="d-none" accept="image/*" onChange={handleLogoUpload} />
              <label htmlFor="upload-company_logo" className="btn btn-outline-primary btn-sm mb-0">
                <i className="fa fa-upload me-1"></i>{p?.company_logo_path ? 'Replace' : 'Upload Logo'}
              </label>
            </div>
          </div>

          <div className="portfolio-appendix-group">
            <h6 className="portfolio-appendix-group-title">Company &amp; OJT</h6>
            <div className="portfolio-upload-grid">
              {renderFileList('org_chart', 'Organizational Chart', false, false, 'image/*')}
              {renderFileList('ojt_photo', 'Photos During OJT', true, true, 'image/*', 'You will be prompted for week number and caption.')}
            </div>
          </div>

          <div className="portfolio-appendix-group">
            <h6 className="portfolio-appendix-group-title">F2F/Online Training (Wadhwani)</h6>
            <div className="portfolio-upload-grid">
              {renderFileList('training_certificate', 'Certificate of Training')}
              {renderFileList('training_test_result', 'Pre and Post Test Result')}
              {renderFileList('training_documentation', 'Documentation of Training Proper', false, true, 'image/*', 'Required — upload pictures with explanations.')}
            </div>
          </div>

          <div className="portfolio-appendix-group">
            <h6 className="portfolio-appendix-group-title">Certification Exam (Online / F2F)</h6>
            <div className="portfolio-upload-grid">
              {renderFileList('exam_certificate', 'Certification')}
              {renderFileList('exam_test_result', 'Pre and Post Test Result')}
              {renderFileList('exam_documentation', 'Documentation of Preparation', false, true, 'image/*', 'Upload pictures of exam preparation with explanations.')}
            </div>
          </div>

          {/*
            Documents already on the Documents page (CV, Medical, Psych, Consent,
            Acceptance, Application Letter, Recommendation, Training Plan, MOA,
            Completion Certificate) are intentionally omitted here.
          */}
          <div className="portfolio-appendix-group">
            <h6 className="portfolio-appendix-group-title">Other Required Appendices</h6>
            <div className="portfolio-upload-grid">
              {renderFileList('registration_form', 'Registration Form')}
              {renderFileList('visitation_form', 'Internship / OJT Visitation Form')}
              {renderFileList('hte_evaluation', 'HTE Evaluation Form (PNC AA-FO-22)')}
              {renderFileList('program_evaluation', 'Program Evaluation Form (PNC AA-FO-23)')}
            </div>
          </div>
        </section>
      </div>
    </Layout>
  )
}

export default PortfolioBuilder

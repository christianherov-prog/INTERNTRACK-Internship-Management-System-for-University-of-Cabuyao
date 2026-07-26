import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import ConfirmModal from '../../../components/modals/ConfirmModal'
import api from '../../../services/api'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../../components/AuthenticatedFile'

const CHAPTER3_MAX = 3000

const CHAPTER3_FIELDS = [
  { key: 'prof_ethical_responsibilities', label: 'Professional, Ethical, and Legal Responsibilities as Future IT Professionals' },
  { key: 'things_learned', label: 'Things I Learned as a Future IT Professional' },
  { key: 'experience_with_people', label: 'My Experience with People Around Me' },
  { key: 'industry_best_practices', label: 'Industry-aligned Best Practices and Standards I Learned' },
  { key: 'recommendations', label: 'My Recommendation for Improvement of the Internship Program' },
  { key: 'advice', label: 'My Advice to Those Who Will Take Their Internship in the Near Future' },
]

const TABS = [
  { id: 'cover',       icon: 'fa-id-card',       label: 'Cover & Title' },
  { id: 'chapter1',   icon: 'fa-building',       label: 'Chapter I' },
  { id: 'chapter2',   icon: 'fa-book-open',      label: 'Chapter II' },
  { id: 'chapter3',   icon: 'fa-pen-to-square',  label: 'Chapter III' },
  { id: 'appendices', icon: 'fa-folder-open',    label: 'Appendices' },
  { id: 'photos',     icon: 'fa-images',         label: 'OJT Photos' },
  { id: 'training',   icon: 'fa-certificate',    label: 'Training & Certs' },
]

function PortfolioBuilder() {
  const [activeTab, setActiveTab] = useState('cover')
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [form, setForm] = useState({
    company_profile: '',
    company_background: '',
    company_vision: '',
    company_mission: '',
    org_chart_caption: '',
    prof_ethical_responsibilities: '',
    things_learned: '',
    experience_with_people: '',
    industry_best_practices: '',
    recommendations: '',
    advice: '',
  })
  const [deleteTargetId, setDeleteTargetId] = useState(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState(null)
  const [ojtForm, setOjtForm] = useState({ week: '', label: '', description: '', file: null })
  const [ojtUploading, setOjtUploading] = useState(false)

  const fetchPortfolio = () => {
    setLoading(true)
    api.get('/student/portfolio')
      .then(res => {
        setData(res.data)
        const p = res.data.internship?.portfolio
        if (p) {
          setForm({
            company_profile:               p.company_profile ?? '',
            company_background:            p.company_background ?? '',
            company_vision:                p.company_vision ?? '',
            company_mission:               p.company_mission ?? '',
            org_chart_caption:             p.org_chart_caption ?? '',
            prof_ethical_responsibilities: p.prof_ethical_responsibilities ?? '',
            things_learned:                p.things_learned ?? '',
            experience_with_people:        p.experience_with_people ?? '',
            industry_best_practices:       p.industry_best_practices ?? '',
            recommendations:               p.recommendations ?? '',
            advice:                        p.advice ?? '',
          })
        }
      })
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchPortfolio() }, [])

  const handleSave = async (e) => {
    e.preventDefault()
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

  const uploadFile = async (file, type, extra = {}) => {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('type', type)
    Object.entries(extra).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) fd.append(k, v) })
    await api.post('/student/portfolio/photos', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    fetchPortfolio()
  }

  const handleSimpleUpload = async (e, type) => {
    const file = e.target.files[0]
    if (!file) return
    e.target.value = ''
    try { await uploadFile(file, type) }
    catch (err) { setMessage({ type: 'danger', text: 'Upload failed: ' + (err.response?.data?.message || err.message) }) }
  }

  const handleOjtUpload = async () => {
    if (!ojtForm.file) return
    if (!ojtForm.week || isNaN(parseInt(ojtForm.week))) {
      setMessage({ type: 'danger', text: 'Please enter a valid week number.' })
      return
    }
    setOjtUploading(true)
    try {
      await uploadFile(ojtForm.file, 'ojt_photo', {
        week_number: parseInt(ojtForm.week),
        label: ojtForm.label,
        description: ojtForm.description
      })
      setOjtForm({ week: '', label: '', description: '', file: null })
      setMessage({ type: 'success', text: 'OJT photo uploaded.' })
    } catch (err) {
      setMessage({ type: 'danger', text: 'Upload failed: ' + (err.response?.data?.message || err.message) })
    } finally {
      setOjtUploading(false)
    }
  }

  const confirmDeleteFile = async () => {
    if (!deleteTargetId) return
    setDeleting(true)
    setDeleteError(null)
    try {
      await api.delete(`/student/portfolio/photos/${deleteTargetId}`)
      setDeleteTargetId(null)
      fetchPortfolio()
    } catch (err) {
      setDeleteError(err.response?.data?.message || 'Delete failed.')
    } finally {
      setDeleting(false)
    }
  }

  if (loading) return <Layout title="Portfolio Builder"><div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x"></i></div></Layout>

  const internship = data?.internship
  const p = internship?.portfolio
  const photos = p?.photos || []
  const journals = internship?.journals || []
  // Laravel JSON uses snake_case relationship keys (student_profile, faculty_profile).
  const student = data?.user?.student_profile ?? data?.user?.studentProfile ?? null
  const company = internship?.company
  const facultyProfile =
    internship?.faculty?.faculty_profile
    ?? internship?.faculty?.facultyProfile
    ?? null

  const studentDisplayName = student
    ? `${student.last_name}, ${student.first_name}${student.middle_name ? ` ${student.middle_name}` : ''}`.trim()
    : null
  const studentProgram = student?.program || student?.course_name || internship?.program || null
  const studentSection = student?.section || internship?.section || null
  const instructorName = facultyProfile
    ? `${facultyProfile.first_name} ${facultyProfile.last_name}`.trim()
    : null

  const textChecks = [
    { key: 'company_profile' }, { key: 'company_background' }, { key: 'company_vision' }, { key: 'company_mission' },
    { key: 'prof_ethical_responsibilities' }, { key: 'things_learned' }, { key: 'experience_with_people' },
    { key: 'industry_best_practices' }, { key: 'recommendations' }, { key: 'advice' },
  ]
  const appendixTypes = [
    'registration_form','medical_result','psychological_result','application_letter','student_cv',
    'recommendation_request','acceptance_form','consent_form','training_plan','dtr_form',
    'performance_eval','moa_document','visitation_form','completion_certificate','hte_evaluation','program_evaluation'
  ]
  const textDone = textChecks.filter(c => (form[c.key] || '').trim()).length
  const appendixDone = appendixTypes.filter(t => photos.some(ph => ph.type === t)).length
  const logoDone = Boolean(p?.company_logo_path)
  const orgChartDone = Boolean(p?.org_chart_path)
  const journalCount = journals.length
  const ojtPhotos = photos.filter(ph => ph.type === 'ojt_photo')
  const totalItems = textChecks.length + 2 + appendixTypes.length + 1 + 1
  const doneItems = textDone + (logoDone?1:0) + (orgChartDone?1:0) + appendixDone + (journalCount>0?1:0) + (ojtPhotos.length>0?1:0)
  const pct = Math.round((doneItems / totalItems) * 100)

  const renderAppendixCard = (type, title, accept = 'image/*,application/pdf', tip = '') => {
    const items = photos.filter(ph => ph.type === type)
    return (
      <div className="content-card portfolio-upload-tile" key={type}>
        <div className="portfolio-upload-tile-head">
          <h6 className="mb-0" style={{ fontSize: '0.8rem' }}>
            {items.length > 0
              ? <i className="fa fa-circle-check text-success me-1"></i>
              : <i className="fa fa-circle text-muted me-1"></i>}
            {title}
          </h6>
        </div>
        <div className="portfolio-upload-tile-body">
          {items.length > 0 ? (
            <div className="portfolio-upload-files">
              {items.map(item => (
                <div key={item.id} className="portfolio-upload-file-row">
                  <div className="text-truncate flex-grow-1 me-2 small">
                    {item.file_path.endsWith('.pdf')
                      ? <i className="fa fa-file-pdf text-danger me-1"></i>
                      : <i className="fa fa-image text-primary me-1"></i>}
                    {item.label || 'Uploaded'}
                  </div>
                  <div className="d-flex gap-1">
                    <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}>
                      <i className="fa fa-eye"></i>
                    </AuthenticatedFileLink>
                    <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => setDeleteTargetId(item.id)}>
                      <i className="fa fa-trash"></i>
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : <p className="portfolio-upload-empty">No file yet</p>}
          {tip && <p className="portfolio-upload-hint">{tip}</p>}
          <input type="file" id={`upload-${type}`} className="d-none" accept={accept}
            onChange={e => { const f = e.target.files[0]; e.target.value = ''; if (f) uploadFile(f, type).catch(err => setMessage({ type: 'danger', text: 'Upload failed: ' + (err.response?.data?.message || err.message) })) }} />
          <label htmlFor={`upload-${type}`} className="btn btn-outline-green btn-sm w-100 portfolio-upload-btn mt-2">
            <i className="fa fa-upload me-1"></i>{items.length > 0 ? 'Replace / Add' : 'Upload'}
          </label>
        </div>
      </div>
    )
  }

  return (
    <Layout title="Portfolio Builder" subtitle="Official Internship Portfolio Generator" icon="fa-book" bodyClass="student-page">
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible`}>
          {message.text}
          <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {/* Tab Bar */}
      <div className="portfolio-tabs mb-3">
        {TABS.map(tab => (
          <button key={tab.id} type="button"
            className={`portfolio-tab-btn${activeTab === tab.id ? ' active' : ''}`}
            onClick={() => setActiveTab(tab.id)}>
            <i className={`fa ${tab.icon} me-1`}></i>
            <span className="portfolio-tab-label">{tab.label}</span>
          </button>
        ))}
        <Link to="/student/portfolio/preview" className="portfolio-tab-btn ms-auto text-decoration-none">
          <i className="fa fa-print me-1"></i>
          <span className="portfolio-tab-label">Preview &amp; Print</span>
        </Link>
      </div>

      {/* Progress Bar */}
      <div className="content-card mb-3">
        <div className="p-3">
          <div className="d-flex align-items-center justify-content-between mb-1">
            <strong style={{ fontSize: '0.85rem' }}>Portfolio Completeness</strong>
            <span className={`badge ${pct >= 80 ? 'bg-success' : pct >= 50 ? 'bg-warning text-dark' : 'bg-danger'}`}>{pct}%</span>
          </div>
          <div className="portfolio-progress-bar mb-1">
            <div className="portfolio-progress-bar-fill" style={{ width: `${pct}%` }}></div>
          </div>
          <div style={{ fontSize: '0.78rem', color: '#888' }}>
            {textDone}/{textChecks.length} text fields &middot; {appendixDone}/{appendixTypes.length} appendices &middot; {journalCount} journal{journalCount !== 1 ? 's' : ''} &middot; {ojtPhotos.length} OJT photos
          </div>
        </div>
      </div>

      {/* COVER & TITLE */}
      {activeTab === 'cover' && (
        <div className="content-card">
          <div className="content-card-header bg-light">
            <h6 className="mb-0"><i className="fa fa-id-card me-2"></i>Cover &amp; Title Page — Auto-populated Data</h6>
          </div>
          <div className="p-4">
            <p className="text-muted small mb-4">These fields are pulled automatically from your internship record and appear on the Cover Page and Title Page.</p>
            <div className="row g-3">
              {[
                { label: 'Student Name', value: studentDisplayName || '—' },
                { label: 'Student Number', value: student?.student_number || '—' },
                { label: 'Course / Program', value: studentProgram || '—' },
                { label: 'Section', value: studentSection || '—' },
                { label: 'Academic Year', value: internship?.academic_year || student?.academic_year || '—' },
                { label: 'Semester', value: internship?.semester ?? student?.semester ?? '—' },
                { label: 'Host Company', value: company?.company_name || '—' },
                { label: 'Company Address', value: company?.address || '—' },
                { label: 'Internship Instructor', value: instructorName || '—' },
              ].map(({ label, value }) => (
                <div className="col-md-6 col-lg-4" key={label}>
                  <div style={{ fontSize: '0.72rem', color: '#888', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 2 }}>{label}</div>
                  <div className="fw-semibold">{value}</div>
                </div>
              ))}
            </div>
            <div className="alert alert-info mt-4 mb-0 small">
              <i className="fa fa-circle-info me-2"></i>
              To update this information, contact your coordinator. These fields are generated automatically.
            </div>
          </div>
        </div>
      )}

      {/* CHAPTER I */}
      {activeTab === 'chapter1' && (
        <form onSubmit={handleSave}>
          <div className="content-card mb-3">
            <div className="content-card-header bg-light">
              <h6 className="mb-0"><i className="fa fa-building me-2"></i>Chapter I: Host Company Profile</h6>
            </div>
            <div className="p-3 p-lg-4">
              {/* Company Logo */}
              <div className="mb-4 p-3 border rounded d-flex align-items-center gap-3" style={{ background: '#f8f9fa' }}>
                {p?.company_logo_path
                  ? <AuthenticatedFileImage path={p.company_logo_path} alt="Company Logo" className="portfolio-logo-thumb" />
                  : <div style={{ width: 60, height: 60, border: '2px dashed #ccc', borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#bbb', fontSize: '0.7rem' }}>Logo</div>}
                <div>
                  <div className="fw-semibold mb-1">Company Logo (HTE)</div>
                  <p className="mb-2 text-muted small">Appears on the PDF page headers. Clear PNG/JPG preferred.</p>
                  <input type="file" id="upload-company_logo" className="d-none" accept="image/*" onChange={e => { handleSimpleUpload(e, 'company_logo') }} />
                  <label htmlFor="upload-company_logo" className="btn btn-outline-green btn-sm mb-0">
                    <i className="fa fa-upload me-1"></i>{p?.company_logo_path ? 'Replace Logo' : 'Upload Logo'}
                  </label>
                </div>
              </div>

              <div className="mb-3">
                <label className="portfolio-field-label">Company Profile <span className="text-muted small">(brief introduction)</span></label>
                <textarea className="form-control portfolio-field-input" rows={4} value={form.company_profile}
                  onChange={e => setForm(f => ({ ...f, company_profile: e.target.value }))}
                  placeholder="Write a brief introduction about the company."></textarea>
              </div>

              <div className="portfolio-fields-2 mb-3">
                <div>
                  <label className="portfolio-field-label">Company Vision</label>
                  <textarea className="form-control portfolio-field-input" rows={4} value={form.company_vision}
                    onChange={e => setForm(f => ({ ...f, company_vision: e.target.value }))}
                    placeholder="Enter the company vision statement."></textarea>
                </div>
                <div>
                  <label className="portfolio-field-label">Company Mission</label>
                  <textarea className="form-control portfolio-field-input" rows={4} value={form.company_mission}
                    onChange={e => setForm(f => ({ ...f, company_mission: e.target.value }))}
                    placeholder="Enter the company mission statement."></textarea>
                </div>
              </div>

              <div className="mb-3 p-3 border rounded" style={{ background: '#f8f9fa' }}>
                <div className="d-flex align-items-start gap-3 flex-wrap">
                  <div>
                    <div className="fw-semibold mb-1">Organizational Chart</div>
                    <p className="text-muted small mb-2">Upload an image of the company organizational chart.</p>
                    {p?.org_chart_path && (
                      <div className="mb-2">
                        <AuthenticatedFileImage path={p.org_chart_path} alt="Org Chart"
                          style={{ maxWidth: 200, maxHeight: 140, objectFit: 'contain', border: '1px solid #ddd', borderRadius: 4 }} />
                      </div>
                    )}
                    <input type="file" id="upload-org_chart" className="d-none" accept="image/*" onChange={e => { handleSimpleUpload(e, 'org_chart') }} />
                    <label htmlFor="upload-org_chart" className="btn btn-outline-green btn-sm">
                      <i className="fa fa-upload me-1"></i>{p?.org_chart_path ? 'Replace Chart' : 'Upload Org Chart'}
                    </label>
                  </div>
                  <div style={{ minWidth: 200, flex: 1 }}>
                    <label className="portfolio-field-label">Chart Caption (optional)</label>
                    <input type="text" className="form-control form-control-sm" value={form.org_chart_caption}
                      onChange={e => setForm(f => ({ ...f, org_chart_caption: e.target.value }))}
                      placeholder="e.g. Organizational Structure of XYZ Corp." />
                  </div>
                </div>
              </div>

              <div className="mb-3">
                <label className="portfolio-field-label">Company History / Background</label>
                <textarea className="form-control portfolio-field-input" rows={5} value={form.company_background}
                  onChange={e => setForm(f => ({ ...f, company_background: e.target.value }))}
                  placeholder="Write about the company history, founding, milestones, and background."></textarea>
              </div>
            </div>
          </div>
          <div className="portfolio-form-actions">
            <button type="submit" className="btn btn-success px-5" disabled={saving}>
              {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Chapter I</>}
            </button>
          </div>
        </form>
      )}

      {/* CHAPTER II */}
      {activeTab === 'chapter2' && (
        <div className="content-card">
          <div className="content-card-header bg-light">
            <h6 className="mb-0"><i className="fa fa-book-open me-2"></i>Chapter II: Weekly Progress Report</h6>
          </div>
          <div className="p-4">
            <div className="alert alert-info mb-4">
              <i className="fa fa-circle-info me-2"></i>
              Your approved <strong>weekly journal entries (PNC:AA-FO-31)</strong> from the Logbook are automatically included in your portfolio Chapter II. Each approved journal entry appears as one weekly report page in the PDF.
            </div>
            {journalCount === 0 ? (
              <div className="text-center py-4">
                <i className="fa fa-book-open fa-3x text-muted mb-3 d-block"></i>
                <p className="text-muted">No approved journal entries found yet.</p>
                <p className="text-muted small">Submit your weekly journals in the Logbook and get them approved first.</p>
                <Link to="/student/logbook" className="btn btn-green mt-2">
                  <i className="fa fa-arrow-right me-2"></i>Go to Logbook
                </Link>
              </div>
            ) : (
              <div>
                <p className="text-muted small mb-3"><strong>{journalCount}</strong> approved journal week{journalCount !== 1 ? 's' : ''} ready for the portfolio PDF.</p>
                <div className="table-responsive">
                  <table className="table table-hover table-sm">
                    <thead className="table-light">
                      <tr><th>Week</th><th>Date</th><th>Activities Summary</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                      {journals.map(j => (
                        <tr key={j.id}>
                          <td><span className="badge bg-primary">Week {j.week_number ?? j.entry_number}</span></td>
                          <td className="small">{j.date ?? '—'}</td>
                          <td className="small" style={{ maxWidth: 260 }}>
                            {j.activities_summary
                              ? j.activities_summary.slice(0, 80) + (j.activities_summary.length > 80 ? '…' : '')
                              : <span className="text-muted">File upload</span>}
                          </td>
                          <td><span className="badge bg-success">Approved</span></td>
                          <td>
                            {j.file_path && (
                              <AuthenticatedFileLink path={j.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.4rem' }}>
                                <i className="fa fa-eye"></i>
                              </AuthenticatedFileLink>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <Link to="/student/logbook" className="btn btn-outline-green btn-sm mt-2">
                  <i className="fa fa-arrow-right me-1"></i>Manage Journals
                </Link>
              </div>
            )}
          </div>
        </div>
      )}

      {/* CHAPTER III */}
      {activeTab === 'chapter3' && (
        <form onSubmit={handleSave}>
          <div className="content-card mb-3">
            <div className="content-card-header bg-light">
              <h6 className="mb-0"><i className="fa fa-pen-to-square me-2"></i>Chapter III: Assessment of the Program</h6>
            </div>
            <div className="p-3 p-lg-4">
              <div className="alert alert-light border mb-4 small">
                <i className="fa fa-circle-info me-2 text-primary"></i>
                Write essay-style responses for each section. Each field supports up to <strong>{CHAPTER3_MAX.toLocaleString()} characters</strong>.
              </div>
              <div className="portfolio-fields-2">
                {CHAPTER3_FIELDS.map(field => {
                  const len = (form[field.key] || '').length
                  const nearLimit = len >= CHAPTER3_MAX * 0.85
                  return (
                    <div key={field.key}>
                      <label className="portfolio-field-label" htmlFor={`ch3-${field.key}`}>{field.label}</label>
                      <textarea
                        id={`ch3-${field.key}`}
                        className="form-control portfolio-field-input"
                        rows={6}
                        maxLength={CHAPTER3_MAX}
                        value={form[field.key]}
                        onChange={e => setForm(prev => ({ ...prev, [field.key]: e.target.value.slice(0, CHAPTER3_MAX) }))}
                        placeholder="Write your response here..."
                      ></textarea>
                      <div className={`portfolio-char-count${nearLimit ? ' warn' : ''}${len >= CHAPTER3_MAX ? ' over' : ''}`}>
                        {len.toLocaleString()} / {CHAPTER3_MAX.toLocaleString()}
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          </div>
          <div className="portfolio-form-actions">
            <button type="submit" className="btn btn-success px-5" disabled={saving}>
              {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Chapter III</>}
            </button>
          </div>
        </form>
      )}

      {/* APPENDICES */}
      {activeTab === 'appendices' && (
        <div>
          <div className="mb-3">
            <h5 className="mb-1"><i className="fa fa-folder-open me-2 text-primary"></i>Appendices (Additional Documents)</h5>
            <p className="text-muted small">Upload scanned or completed copies of each required form. They will appear in the correct appendix pages of the final PDF.</p>
          </div>
          <div className="portfolio-appendix-group mb-4">
            <h6 className="portfolio-appendix-group-title">Required School Forms</h6>
            <div className="portfolio-upload-grid">
              {renderAppendixCard('registration_form', 'Registration Form')}
              {renderAppendixCard('medical_result', 'Medical Result')}
              {renderAppendixCard('psychological_result', 'Psychological Test Result')}
              {renderAppendixCard('application_letter', 'Application Letter')}
              {renderAppendixCard('student_cv', 'Student CV (PNC-AA-FO-27)')}
              {renderAppendixCard('recommendation_request', 'HTE Recommendation Request (PNC:AA-FO-26)')}
              {renderAppendixCard('acceptance_form', 'Acceptance Form (PNC:AA-FO-29)')}
              {renderAppendixCard('consent_form', 'Consent Form (PNC:AA-FO-28)')}
              {renderAppendixCard('training_plan', 'Training Plan (PNC:AA-FO-25.3)')}
            </div>
          </div>
          <div className="portfolio-appendix-group mb-4">
            <h6 className="portfolio-appendix-group-title">Attendance &amp; Performance</h6>
            <div className="portfolio-upload-grid">
              {renderAppendixCard('dtr_form', 'DTR — Daily Time Record (PNC:AA-FO-30)', 'image/*,application/pdf', 'Fill the official FO-30 form offline, then upload here.')}
              {renderAppendixCard('performance_eval', 'Performance Evaluation Form (PNC:AA-FO-24)')}
            </div>
          </div>
          <div className="portfolio-appendix-group mb-4">
            <h6 className="portfolio-appendix-group-title">Agreements &amp; Evaluations</h6>
            <div className="portfolio-upload-grid">
              {renderAppendixCard('moa_document', 'Memorandum of Agreement (MOA)')}
              {renderAppendixCard('visitation_form', 'OJT Visitation Form')}
              {renderAppendixCard('completion_certificate', 'Certification of Completion')}
              {renderAppendixCard('hte_evaluation', 'HTE Evaluation (PNC AA-FO-22)')}
              {renderAppendixCard('program_evaluation', 'Program Evaluation (PNC AA-FO-23)')}
            </div>
          </div>
          <div className="alert alert-light border small">
            <i className="fa fa-circle-info me-2 text-muted"></i>
            <strong>Progress:</strong> {appendixDone} of {appendixTypes.length} documents uploaded.
          </div>
        </div>
      )}

      {/* OJT PHOTOS */}
      {activeTab === 'photos' && (
        <div className="content-card">
          <div className="content-card-header bg-light">
            <h6 className="mb-0"><i className="fa fa-images me-2"></i>Photos During OJT</h6>
          </div>
          <div className="p-4">
            <p className="text-muted small mb-4">Upload photos taken during your OJT, organized by week. Each photo appears in the Photos appendix section of your portfolio.</p>
            <div className="p-3 border rounded mb-4" style={{ background: '#f8f9fa' }}>
              <h6 className="mb-3">Add a Photo</h6>
              <div className="row g-3">
                <div className="col-md-2">
                  <label className="form-label small fw-semibold">Week #</label>
                  <input type="number" className="form-control form-control-sm" min={1} placeholder="e.g. 1"
                    value={ojtForm.week} onChange={e => setOjtForm(s => ({ ...s, week: e.target.value }))} />
                </div>
                <div className="col-md-4">
                  <label className="form-label small fw-semibold">Photo Label / Title</label>
                  <input type="text" className="form-control form-control-sm" placeholder="e.g. Team Meeting"
                    value={ojtForm.label} onChange={e => setOjtForm(s => ({ ...s, label: e.target.value }))} />
                </div>
                <div className="col-md-6">
                  <label className="form-label small fw-semibold">Description / Caption</label>
                  <input type="text" className="form-control form-control-sm" placeholder="Brief explanation of this photo"
                    value={ojtForm.description} onChange={e => setOjtForm(s => ({ ...s, description: e.target.value }))} />
                </div>
                <div className="col-12">
                  <label className="form-label small fw-semibold">Select Photo</label>
                  <div className="d-flex gap-2 align-items-center flex-wrap">
                    <input
                      type="file"
                      id="ojt-file-input"
                      className="d-none"
                      accept="image/*"
                      onChange={e => setOjtForm(s => ({ ...s, file: e.target.files[0] || null }))}
                    />
                    <label htmlFor="ojt-file-input" className="btn btn-sm btn-outline-success mb-0">
                      <i className="fa fa-folder-plus me-1"></i>
                      {ojtForm.file ? ojtForm.file.name : 'Choose photo'}
                    </label>
                    <button
                      type="button"
                      className="btn btn-success btn-sm text-nowrap"
                      onClick={handleOjtUpload}
                      disabled={!ojtForm.file || ojtUploading}
                    >
                      {ojtUploading
                        ? <><i className="fa fa-spinner fa-spin me-1"></i>Uploading...</>
                        : <><i className="fa fa-upload me-1"></i>Upload</>}
                    </button>
                  </div>
                  {!ojtForm.file && (
                    <small className="text-muted d-block mt-1">No file selected yet</small>
                  )}
                </div>
              </div>
            </div>
            {ojtPhotos.length === 0
              ? <p className="text-muted text-center py-3">No OJT photos uploaded yet.</p>
              : (
                <div>
                  <h6 className="mb-3">Uploaded Photos ({ojtPhotos.length})</h6>
                  <div className="row g-3">
                    {ojtPhotos.sort((a, b) => (a.week_number ?? 0) - (b.week_number ?? 0)).map(photo => (
                      <div className="col-sm-6 col-md-4 col-lg-3" key={photo.id}>
                        <div className="card h-100">
                          <AuthenticatedFileImage path={photo.file_path} alt={photo.label}
                            style={{ height: 130, objectFit: 'cover', width: '100%', borderRadius: '4px 4px 0 0' }} />
                          <div className="card-body p-2">
                            <div className="d-flex justify-content-between align-items-start">
                              <div style={{ flex: 1 }}>
                                {photo.week_number && <span className="badge bg-primary me-1" style={{ fontSize: '0.7rem' }}>W{photo.week_number}</span>}
                                <div className="small fw-semibold mt-1">{photo.label || 'OJT Photo'}</div>
                                {photo.description && <div className="text-muted" style={{ fontSize: '0.72rem' }}>{photo.description}</div>}
                              </div>
                              <button type="button" className="btn btn-outline-danger btn-sm ms-1" style={{ padding: '0.1rem 0.3rem' }}
                                onClick={() => setDeleteTargetId(photo.id)}>
                                <i className="fa fa-trash"></i>
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
          </div>
        </div>
      )}

      {/* TRAINING & CERTS */}
      {activeTab === 'training' && (
        <div>
          <div className="portfolio-appendix-group mb-4">
            <h6 className="portfolio-appendix-group-title">F2F / Online Training (Wadhwani)</h6>
            <div className="portfolio-upload-grid">
              {renderAppendixCard('training_certificate', 'Certificate of Training')}
              {renderAppendixCard('training_test_result', 'Pre and Post Test Result (if applicable)')}
              {renderAppendixCard('training_documentation', 'Documentation of Training Proper (Photos)', 'image/*')}
            </div>
          </div>
          <div className="portfolio-appendix-group mb-4">
            <h6 className="portfolio-appendix-group-title">Certification Exam (Online / F2F)</h6>
            <div className="portfolio-upload-grid">
              {renderAppendixCard('exam_certificate', 'Certification')}
              {renderAppendixCard('exam_test_result', 'Pre and Post Test Result')}
              {renderAppendixCard('exam_documentation', 'Documentation of Exam Preparation (Photos)', 'image/*')}
            </div>
          </div>
          <div className="alert alert-light border small">
            <i className="fa fa-circle-info me-2 text-muted"></i>
            Optional — include only if you participated in Wadhwani or a certification exam program.
          </div>
        </div>
      )}

      <ConfirmModal
        open={deleteTargetId != null}
        title="Delete this file?"
        message="This portfolio attachment will be permanently removed. This cannot be undone."
        confirmLabel="Delete" cancelLabel="Cancel" variant="danger"
        loading={deleting} error={deleteError}
        onCancel={() => { if (deleting) return; setDeleteTargetId(null); setDeleteError(null) }}
        onConfirm={confirmDeleteFile}
      />
    </Layout>
  )
}

export default PortfolioBuilder

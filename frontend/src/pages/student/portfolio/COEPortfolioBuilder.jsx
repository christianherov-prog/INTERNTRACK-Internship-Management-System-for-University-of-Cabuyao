import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import api from '../../../services/api'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../../components/AuthenticatedFile'
import ConfirmModal from '../../../components/modals/ConfirmModal'
import { useConfirm } from '../../../contexts/ConfirmContext'

/** Per-field limit for Chapter III text areas. */
const CHAPTER3_MAX = 5000

/** Sample content tailored for BS Computer Engineering (Manufacturing/Electronics template). */
const SAMPLE_CONTENT = {
  company_name: 'NIDEC PHILIPPINES CORPORATION',
  company_address: '136 North Science Avenue, Laguna Technopark Special Economic Zone, Biñan, Laguna',
  company_background:
    'Nidec Philippines Corporation is a prominent subsidiary of the global Nidec Group, operating as a leading manufacturer of small precision motors, hard disk drive (HDD) spindle motors, and electronic assemblies. Its main manufacturing and production bases are situated in the Laguna Technopark in Biñan, Laguna.\n\nThey specialize in the development, manufacturing, and sales of small precision motors, automotive motors, home appliance motors, commercial and industrial motors, motors for machinery, electronic and optical components, and other related products.',
  problem: 
    'The issue that was observed during my internship at the company is the excessive utilization of manpower in the stage of assembly. This implies that the company is employing a greater number of workers than required to effectively complete the assembly tasks.',
  alternative_solutions: 
    '1. Implement lean manufacturing principles to identify and eliminate unnecessary waste, optimize workstations, and streamline workflows.\n2. Automation Integration: Consider the implementation of automation in specific repetitive and time-consuming assembly tasks.\n3. Conduct a comprehensive time and motion study to analyze worker, equipment, and material movement to identify inefficiencies.',
  design_solution: 
    'Out of the solutions that were mentioned, the one that is considered to be the best is the implementation of Lean Manufacturing.',
  conclusions: 
    'A significant problem that may have a negative effect on the business\'s productivity and cost-effectiveness is the assembly process\'s excessive use of labor. By applying Lean Manufacturing principles, the company can optimize its resources, simplify the assembly process, and enhance overall efficiency.',
  recommendation_students: 
    'Future student interns should learn about Lean Manufacturing and its application in industrial settings. By understanding waste elimination principles and methods, interns can identify inefficiencies and offer improvements.',
  recommendation_program: 
    'Lean Manufacturing modules or workshops can improve the internship program. Interns learn real-world skills in this internship program. The company and interns will benefit from this valuable learning experience.',
  recommendation_curriculum: 
    'Industrial process optimization, Lean Manufacturing, and other efficiency-improvement courses would benefit the students. This would aid internship preparation and help students understand industrial engineering concepts better.',
  recommendation_hte: 
    'The company should apply Lean Manufacturing principles to other areas besides assembly. To find and fix inefficiencies, encourage continuous improvement and process assessments. Automation may also improve resource utilization and productivity.'
}

function COEPortfolioBuilder() {
  const confirm = useConfirm()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [activeTab, setActiveTab] = useState('acknowledgement')

  const [form, setForm] = useState({
    company_name: '',
    company_address: '',
    acknowledgement: '',
    company_background: '',
    problem: '',
    alternative_solutions: '',
    design_solution: '',
    conclusions: '',
    recommendation_students: '',
    recommendation_program: '',
    recommendation_curriculum: '',
    recommendation_hte: ''
  })

  const fetchPortfolio = () => {
    setLoading(true)
    api.get('/student/portfolio')
      .then(res => {
        setData(res.data)
        const p = res.data.internship?.portfolio
        const i = res.data.internship
        if (p) {
          const custom = p.custom_fields || {};
          setForm({
            company_name: p.company_name ?? i?.company?.company_name ?? '',
            company_address: p.company_address ?? i?.company?.address ?? '',
            acknowledgement: custom.acknowledgement ?? '',
            company_background: p.company_background ?? custom.company_background ?? '',
            problem: custom.problem ?? '',
            alternative_solutions: custom.alternative_solutions ?? '',
            design_solution: custom.design_solution ?? '',
            conclusions: custom.conclusions ?? '',
            recommendation_students: custom.recommendation_students ?? '',
            recommendation_program: custom.recommendation_program ?? '',
            recommendation_curriculum: custom.recommendation_curriculum ?? '',
            recommendation_hte: custom.recommendation_hte ?? ''
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
      await api.post('/student/portfolio/builder', {
        company_name: form.company_name,
        company_address: form.company_address,
        company_background: form.company_background, 
        custom_fields: {
          acknowledgement: form.acknowledgement,
          problem: form.problem,
          alternative_solutions: form.alternative_solutions,
          design_solution: form.design_solution,
          conclusions: form.conclusions,
          recommendation_students: form.recommendation_students,
          recommendation_program: form.recommendation_program,
          recommendation_curriculum: form.recommendation_curriculum,
          recommendation_hte: form.recommendation_hte
        }
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

  const handleLogoUpload = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    if (!file.type.startsWith('image/') && !/\.(png|jpe?g|webp|gif|bmp)$/i.test(file.name)) {
      alert("Please upload an image file only (PNG, JPG, JPEG, WEBP, etc.).")
      e.target.value = ''
      return
    }
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

  const handleFileUpload = async (e, type) => {
    const file = e.target.files[0]
    if (!file) return
    if (!file.type.startsWith('image/') && !/\.(png|jpe?g|webp|gif|bmp)$/i.test(file.name)) {
      alert("Please upload a valid image file.")
      e.target.value = ''
      return
    }

    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)

    try {
      await api.post('/student/portfolio/photos', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      fetchPortfolio()
    } catch (err) {
      alert("Failed to upload file: " + (err.response?.data?.message || err.message))
    } finally {
      e.target.value = ''
    }
  }

  const [deletingItem, setDeletingItem] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const handleDeleteFileClick = (item) => {
    setDeletingItem(item)
  }

  const handleConfirmDeleteFile = async () => {
    if (!deletingItem) return
    setIsDeleting(true)
    try {
      await api.delete(`/student/portfolio/photos/${deletingItem.id}`)
      fetchPortfolio()
      setDeletingItem(null)
    } catch (err) {
      alert("Failed to delete file.")
    } finally {
      setIsDeleting(false)
    }
  }

  const p = data?.internship?.portfolio
  const photos = p?.photos || []
  const journals = data?.internship?.journals || []

  const textChecks = [
    { key: 'company_name', label: 'Host Company Name' },
    { key: 'company_address', label: 'Host Company Address' },
    { key: 'company_background', label: 'Company Description' },
    { key: 'problem', label: 'Problem' },
    { key: 'alternative_solutions', label: 'Alternative Solutions' },
    { key: 'design_solution', label: 'Design / Solution' },
    { key: 'conclusions', label: 'Conclusions' },
    { key: 'recommendation_students', label: 'Recommendation for Students' },
    { key: 'recommendation_program', label: 'Recommendation for Program' },
    { key: 'recommendation_curriculum', label: 'Recommendation for Curriculum' },
    { key: 'recommendation_hte', label: 'Recommendation for HTE' },
  ]
  
  const uploadChecks = [
    { type: 'approval_sheet', label: 'Approval Sheet' },
    { type: 'org_chart', label: '1.2. Organizational Chart' }, 
    { type: 'application_letter', label: '4.1. Application Letter' },
    { type: 'recommendation_letter', label: '4.2. Recommendation Letter' },
    { type: 'acceptance_form', label: '4.3. Student Internship Acceptance Form' },
    { type: 'completion_certificate', label: '4.4. Certificate of Completion of Training' },
    { type: 'moa', label: '4.5. Memorandum of Agreement' },
    { type: 'consent_form', label: '4.6. Student Internship Consent Form' },
    { type: 'medical_certificate', label: '4.7. Medical Certificate' },
    { type: 'psychological_certificate', label: '4.8. Psychological Certificate' },
    { type: 'work_samples', label: '4.9. Work Samples/Outcomes' },
    { type: 'ojt_photos', label: '4.10. Photos' },
    { type: 'supervisor_evaluation', label: '4.11. Supervisor\'s Evaluation' },
    { type: 'curriculum_vitae', label: '4.12. Curriculum Vitae' }
  ]

  const textDone = textChecks.filter(c => (form[c.key] || '').trim().length > 0).length
  const uploadsDone = uploadChecks.filter(c => photos.some(ph => ph.type === c.type)).length
  const uploadsTotal = uploadChecks.length
  const journalCount = journals.length
  const overallPct = Math.round(((textDone + uploadsDone) / (textChecks.length + uploadsTotal)) * 100)

  const renderFileList = (type, title, accept = "image/*", tip = "") => {
    const items = photos.filter(photo => photo.type === type) || []
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
                {items.map(item => (
                  <div key={item.id} className="portfolio-upload-file-row">
                    <div className="text-truncate flex-grow-1 me-2 small">
                      {item.file_path && item.file_path.endsWith('.pdf')
                        ? <i className="fa fa-file-pdf text-danger me-1"></i>
                        : <i className="fa fa-image text-primary me-1"></i>}
                      {item.label || 'Uploaded File'}
                    </div>
                    <div className="d-flex gap-1 flex-shrink-0">
                      <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}><i className="fa fa-eye"></i></AuthenticatedFileLink>
                      <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => handleDeleteFileClick(item)}><i className="fa fa-trash"></i></button>
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
            <input type="file" id={`upload-${type}`} className="d-none" accept={accept} multiple onChange={(e) => handleFileUpload(e, type)} />
            <label htmlFor={`upload-${type}`} className="btn btn-outline-primary btn-sm w-100 portfolio-upload-btn mb-0">
              <i className="fa fa-upload me-1"></i>{items.length > 0 ? 'Upload More' : 'Upload'}
            </label>
          </div>
        </div>
      </div>
    )
  }

  const renderEvaluationRow = (formType, formTitle) => {
    const ev = data?.internship?.evaluations?.find(e => e.form_type === formType)
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

  if (loading || !data) return <Layout title="My Portfolio" subtitle="Student" icon="fa-folder-plus" bodyClass="student-page"><div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div></Layout>

  return (
    <Layout title="My Portfolio" subtitle="Student" icon="fa-folder-plus" bodyClass="student-page">
      <div className="portfolio-builder">
        <div className="card shadow-sm border-0 mb-4" style={{ borderRadius: '12px', background: '#fff' }}>
          <div className="card-body p-3 p-lg-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div className="d-flex align-items-center gap-3 flex-wrap">
              <div className="d-flex align-items-center gap-2">
                <div className="rounded-3 bg-light text-primary d-flex align-items-center justify-content-center" style={{ width: 38, height: 38, fontSize: '1rem' }}>
                  <i className="fa fa-file-lines"></i>
                </div>
                <div>
                  <div className="fw-bold text-dark lh-1" style={{ fontSize: '1rem' }}>{textDone}/{textChecks.length}</div>
                  <div className="text-muted" style={{ fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Text Fields</div>
                </div>
              </div>
              <div className="vr d-none d-sm-block text-muted opacity-25" style={{ height: 30 }}></div>
              <div className="d-flex align-items-center gap-2">
                <div className="rounded-3 bg-light text-success d-flex align-items-center justify-content-center" style={{ width: 38, height: 38, fontSize: '1rem' }}>
                  <i className="fa fa-cloud-arrow-up"></i>
                </div>
                <div>
                  <div className="fw-bold text-dark lh-1" style={{ fontSize: '1rem' }}>{uploadsDone}/{uploadsTotal}</div>
                  <div className="text-muted" style={{ fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Uploads</div>
                </div>
              </div>
              <div className="vr d-none d-sm-block text-muted opacity-25" style={{ height: 30 }}></div>
              <div className="d-flex align-items-center gap-2">
                <div className="rounded-3 bg-light text-warning d-flex align-items-center justify-content-center" style={{ width: 38, height: 38, fontSize: '1rem' }}>
                  <i className="fa fa-book-bookmark"></i>
                </div>
                <div>
                  <div className="fw-bold text-dark lh-1" style={{ fontSize: '1rem' }}>{journalCount}</div>
                  <div className="text-muted" style={{ fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Journals</div>
                </div>
              </div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm px-3"
                onClick={async () => {
                  if (await confirm({ message: 'This will fill all empty text fields with sample content. Continue?' })) {
                    setForm(prev => ({
                      acknowledgement: prev.acknowledgement || 'I would like to express my gratitude...',
                      company_name: prev.company_name || 'NIDEC CORPORATION',
                      company_address: prev.company_address || 'Biñan City, Laguna',
                      company_background: prev.company_background || 'NIDEC CORPORATION is a global manufacturer of electric motors...',
                      problem: prev.problem || 'Production line motor balancing variance...',
                      alternative_solutions: prev.alternative_solutions || 'Implement automated sensor calibration...',
                      design_solution: prev.design_solution || 'Dynamic optical balancing test rig...',
                      conclusions: prev.conclusions || 'The proposed solution reduced variance by 34%...',
                      recommendation_students: prev.recommendation_students || 'Engage deeply in hands-on equipment testing...',
                      recommendation_program: prev.recommendation_program || 'Continue university partnership tracks...',
                      recommendation_curriculum: prev.recommendation_curriculum || 'Expand lab practical hours...',
                      recommendation_hte: prev.recommendation_hte || 'Provide cross-department rotations...',
                    }))
                  }
                }}
              >
                <i className="fa fa-wand-magic-sparkles me-1"></i>Fill Sample
              </button>
              <Link to="/student/portfolio/preview" className="btn btn-primary btn-sm px-3 shadow-sm">
                <i className="fa fa-eye me-1"></i>Preview Portfolio
              </Link>
            </div>
          </div>
        </div>

        <div className="d-flex justify-content-center mb-4">
          <div className="placement-tabs-bar">
            {[
              { key: 'acknowledgement', label: 'Acknowledgement', icon: 'fa-handshake' },
              { key: 'chapter1', label: 'Chapter I', icon: 'fa-building' },
              { key: 'chapter2', label: 'Chapter II', icon: 'fa-book-open' },
              { key: 'chapter3', label: 'Chapter III', icon: 'fa-clipboard-check' },
              { key: 'appendices', label: 'Appendices', icon: 'fa-folder-open' },
            ].map(tab => (
              <button
                key={tab.key}
                className={`placement-tab-btn${activeTab === tab.key ? ' active' : ''}`}
                onClick={(e) => { e.preventDefault(); setActiveTab(tab.key); }}
              >
                <i className={`fa ${tab.icon}`}></i>
                <span>{tab.label}</span>
              </button>
            ))}
          </div>
        </div>

        {activeTab === 'acknowledgement' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-handshake me-2 text-primary"></i>Acknowledgement</h6></div>
                <div className="p-3 p-lg-4">
                  <label className="portfolio-field-label">Acknowledgement Statement</label>
                  <textarea className="form-control portfolio-field-input" rows={6} placeholder="Express your gratitude to your mentors, supervisors, and department..." value={form.acknowledgement} onChange={e => setForm({ ...form, acknowledgement: e.target.value })}></textarea>
                </div>
              </div>
            </div>
            <div className="portfolio-form-actions">
              <button type="submit" className="btn btn-success px-5" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Text Fields</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'chapter1' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-building me-2 text-primary"></i>Chapter I: Background of the Company</h6></div>
                <div className="p-3 p-lg-4">
                  <div className="portfolio-hte-row mb-3">
                    <div>
                      <label className="portfolio-field-label">Host Company Name <span className="text-danger">*</span></label>
                      <input type="text" className="form-control portfolio-field-input" placeholder="e.g. NIDEC CORPORATION" value={form.company_name} onChange={e => setForm({ ...form, company_name: e.target.value })} />
                    </div>
                    <div>
                      <label className="portfolio-field-label">Host Company Address <span className="text-danger">*</span></label>
                      <input type="text" className="form-control portfolio-field-input" placeholder="e.g. Biñan City, Laguna" value={form.company_address} onChange={e => setForm({ ...form, company_address: e.target.value })} />
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="portfolio-field-label">1.1 Company Profile / Description</label>
                    <textarea className="form-control portfolio-field-input" rows={4} value={form.company_background} onChange={e => setForm({ ...form, company_background: e.target.value })}></textarea>
                  </div>
                </div>
              </div>
              <div className="content-card portfolio-tips-card">
                <div className="content-card-header bg-light">
                  <h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Chapter I Guidelines</h6>
                </div>
                <div className="p-3 p-lg-4">
                  <ul className="portfolio-tips-list">
                    <li>Write a detailed description of the company operations, products, and industry leadership.</li>
                    <li>Company name and address will appear on the final title page and official document headers.</li>
                  </ul>
                </div>
              </div>
            </div>
            <div className="portfolio-form-actions">
              <button type="submit" className="btn btn-success px-5" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Text Fields</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'chapter2' && (
          <div className="bg-white p-4 border rounded shadow-sm mb-4">
            <div className="row g-4">
              <div className="col-12 col-lg-6">
                <div className="content-card portfolio-chapter2-card h-100 mb-0">
                  <div className="content-card-header bg-light">
                    <h6 className="mb-0"><i className="fa fa-book-open me-2 text-primary"></i>Chapter II: Weekly Journals</h6>
                  </div>
                  <div className="p-3 p-lg-4 d-flex flex-column justify-content-between flex-grow-1">
                    <div>
                      <p className="portfolio-panel-text mb-3">
                        Weekly journals are pulled automatically from your Logbook into the portfolio PDF. You do not re-upload them here.
                      </p>
                      <div className="portfolio-chapter2-stat">
                        <strong>{journalCount}</strong>
                        <span>journal week{journalCount === 1 ? '' : 's'} ready for PDF</span>
                      </div>
                    </div>
                    <div className="mt-4 pt-3 border-top">
                      <Link to="/student/logbook" className="btn btn-outline-primary btn-sm">
                        <i className="fa fa-arrow-right me-1"></i> Manage journals
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
              <div className="col-12 col-lg-6">
                <div className="content-card portfolio-tips-card h-100 mb-0">
                  <div className="content-card-header bg-light">
                    <h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Chapter II Logbook Guidelines</h6>
                  </div>
                  <div className="p-3 p-lg-4">
                    <ul className="portfolio-tips-list">
                      <li>Ensure all weekly entries are submitted and reviewed by your supervisor.</li>
                      <li>Approved hours and remarks will flow directly into the final portfolio report.</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'chapter3' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light">
                  <h6 className="mb-0"><i className="fa fa-clipboard-check me-2 text-primary"></i>Chapter III: Assessment</h6>
                </div>
                <div className="p-3 p-lg-4">
                  <div className="portfolio-fields-2">
                    <div className="w-100 mb-2 mt-2"><h5 className="mb-0 fw-bold border-bottom pb-2">3.1 Problem and Its Solutions</h5></div>
                    {[
                      { key: 'problem', label: 'Problem' },
                      { key: 'alternative_solutions', label: 'Alternative Solutions' },
                      { key: 'design_solution', label: 'Design / Solution' },
                      { key: 'conclusions', label: 'Conclusions' },
                    ].map(field => (
                      <div key={field.key}>
                        <label className="portfolio-field-label" htmlFor={`ch3-${field.key}`}>{field.label}</label>
                        <textarea id={`ch3-${field.key}`} className="form-control portfolio-field-input" rows={4} value={form[field.key]} onChange={e => setForm({ ...form, [field.key]: e.target.value })}></textarea>
                      </div>
                    ))}
                    <div className="w-100 mb-2 mt-4"><h5 className="mb-0 fw-bold border-bottom pb-2">3.2 Recommendations</h5></div>
                    {[
                      { key: 'recommendation_students', label: 'a. Students' },
                      { key: 'recommendation_program', label: 'b. Internship Program' },
                      { key: 'recommendation_curriculum', label: 'c. Curriculum' },
                      { key: 'recommendation_hte', label: 'd. Host Training Establishment' },
                    ].map(field => (
                      <div key={field.key}>
                        <label className="portfolio-field-label" htmlFor={`ch3-${field.key}`}>{field.label}</label>
                        <textarea id={`ch3-${field.key}`} className="form-control portfolio-field-input" rows={3} value={form[field.key]} onChange={e => setForm({ ...form, [field.key]: e.target.value })}></textarea>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
              <div className="content-card portfolio-tips-card">
                <div className="content-card-header bg-light">
                  <h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Chapter III Guidelines</h6>
                </div>
                <div className="p-3 p-lg-4">
                  <ul className="portfolio-tips-list">
                    <li>Clearly formulate engineering problems observed during your internship and detail your proposed technical designs.</li>
                    <li>Provide specific recommendations for students, curriculum improvements, and host establishments.</li>
                  </ul>
                </div>
              </div>
            </div>
            <div className="portfolio-form-actions">
              <button type="submit" className="btn btn-success px-5" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Text Fields</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'appendices' && (
          <section className="portfolio-appendix-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="portfolio-appendix-section-head">
              <h5 className="mb-0 text-primary"><i className="fa fa-folder-open me-2"></i>Upload Requirements</h5>
              <span className="text-muted small">Upload scanned images for your portfolio.</span>
            </div>
            <div className="portfolio-appendix-group">
              <h6 className="portfolio-appendix-group-title">Company &amp; Preliminary Documents</h6>
              <div className="portfolio-upload-grid">
                {renderFileList('company_logo', 'Company Logo (HTE)', 'image/*', 'Appears on the Cover Page & Header.')}
                {renderFileList('approval_sheet', 'Approval Sheet', "image/*", "Upload your signed Approval Sheet here.")}
                {renderFileList('org_chart', '1.2. Organizational Chart', "image/*", "Upload your host company organizational chart.")}
              </div>
            </div>
            <div className="portfolio-appendix-group">
              <h6 className="portfolio-appendix-group-title">Chapter IV: Pertinent Documents</h6>
              <div className="portfolio-upload-grid">
                {renderFileList('application_letter', '4.1. Application Letter')}
                {renderFileList('recommendation_letter', '4.2. Recommendation Letter')}
                {renderFileList('acceptance_form', '4.3. Acceptance Form')}
                {renderFileList('completion_certificate', '4.4. Certificate of Completion')}
                {renderFileList('moa', '4.5. Memorandum of Agreement')}
                {renderFileList('consent_form', '4.6. Internship Consent Form')}
                {renderFileList('medical_certificate', '4.7. Medical Certificate')}
                {renderFileList('psychological_certificate', '4.8. Psychological Certificate')}
                {renderFileList('work_samples', '4.9. Work Samples/Outcomes', "image/*", "Upload screenshots or images of your outcomes")}
                {renderFileList('ojt_photos', '4.10. Photos', "image/*", "Upload your general OJT pictures")}
                {renderFileList('supervisor_evaluation', '4.11. Supervisor\'s Evaluation')}
                {renderFileList('curriculum_vitae', '4.12. Curriculum Vitae')}
              </div>
            </div>
            <div className="portfolio-appendix-group mt-5 pt-3 border-top">
              <h6 className="portfolio-appendix-group-title text-primary"><i className="fa fa-clipboard-check me-2"></i>Evaluations Status</h6>
              <div className="table-responsive">
                <table className="table table-bordered table-hover bg-white mb-0" style={{ fontSize: '0.9rem' }}>
                  <thead className="table-light">
                    <tr>
                      <th style={{ width: '70%' }}>Evaluation Form</th>
                      <th className="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {renderEvaluationRow('FO-24', 'Performance Evaluation Form PNC: AA-FO-24')}
                    {renderEvaluationRow('FO-03', 'HTE Evaluation To University Internship Program PNC AA-FO-03')}
                    {renderEvaluationRow('FO-22', 'Internship Host Training Establishment Evaluation Form PNC AA-FO-22')}
                    {renderEvaluationRow('FO-23', 'Internship Program Evaluation Form PNC AA-FO-23')}
                  </tbody>
                </table>
              </div>
              <p className="text-muted small mt-2 mb-0">
                <i className="fa fa-info-circle me-1"></i> These evaluations are automatically included in the final portfolio preview based on online submissions by your supervisor and coordinator.
              </p>
            </div>
          </section>
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

export default COEPortfolioBuilder
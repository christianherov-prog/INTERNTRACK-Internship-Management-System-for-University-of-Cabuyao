import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import api from '../../../services/api'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../../components/AuthenticatedFile'
import ConfirmModal from '../../../components/modals/ConfirmModal'

/** Per-field limit for Chapter III (now dynamically paginated across A4 sheets without clipping). */
const CHAPTER3_MAX = 5000

const CHAPTER3_FIELDS = [
  { key: 'prof_ethical_responsibilities', label: 'Professional, Ethical, and Legal Responsibilities' },
  { key: 'things_learned', label: 'Things I Learned as a Future IT Professional' },
  { key: 'experience_with_people', label: 'My Experience with People Around Me' },
  { key: 'industry_best_practices', label: 'Industry-aligned Best Practices and Standards I Learned' },
  { key: 'recommendations', label: 'My Recommendation for Improvement of the Internship Program' },
  { key: 'advice', label: 'My Advice to Those Who Will Take Their Internship in the Near Future' },
]

/** Sample content for BS IT students completing 500-hr OJT (TechCorp PH template). */
const SAMPLE_CONTENT = {
  company_name: 'TechCorp PH',
  company_address: 'Alabang, Muntinlupa City, Metro Manila, Philippines',
  company_background:
    'Established in 2018 in the bustling commercial district of Alabang, Muntinlupa, TechCorp PH was founded by a team of experienced software architects and systems analysts who recognized the growing demand for digital transformation among small and medium-sized enterprises (SMEs) and corporate clients throughout Metro Manila and South Luzon. What began as a boutique software consultancy firm with a core team of five engineers has since evolved into a comprehensive IT solutions provider employing over fifty skilled IT professionals. TechCorp PH has built a strong reputation for delivering enterprise-grade web development, cloud migration services, and custom enterprise resource planning (ERP) systems across multiple industries including retail, healthcare, and logistics.\n\nTechCorp PH specializes in three core digital service pillars: Custom Software and Web Development, Cloud IT Infrastructure and DevOps, and Technical Support and Quality Assurance Consulting. Their development team designs interactive, responsive web applications using modern full-stack frameworks — including React, Vue, Node.js, and Laravel/PHP — backed by relational and NoSQL databases. Their IT infrastructure division handles cloud server deployment, network security management, and automated CI/CD pipelines. Additionally, TechCorp PH provides rigorous software quality assurance, vulnerability assessment, and legacy system modernization to ensure high availability, data integrity, and seamless user experiences for all client platforms.',
  company_vision:
    'To be the premier digital transformation partner and IT solutions provider in the Philippines, driving technological innovation and empowering enterprises through cutting-edge, secure, and scalable software architectures that elevate global competitiveness.',
  company_mission:
    'TechCorp PH is committed to delivering robust, customized information technology solutions, responsive web applications, and mission-critical cloud infrastructure. We strive to foster an environment of continuous technical excellence, ethical computing, and client-centric collaboration — solving complex business challenges while nurturing the next generation of Filipino IT professionals.',
  prof_ethical_responsibilities:
    'During my 500-hour internship at TechCorp PH, I developed a profound appreciation for the immense weight of professional, ethical, and legal responsibilities that guide the information technology industry. Working in a commercial development environment where real client data is processed daily, I witnessed firsthand the critical importance of strict adherence to the Data Privacy Act of 2012 (R.A. 10173). I learned that responsible computing extends far beyond writing functional code; it requires implementing secure coding practices such as input sanitization, parameterized queries to prevent SQL injection attacks, and proper role-based access control (RBAC) to safeguard sensitive user information from unauthorized access. Ethically, I was trained on the importance of transparency in reporting software bugs and security vulnerabilities without attempting to conceal technical debt or known system flaws, as honesty and accountability are foundational to the trust that clients place in technology firms. I also came to understand the legal dimensions of software development — from software licensing compliance and intellectual property rights to contractual obligations tied to service-level agreements. This practicum instilled in me the understanding that as a future IT professional, my duty is not only to innovate and deliver functional systems, but to act as a rigorous guardian of digital privacy, intellectual property, and system integrity, ensuring that technology serves society fairly, transparently, and securely.',
  things_learned:
    'My technical proficiency and conceptual understanding of software engineering expanded significantly throughout my time at TechCorp PH. Bridging the gap between academic theory at the University of Cabuyao and real industry practice, I gained hands-on experience navigating the complete Software Development Life Cycle (SDLC) within an Agile and Scrum framework. I learned how to translate complex business requirements and user stories into functional system modules and clean, responsive UI/UX components using modern web technologies including React, JavaScript, HTML5, CSS3, and backend RESTful APIs. I mastered essential industry-standard tools and workflows, including version control using Git and GitHub for collaborative branching, pull requests, and code merging, as well as API testing and debugging using Postman. Beyond syntax and coding, I developed the critical skills of debugging complex application flows, reading server error logs, and writing clean, maintainable code that adheres to the organization\'s established coding standards and style guides. I also deepened my understanding of database architecture, learning to design normalized relational schemas and write efficient SQL queries for production-grade applications. This comprehensive exposure transformed my technical confidence, bridging my academic foundation with robust, production-level IT competencies that I could not have gained solely through classroom instruction.',
  experience_with_people:
    'The collaborative working environment at TechCorp PH provided an invaluable foundation for developing my interpersonal communication and professional conduct. Interacting daily with senior software engineers, project managers, quality assurance testers, and fellow interns reinforced my understanding that successful software development is fundamentally a team effort requiring open dialogue, mutual respect, and shared accountability. During our daily stand-up meetings and bi-weekly sprint reviews, I learned how to articulate my technical progress clearly, report blockers and challenges effectively, and accept constructive feedback from supervisors with maturity and a growth mindset. When collaborative challenges arose — such as merge conflicts in shared code repositories or disagreements on implementation approaches — my peers and I resolved them through professional discussion, peer code reviews, and cooperative problem-solving sessions. The mentorship I received from senior developers was particularly instrumental in shaping my professional identity; they guided me not only on technical best practices but also on workplace etiquette, time management, and the importance of proactive communication. This rich interpersonal experience reinforced my conviction that empathy, teamwork, and articulate communication are just as vital to a successful IT career as technical expertise and coding ability.',
  industry_best_practices:
    'My practicum at TechCorp PH exposed me to rigorous industry-aligned development standards that elevate software products from simple scripts to scalable, maintainable enterprise solutions. I was trained in the application of structured software architectures, particularly the Model-View-Controller (MVC) pattern and component-based frontend design principles, which enforce separation of concerns and promote code reusability across large-scale projects. I learned to apply SOLID design principles and modular programming techniques to keep codebases clean, coherent, and easily extendable for future development cycles. In terms of documentation standards, I was introduced to formal technical documentation practices including comprehensive README files, API endpoint schemas in Swagger/OpenAPI format, and meaningful inline code comments — all essential for ensuring seamless project handoffs between team members. Quality assurance was another critical standard emphasized throughout my training; I participated in unit testing, cross-browser compatibility validation, and mobile responsiveness testing before any feature could be submitted for a pull request review. Our team adhered strictly to CI/CD deployment pipelines using automated build and test triggers, ensuring every code release met predefined quality gates before reaching the staging or production environment.',
  recommendations:
    'To further strengthen the already valuable practicum experience for future student trainees, I wish to offer constructive recommendations addressed both to the College of Computing Studies at Pamantasan ng Cabuyao and to our host establishment, TechCorp PH. For the university, I strongly recommend incorporating more specialized pre-internship workshops focusing on practical version control workflows — specifically advanced Git branching strategies, pull request best practices, and collaborative code review techniques — as well as hands-on exposure to industry frameworks such as React, Laravel, and RESTful API development prior to deployment. Familiarity with these tools through structured laboratory exercises in a simulated corporate environment would significantly reduce the onboarding learning curve students face during their critical first weeks at the host establishment. For TechCorp PH, I recommend establishing a formalized peer-mentorship pairing system, wherein each intern is explicitly assigned to a junior or mid-level developer who serves as a dedicated technical buddy for day-to-day questions and guidance. While the senior supervisors at TechCorp PH are exceptionally supportive, a structured mentorship pairing would streamline technical onboarding, encourage more frequent knowledge-sharing dialogues, and further enrich the overall learning experience for intern developers throughout their deployment period.',
  advice:
    'To my fellow students at the University of Cabuyao who are preparing to embark on their IT practicum in the coming semesters, my foremost advice is to approach the experience with intentional preparation, unwavering curiosity, and proactive time management from the very beginning. Do not wait for your first day on the job to review your core programming concepts, database management principles, and version control fundamentals; building a solid working knowledge of Git, basic web development technologies, and at least one server-side framework beforehand will give you tremendous confidence and allow you to contribute meaningfully from the start. During your internship, never hesitate to ask questions or honestly admit when you are stuck — but always make a genuine effort to solve the problem on your own first, as supervisors and senior developers deeply value interns who demonstrate initiative, resourcefulness, and an authentic eagerness to learn. Master the discipline of time management by accurately tracking your daily hours, maintaining a detailed and honest logbook of your daily tasks and learnings, and carefully balancing your project deadlines with your academic submission requirements. Build authentic professional relationships with your supervisors, fellow interns, and colleagues, as the network you cultivate during your OJT will be one of the most enduring and valuable outcomes of your practicum. Above all, embrace every challenge, setback, and piece of feedback as a stepping stone rather than an obstacle; your internship is your bridge from being a student to becoming a competent, confident, and ethically grounded IT professional, so absorb every lesson with humility, dedication, and a genuine passion for the craft.',
}

function PortfolioBuilder() {
  const [data, setData] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [activeTab, setActiveTab] = useState('chapter1')

  const [form, setForm] = useState({
    company_name: '',
    company_address: '',
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
    api.get('/student/portfolio')
      .then(res => {
        setData(res.data)
        const p = res.data.internship?.portfolio
        if (p) {
          setForm({
            company_name: p.company_name ?? res.data.internship?.company?.company_name ?? '',
            company_address: p.company_address ?? res.data.internship?.company?.address ?? '',
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

  const handleFileUpload = async (e, type, requiresWeek = false, requiresLabel = false) => {
    const file = e.target.files[0]
    if (!file) return
    const isImage = file.type.startsWith('image/') || /\.(png|jpe?g|webp|gif|bmp)$/i.test(file.name)
    const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name)

    if (type === 'company_logo' && !isImage) {
      alert("Please upload an image file only for Company Logo (PNG, JPG, JPEG, WEBP).")
      e.target.value = ''
      return
    }

    if (!isImage && !isPdf) {
      alert("Please upload a valid image or PDF document.")
      e.target.value = ''
      return
    }

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
    { type: 'vision_mission', label: 'Company Vision & Mission Image' },
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
    { type: 'dtr_form', label: 'PNC:AA-FO-30 DTR (manual form upload)' },
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

  const renderFileList = (type, title, requiresWeek = false, requiresLabel = false, accept = "image/*,.png,.jpg,.jpeg,.webp,.gif,.pdf", tip = "") => {
    const items = p?.photos?.filter(photo => photo.type === type) || []

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
                      {requiresWeek && <span className="badge bg-primary me-1">W{item.week_number}</span>}
                      {item.file_path && item.file_path.endsWith('.pdf')
                        ? <i className="fa fa-file-pdf text-danger me-1"></i>
                        : <i className="fa fa-image text-primary me-1"></i>}
                      {item.label || 'Uploaded'}
                    </div>
                    <div className="d-flex gap-1 flex-shrink-0">
                      <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}>
                        <i className="fa fa-eye"></i>
                      </AuthenticatedFileLink>
                      <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => handleDeleteFileClick(item)}>
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
            <input type="file" id={`upload-${type}`} className="d-none" accept={accept} onChange={(e) => handleFileUpload(e, type, requiresWeek, requiresLabel)} />
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

  return (
    <Layout title="My Portfolio" subtitle="Student" icon="fa-folder-plus" bodyClass="student-page">
      <div className="portfolio-builder">
        {/* ── Top Hero Summary Header ── */}
        <div className="card shadow-sm border-0 mb-4" style={{ borderRadius: '12px', background: '#fff' }}>
          <div className="card-body p-3 p-lg-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            {/* Left stats */}
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

            {/* Right actions */}
            <div className="d-flex align-items-center gap-2">
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm px-3"
                onClick={() => {
                  if (window.confirm('This will fill all empty text fields with professional sample content. Fields that already have content will not be overwritten. Continue?')) {
                    setForm(prev => ({
                      company_name: prev.company_name || SAMPLE_CONTENT.company_name,
                      company_address: prev.company_address || SAMPLE_CONTENT.company_address,
                      company_background: prev.company_background || SAMPLE_CONTENT.company_background,
                      company_vision: prev.company_vision || SAMPLE_CONTENT.company_vision,
                      company_mission: prev.company_mission || SAMPLE_CONTENT.company_mission,
                      prof_ethical_responsibilities: prev.prof_ethical_responsibilities || SAMPLE_CONTENT.prof_ethical_responsibilities,
                      things_learned: prev.things_learned || SAMPLE_CONTENT.things_learned,
                      experience_with_people: prev.experience_with_people || SAMPLE_CONTENT.experience_with_people,
                      industry_best_practices: prev.industry_best_practices || SAMPLE_CONTENT.industry_best_practices,
                      recommendations: prev.recommendations || SAMPLE_CONTENT.recommendations,
                      advice: prev.advice || SAMPLE_CONTENT.advice,
                    }))
                  }
                }}
              >
                <i className="fa fa-wand-magic-sparkles me-1"></i>Fill Sample
              </button>
              <Link to="/student/portfolio/preview" className="btn btn-primary btn-sm px-3 shadow-sm" title="Web Preview (Draft Mode Available)">
                <i className="fa fa-eye me-1"></i>Preview Portfolio
              </Link>
            </div>
          </div>
        </div>

        {/* ── Segment Tab Bar (Centered) ── */}
        <div className="d-flex justify-content-center mb-4">
          <div className="placement-tabs-bar">
            {[
              { key: 'chapter1',    label: 'Chapter I',               icon: 'fa-building' },
              { key: 'chapter2',    label: 'Chapter II',              icon: 'fa-book-open' },
              { key: 'chapter3',    label: 'Chapter III',             icon: 'fa-pen-ruler' },
              { key: 'appendices',  label: 'Appendices & Evaluations', icon: 'fa-folder-open' },
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

        {/* ── Chapter I Tab ── */}
        {activeTab === 'chapter1' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-building me-2 text-primary"></i>Chapter I: Host Company Profile</h6></div>
                <div className="p-3 p-lg-4">
                  {/* HTE Name + Address */}
                  <div className="portfolio-hte-row mb-3">
                    <div>
                      <label className="portfolio-field-label">Host Company Name <span className="text-danger">*</span></label>
                      <input
                        type="text"
                        className="form-control portfolio-field-input"
                        placeholder="e.g. ACME Technologies Inc."
                        value={form.company_name}
                        onChange={e => setForm({ ...form, company_name: e.target.value })}
                      />
                      <div className="text-muted" style={{ fontSize: '11px', marginTop: '3px' }}>Appears on the portfolio title page &amp; header.</div>
                    </div>
                    <div>
                      <label className="portfolio-field-label">Host Company Address <span className="text-danger">*</span></label>
                      <input
                        type="text"
                        className="form-control portfolio-field-input"
                        placeholder="e.g. Biñan City, Laguna"
                        value={form.company_address}
                        onChange={e => setForm({ ...form, company_address: e.target.value })}
                      />
                      <div className="text-muted" style={{ fontSize: '11px', marginTop: '3px' }}>Appears on the portfolio title page.</div>
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="portfolio-field-label">Company Background / History</label>
                    <textarea
                      className="form-control portfolio-field-input"
                      rows={3}
                      placeholder="Describe the company's background, establishment, core industry, and achievements."
                      value={form.company_background}
                      onChange={e => setForm({ ...form, company_background: e.target.value })}
                    ></textarea>
                  </div>
                  <div className="mb-3 p-3 bg-light rounded border">
                    <div className="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                      <span className="fw-bold text-dark"><i className="fa fa-image me-2 text-primary"></i>Company Vision &amp; Mission Image (Optional)</span>
                      <span className="badge bg-secondary">Alternative to text</span>
                    </div>
                    <p className="text-muted small mb-2">
                      If your host company has an official image or graphic poster of their Vision and Mission, you can upload it below. When uploaded, the portfolio PDF will display the graphic instead of the text fields below.
                    </p>
                    <div className="mt-2">
                      {renderFileList('vision_mission', 'Vision & Mission Image', false, false, 'image/*')}
                    </div>
                  </div>
                  <div className="portfolio-fields-2">
                    <div>
                      <label className="portfolio-field-label">Company Vision (Text)</label>
                      <textarea className="form-control portfolio-field-input" rows={3} placeholder="Leave empty if uploading an image above" value={form.company_vision} onChange={e => setForm({ ...form, company_vision: e.target.value })}></textarea>
                    </div>
                    <div>
                      <label className="portfolio-field-label">Company Mission (Text)</label>
                      <textarea className="form-control portfolio-field-input" rows={3} placeholder="Leave empty if uploading an image above" value={form.company_mission} onChange={e => setForm({ ...form, company_mission: e.target.value })}></textarea>
                    </div>
                  </div>
                </div>
              </div>

              {/* Dedicated Full-Width Writing Tips Card for Chapter I */}
              <div className="content-card portfolio-tips-card">
                <div className="content-card-header bg-light">
                  <h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Chapter I Writing Guidelines &amp; Tips</h6>
                </div>
                <div className="p-3 p-lg-4">
                  <ul className="portfolio-tips-list">
                    <li><strong>Company Background:</strong> Write in cohesive paragraph form detailing company history, products/services, and target client base.</li>
                    <li><strong>Vision &amp; Mission:</strong> You may either type the statements into the text fields or upload the official poster image.</li>
                    <li><strong>Formatting:</strong> The company name and address are automatically formatted onto the generated cover sheet and document headers.</li>
                    <li><strong>Saving:</strong> Click "Save Text Fields" below to commit your draft. Changes are saved immediately and updated in the PDF preview.</li>
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

        {/* ── Chapter II Tab ── */}
        {activeTab === 'chapter2' && (
          <div className="bg-white p-4 border rounded shadow-sm mb-4">
            <div className="row g-4">
              <div className="col-12 col-lg-6">
                <div className="content-card portfolio-chapter2-card h-100 mb-0">
                  <div className="content-card-header bg-light">
                    <h6 className="mb-0"><i className="fa fa-book-open me-2 text-primary"></i>Chapter II: Weekly Journals (PNC:AA-FO-31)</h6>
                  </div>
                  <div className="p-3 p-lg-4 d-flex flex-column justify-content-between flex-grow-1">
                    <div>
                      <p className="portfolio-panel-text mb-3">
                        Weekly <strong>PNC:AA-FO-31</strong> journals and supervisor signatures are synchronized automatically from your Logbook into the portfolio PDF. You do not need to re-upload FO-31 sheets here.
                      </p>
                      <div className="portfolio-chapter2-stat">
                        <strong>{journalCount}</strong>
                        <span>journal week{journalCount === 1 ? '' : 's'} ready for PDF</span>
                      </div>
                    </div>
                    <div className="mt-4 pt-3 border-top">
                      <Link to="/student/logbook" className="btn btn-outline-primary btn-sm">
                        <i className="fa fa-arrow-right me-1"></i>
                        {journalCount === 0 ? 'Go to Logbook' : 'Manage Weekly Journals'}
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
                      <li><strong>Automatic Inclusion:</strong> Every submitted weekly journal in your Logbook is formatted sequentially into Chapter II of the PDF.</li>
                      <li><strong>Supervisor Review:</strong> Ensure your weekly journals are reviewed and approved so supervisor remarks and ratings appear in the final report.</li>
                      <li><strong>Attachments:</strong> Weekly photos uploaded during daily timekeeping are compiled alongside their respective week entries.</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ── Chapter III Tab ── */}
        {activeTab === 'chapter3' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light">
                  <h6 className="mb-0"><i className="fa fa-pen-ruler me-2 text-primary"></i>Chapter III: Assessment of the Program</h6>
                </div>
                <div className="p-3 p-lg-4">
                  <div className="portfolio-chapter3-note mb-3">
                    <i className="fa fa-circle-info"></i>
                    <span>
                      Each answer supports up to <strong>{CHAPTER3_MAX} characters</strong>. With automatic A4 pagination enabled, long essays will dynamically reflow across multiple sheets without overlapping or clipping.
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

              {/* Dedicated Full-Width Writing Tips Card for Chapter III */}
              <div className="content-card portfolio-tips-card">
                <div className="content-card-header bg-light">
                  <h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-warning"></i>Chapter III Assessment &amp; Essay Guidelines</h6>
                </div>
                <div className="p-3 p-lg-4">
                  <ul className="portfolio-tips-list">
                    <li><strong>Specific OJT Examples:</strong> Reference actual tools, frameworks, programming languages, and team workflows you utilized during internship.</li>
                    <li><strong>Professional Growth:</strong> Detail both hard technical competencies and soft skills (communication, time management, ethics) acquired.</li>
                    <li><strong>Constructive Recommendations:</strong> Suggest actionable improvements for both the host training establishment and the university practicum curriculum.</li>
                    <li><strong>Saving:</strong> Click "Save Text Fields" below to record your essays.</li>
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

        {/* ── Appendices & Evaluations Tab ── */}
        {activeTab === 'appendices' && (
          <section className="portfolio-appendix-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="portfolio-appendix-section-head">
              <h5 className="mb-0 text-primary"><i className="fa fa-folder-open me-2"></i>Appendices &amp; Uploads</h5>
              <span className="text-muted small">Upload scanned/completed manual forms for your portfolio PDF. FO-31 journals come from Logbook; FO-30 DTR is uploaded below.</span>
            </div>

            {/* Company & OJT: Standard 4-card grid including unified Company Logo card */}
            <div className="portfolio-appendix-group">
              <h6 className="portfolio-appendix-group-title">Company &amp; OJT</h6>
              <div className="portfolio-upload-grid">
                {renderFileList('company_logo', 'Company Logo (HTE)', false, false, 'image/*', 'Appears on the PDF header and cover page. Clear PNG or JPG recommended.')}
                {renderFileList('org_chart', 'Organizational Chart', false, false, 'image/*', 'Upload your host company organizational structure chart.')}
                {renderFileList('vision_mission', 'Company Vision & Mission Image (Optional)', false, false, 'image/*', 'Upload an image of Vision & Mission if you prefer pictures instead of typing text in Chapter I.')}
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

            {/* Other Required Appendices: Balanced 2-column row eliminating empty grid space */}
            <div className="portfolio-appendix-group">
              <h6 className="portfolio-appendix-group-title">Other Required Appendices</h6>
              <div className="row g-3">
                <div className="col-12 col-md-6 col-lg-5">
                  {renderFileList(
                    'dtr_form',
                    'PNC:AA-FO-30 DTR (Manual Form Upload)',
                    false,
                    false,
                    'image/*,application/pdf',
                    'Fill the official FO-30 Daily Time Record offline, then upload the completed form.'
                  )}
                </div>
                <div className="col-12 col-md-6 col-lg-7">
                  <div className="content-card h-100 p-3 bg-light border-0 d-flex flex-column justify-content-center">
                    <div className="d-flex gap-2">
                      <i className="fa fa-circle-info text-primary mt-1 flex-shrink-0"></i>
                      <div className="small text-muted">
                        <strong className="text-dark d-block mb-1">Official PNC:AA-FO-30 Daily Time Record:</strong>
                        Complete and sign the official PNC:AA-FO-30 Daily Time Record with your supervisor offline, then upload the scanned copy on the left. Weekly FO-31 journal entries are pulled automatically from your Logbook into the generated portfolio PDF.
                      </div>
                    </div>
                  </div>
                </div>
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
                <i className="fa fa-info-circle me-1"></i> These evaluations are automatically included in the final portfolio preview based on online submissions by your supervisor and coordinator. They are always displayed in the generated PDF, appearing as blank templates if not yet started.
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

export default PortfolioBuilder

import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import Layout from '../../../components/Layout'
import api from '../../../services/api'
import { AuthenticatedFileImage, AuthenticatedFileLink } from '../../../components/AuthenticatedFile'
import ConfirmModal from '../../../components/modals/ConfirmModal'
import { useConfirm } from '../../../contexts/ConfirmContext'

function COEDPortfolioBuilder() {
  const confirm = useConfirm()
  const [data, setData] = useState(null)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState(null)
  const [activeTab, setActiveTab] = useState('chapter1')

  const [form, setForm] = useState({
    acknowledgement: '',
    teachers_prayer: '',
    teachers_creed: '',
    teaching_philosophy: '',
    why_teaching: '',
    beliefs_about_learners: '',
    goals_as_teacher: '',
    cooperating_school_history: '',
    deped_vision_mission: '',
    school_vision_mission: '',
    school_programs: '',
    learner_population: '',
    observation_logs: '',
    classroom_management: '',
    teaching_environment: '',
    culminating_reflection: '',
    strengths_discovered: '',
    areas_for_improvement: '',
    ready_for_profession: '',
    narrative_observation: '',
    narrative_assisted: '',
    narrative_independent: '',
    narrative_final_demo: '',
    highlight_growth: '',
  })

  const fetchPortfolio = () => {
    api.get('/student/portfolio')
      .then(res => {
        setData(res.data)
        const p = res.data.internship?.portfolio
        if (p) {
          const custom = p.custom_fields || {}
          setForm({
            acknowledgement: custom.acknowledgement || '',
            teachers_prayer: custom.teachers_prayer || "Lord, I pray that you would give me a heart that is completely devoted to You and to the students You have placed in my care...\n\n(Edit to add your personal 4-stanza prayer)",
            teachers_creed: custom.teachers_creed || "I Commit to My Students:\n...\n\nI Commit to Excellence in Teaching:\n...\n\nI Commit to Partnership:\n...\n\nI Commit to Myself as an Educator:\n...",
            teaching_philosophy: custom.teaching_philosophy || '',
            why_teaching: custom.why_teaching || '',
            beliefs_about_learners: custom.beliefs_about_learners || '',
            goals_as_teacher: custom.goals_as_teacher || '',
            cooperating_school_history: custom.cooperating_school_history || '',
            deped_vision_mission: custom.deped_vision_mission || "DepEd Vision:\nWe dream of Filipinos who passionately love their country and whose values and competencies enable them to realize their full potential and contribute meaningfully to building the nation.\n\nDepEd Mission:\nTo protect and promote the right of every Filipino to quality, equitable, culture-based, and complete basic education where:...",
            school_vision_mission: custom.school_vision_mission || '',
            school_programs: custom.school_programs || '',
            learner_population: custom.learner_population || '',
            observation_logs: custom.observation_logs || '',
            classroom_management: custom.classroom_management || '',
            teaching_environment: custom.teaching_environment || '',
            culminating_reflection: custom.culminating_reflection || '',
            strengths_discovered: custom.strengths_discovered || '',
            areas_for_improvement: custom.areas_for_improvement || '',
            ready_for_profession: custom.ready_for_profession || '',
            narrative_observation: custom.narrative_observation || '',
            narrative_assisted: custom.narrative_assisted || '',
            narrative_independent: custom.narrative_independent || '',
            narrative_final_demo: custom.narrative_final_demo || '',
            highlight_growth: custom.highlight_growth || '',
          })
        }
      })
      .catch(console.error)
  }

  useEffect(() => { fetchPortfolio() }, [])

  const setFormField = (key, value) => {
    setForm(prev => ({ ...prev, [key]: value }))
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    try {
      // Send empty defaults for required core fields if any, and pass the rest in custom_fields
      await api.post('/student/portfolio/builder', {
        custom_fields: form
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

  const handleFileUpload = async (e, type, requiresWeek = false, requiresLabel = false) => {
    const file = e.target.files[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)

    if (requiresWeek) {
      const week = window.prompt("Enter the Week Number for this file (e.g., 1, 2, 3...):")
      if (!week || isNaN(parseInt(week))) { alert("Please enter a valid week number."); e.target.value = ''; return }
      formData.append('week_number', parseInt(week))
    }

    if (requiresLabel) {
      const label = window.prompt(`Enter a label or title for this ${type.replace('_', ' ')}:`, file.name)
      if (label === null) { e.target.value = ''; return }
      formData.append('label', label)
    }

    try {
      await api.post('/student/portfolio/photos', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      fetchPortfolio()
    } catch (err) {
      alert("Failed to upload: " + (err.response?.data?.message || err.message))
    } finally {
      e.target.value = ''
    }
  }

  const deletePhoto = async (id) => {
    if (await confirm("Are you sure you want to delete this file?")) {
      try {
        await api.delete(`/student/portfolio/photos/${id}`)
        fetchPortfolio()
      } catch (err) {
        alert("Failed to delete.")
      }
    }
  }

  if (!data) return (
    <Layout title="COED Portfolio Builder" subtitle="Loading..." icon="fa-folder" bodyClass="student-page">
      <div className="text-center py-5 mt-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
    </Layout>
  )

  const photos = data.photos || []

  const getFiles = (type) => photos.filter(p => p.type === type)

  const renderFileList = (type, title, requiresWeek = false, requiresLabel = false, accept = "image/*,.png,.jpg,.jpeg,.webp,.gif", tip = "") => {
    const items = getFiles(type);

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
                      {requiresWeek && item.week_number && <span className="badge bg-primary me-2">Week {item.week_number}</span>}
                      {item.file_path && item.file_path.endsWith('.pdf')
                        ? <i className="fa fa-file-pdf text-danger me-1"></i>
                        : <i className="fa fa-image text-primary me-1"></i>}
                      {item.label || item.file_name}
                    </div>
                    <div className="d-flex gap-1 flex-shrink-0">
                      <AuthenticatedFileLink path={item.file_path} className="btn btn-outline-secondary btn-sm" style={{ padding: '0.1rem 0.35rem' }}>
                        <i className="fa fa-eye"></i>
                      </AuthenticatedFileLink>
                      <button type="button" className="btn btn-outline-danger btn-sm" style={{ padding: '0.1rem 0.35rem' }} onClick={() => deletePhoto(item.id)}>
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
              <i className="fa fa-cloud-arrow-up me-1"></i> Upload File
            </label>
          </div>
        </div>
      </div>
    );
  }

  const renderTextArea = (key, label, rows = 5, placeholder = "") => (
    <div className="mb-4">
      <label className="form-label fw-bold">{label}</label>
      <textarea
        className="form-control bg-white border shadow-sm"
        rows={rows}
        value={form[key]}
        onChange={e => setFormField(key, e.target.value)}
        placeholder={placeholder}
      />
    </div>
  )

  const textFields = [
    'acknowledgement', 'teachers_prayer', 'teachers_creed', 'teaching_philosophy', 'why_teaching', 'beliefs_about_learners',
    'goals_as_teacher', 'cooperating_school_history', 'deped_vision_mission', 'school_vision_mission', 'school_programs', 'learner_population',
    'observation_logs', 'classroom_management', 'teaching_environment', 'culminating_reflection', 'strengths_discovered',
    'areas_for_improvement', 'ready_for_profession', 'narrative_observation', 'narrative_assisted', 'narrative_independent',
    'narrative_final_demo', 'highlight_growth'
  ];
  const textDone = textFields.filter(k => (form[k] || '').trim().length > 10).length;

  const uploadKeys = ['updated_resume', 'application_letter', 'org_chart', 'lesson_plan', 'instructional_materials', 'worksheets', 'assessment_tools', 'student_outputs', 'experience_photos', 'endorsement_letter', 'acceptance_letter', 'training_plan', 'evaluation_forms', 'certificate_completion', 'clearance'];
  const uploadsTotal = uploadKeys.length;
  const uploadsDone = uploadKeys.filter(type => photos.some(p => p.type === type)).length;

  const tabs = [
    { key: 'chapter1', label: 'Preliminaries', icon: 'fa-home' },
    { key: 'chapter2', label: 'Intro & Profile', icon: 'fa-building' },
    { key: 'chapter3', label: 'Documentation', icon: 'fa-chalkboard-teacher' },
    { key: 'chapter4', label: 'Reflections', icon: 'fa-lightbulb' },
    { key: 'chapter5', label: 'Appendices', icon: 'fa-paperclip' }
  ];

  return (
    <Layout title="My Portfolio" subtitle="Student" icon="fa-folder-plus" bodyClass="student-page">
      <div className="portfolio-builder">
        {/* ✨ Top Hero Summary Header ✨ */}
        <div className="card shadow-sm border-0 mb-4" style={{ borderRadius: '12px', background: '#fff' }}>
          <div className="card-body p-3 p-lg-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            {/* Left stats */}
            <div className="d-flex align-items-center gap-3 flex-wrap">
              <div className="d-flex align-items-center gap-2">
                <div className="rounded-3 bg-light text-primary d-flex align-items-center justify-content-center" style={{ width: 38, height: 38, fontSize: '1rem' }}>
                  <i className="fa fa-file-lines"></i>
                </div>
                <div>
                  <div className="fw-bold text-dark lh-1" style={{ fontSize: '1rem' }}>{textDone}/{textFields.length}</div>
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
                  <i className="fa fa-percent"></i>
                </div>
                <div>
                  <div className="fw-bold text-dark lh-1" style={{ fontSize: '1rem' }}>
                    {Math.round(((textDone + uploadsDone) / (textFields.length + uploadsTotal)) * 100)}%
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Completion</div>
                </div>
              </div>
            </div>

            {/* Right actions */}
            <div className="d-flex align-items-center gap-2">
              <Link to="/student/portfolio/preview" className="btn btn-outline-primary shadow-sm" style={{ fontWeight: 600, padding: '0.5rem 1rem' }}>
                <i className="fa fa-eye me-2"></i>Preview PDF
              </Link>
              <button className="btn btn-primary shadow-sm" style={{ fontWeight: 600, padding: '0.5rem 1rem' }} onClick={handleSave} disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Progress</>}
              </button>
            </div>
          </div>
        </div>

        {message && (
          <div className={`alert alert-${message.type} alert-dismissible fade show shadow-sm`} role="alert">
            <i className={`fa fa-${message.type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2`}></i>
            {message.text}
            <button type="button" className="btn-close" onClick={() => setMessage(null)}></button>
          </div>
        )}

        {/* 🧩 Custom Horizontal Tabs 🧩 */}
        <div className="d-flex justify-content-center mb-4">
          <div className="placement-tabs-bar">
            {tabs.map(tab => (
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

        {/* --- Content Area --- */}
        {activeTab === 'chapter1' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-home me-2 text-primary"></i>Preliminaries</h6></div>
                <div className="p-3 p-lg-4">
                  <div className="alert alert-info border-0 shadow-sm mb-4">
                    <h6 className="alert-heading fw-bold"><i className="fa fa-university me-2"></i>PNC Vision, Mission & Core Values</h6>
                    <p className="mb-0 small">This section will automatically include the PNC Vision, Mission, and Core Values (Personal Dignity, Nurturing Community, Commitment to Excellence) in your printed portfolio.
                      adasdadadasdasdadas
                    </p>
                  </div>

                  <div className="row g-4">
                    <div className="col-12 col-md-6">
                      <div className="mb-3">
                        <label className="portfolio-field-label">Teacher's Prayer</label>
                        <textarea className="form-control portfolio-field-input" rows="5" value={form.teachers_prayer || ""} onChange={e => setFormField('teachers_prayer', e.target.value)}></textarea>
                      </div>
                      <div className="mb-3">
                        <label className="portfolio-field-label">Acknowledgement</label>
                        <textarea className="form-control portfolio-field-input" rows="5" value={form.acknowledgement || ""} onChange={e => setFormField('acknowledgement', e.target.value)}></textarea>
                      </div>
                    </div>
                    <div className="col-12 col-md-6">
                      <div className="mb-3">
                        <label className="portfolio-field-label">Teacher's Creed / Personal Teaching Commitment (4 Pillars)</label>
                        <textarea className="form-control portfolio-field-input" rows="12" value={form.teachers_creed || ""} onChange={e => setFormField('teachers_creed', e.target.value)} placeholder="I Commit to My Students...&#10;&#10;I Commit to Excellence in Teaching...&#10;&#10;I Commit to Partnership...&#10;&#10;I Commit to Myself as an Educator..."></textarea>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">Required Documents</h6>
                <div className="row g-3">
                  <div className="col-12 col-md-6">{renderFileList('updated_resume', 'Updated Resume')}</div>
                  <div className="col-12 col-md-6">{renderFileList('application_letter', 'Application Letter')}</div>
                </div>
              </div>
            </div>

            <div className="d-flex justify-content-end mt-4 pt-3 border-top">
              <button type="submit" className="btn btn-primary px-4 py-2" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Preliminaries</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'chapter2' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-building me-2 text-primary"></i>Intro & Profile</h6></div>
                <div className="p-3 p-lg-4">
                  <div className="row g-4">
                    <div className="col-12 col-md-6">
                      <h6 className="text-muted fw-bold mb-3">I. Introduction</h6>
                      <div className="mb-3"><label className="portfolio-field-label">A. Personal Teaching Philosophy</label><textarea className="form-control portfolio-field-input" rows="4" value={form.teaching_philosophy || ""} onChange={e => setFormField('teaching_philosophy', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">B. Why I Chose Teaching as a Profession</label><textarea className="form-control portfolio-field-input" rows="4" value={form.why_teaching || ""} onChange={e => setFormField('why_teaching', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">C. My Beliefs about Learners and Learning</label><textarea className="form-control portfolio-field-input" rows="4" value={form.beliefs_about_learners || ""} onChange={e => setFormField('beliefs_about_learners', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">D. My Goals as a Future Elementary Teacher</label><textarea className="form-control portfolio-field-input" rows="4" value={form.goals_as_teacher || ""} onChange={e => setFormField('goals_as_teacher', e.target.value)}></textarea></div>
                    </div>
                    <div className="col-12 col-md-6">
                      <h6 className="text-muted fw-bold mb-3">II. School Profile</h6>
                      <div className="mb-3"><label className="portfolio-field-label">A. Brief History of the Cooperating School</label><textarea className="form-control portfolio-field-input" rows="4" value={form.cooperating_school_history || ""} onChange={e => setFormField('cooperating_school_history', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">B. DepEd Vision & Mission</label><textarea className="form-control portfolio-field-input" rows="4" value={form.deped_vision_mission || ""} onChange={e => setFormField('deped_vision_mission', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">C. School Vision and Mission (Optional)</label><textarea className="form-control portfolio-field-input" rows="3" value={form.school_vision_mission || ""} onChange={e => setFormField('school_vision_mission', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">D. School Programs and Initiatives</label><textarea className="form-control portfolio-field-input" rows="4" value={form.school_programs || ""} onChange={e => setFormField('school_programs', e.target.value)} placeholder="e.g., Remedial reading Aral Program, drop-out prevention"></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">E. Description of Learner Population</label><textarea className="form-control portfolio-field-input" rows="4" value={form.learner_population || ""} onChange={e => setFormField('learner_population', e.target.value)} placeholder="Enrollment summary by Grade/Gender"></textarea></div>
                    </div>
                  </div>
                </div>
              </div>
              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">Profile Uploads</h6>
                <div className="row g-3">
                  <div className="col-12 col-md-6">{renderFileList('org_chart', 'C. Organizational Structure (Image)')}</div>
                </div>
              </div>
            </div>
            <div className="d-flex justify-content-end mt-4 pt-3 border-top">
              <button type="submit" className="btn btn-primary px-4 py-2" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Intro & Profile</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'chapter3' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-chalkboard-teacher me-2 text-primary"></i>Documentation & Lesson Plans</h6></div>
                <div className="p-3 p-lg-4">
                  <div className="row g-4">
                    <div className="col-12">
                      <div className="mb-4">
                        <label className="portfolio-field-label">A. Observation and Participation Logs</label>
                        <div className="alert alert-info border-0 shadow-sm mb-0">
                          <p className="mb-0 small"><i className="fa fa-info-circle me-2"></i>Your Weekly Internship Journals (FO-31) from the Logbook will be automatically inserted here in the final PDF.</p>
                        </div>
                      </div>
                      <div className="mb-3"><label className="portfolio-field-label">Classroom Management Practices</label><textarea className="form-control portfolio-field-input" rows="4" value={form.classroom_management || ""} onChange={e => setFormField('classroom_management', e.target.value)} placeholder="e.g., Hand signals, positive reinforcement, themed claps"></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">Teaching Environment</label><textarea className="form-control portfolio-field-input" rows="4" value={form.teaching_environment || ""} onChange={e => setFormField('teaching_environment', e.target.value)} placeholder="Layout, lighting, ventilation..."></textarea></div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">Lesson Plans</h6>
                <div className="row g-3">
                  <div className="col-12">{renderFileList('lesson_plan', 'Upload 5-10 Best Lesson Plans (English, Math, Science, AP, Filipino, MAPEH)', false, false, 'image/*', 'Include Objectives, Materials, Procedure, Assessment, Reflection')}</div>
                </div>
              </div>
            </div>
            <div className="d-flex justify-content-end mt-4 pt-3 border-top">
              <button type="submit" className="btn btn-primary px-4 py-2" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Documentation</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'chapter4' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-lightbulb me-2 text-primary"></i>Reflections & Artifacts</h6></div>
                <div className="p-3 p-lg-4">
                  <h6 className="text-muted fw-bold mb-3">VI. Culminating Reflection</h6>
                  <div className="mb-3"><label className="portfolio-field-label">How did internship shape me as a teacher?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.culminating_reflection || ""} onChange={e => setFormField('culminating_reflection', e.target.value)}></textarea></div>
                  <div className="mb-3"><label className="portfolio-field-label">What strengths did I discover?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.strengths_discovered || ""} onChange={e => setFormField('strengths_discovered', e.target.value)}></textarea></div>
                  <div className="mb-3"><label className="portfolio-field-label">What areas need improvement?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.areas_for_improvement || ""} onChange={e => setFormField('areas_for_improvement', e.target.value)}></textarea></div>
                  <div className="mb-3"><label className="portfolio-field-label">Am I ready for the teaching profession?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.ready_for_profession || ""} onChange={e => setFormField('ready_for_profession', e.target.value)}></textarea></div>
                </div>
              </div>

              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">V. Teaching Artifacts Uploads</h6>
                <div className="row g-3">
                  <div className="col-12 col-md-6">{renderFileList('instructional_materials', 'Instructional Materials Created (charts, PPT screenshots)')}</div>
                  <div className="col-12 col-md-6">{renderFileList('worksheets', 'Worksheets Developed')}</div>
                  <div className="col-12 col-md-6">{renderFileList('assessment_tools', 'Assessment Tools (quizzes, rubrics)')}</div>
                  <div className="col-12 col-md-6">{renderFileList('student_outputs', 'Sample Anonymized Student Outputs')}</div>
                </div>
              </div>
            </div>
            <div className="d-flex justify-content-end mt-4 pt-3 border-top">
              <button type="submit" className="btn btn-primary px-4 py-2" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Reflections</>}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'chapter5' && (
          <form onSubmit={handleSave} className="portfolio-form-section bg-white p-4 border rounded shadow-sm mb-4">
            <div className="d-flex flex-column gap-4">
              <div className="content-card portfolio-chapter-card border-0 shadow-none mb-0">
                <div className="content-card-header bg-light"><h6 className="mb-0"><i className="fa fa-paperclip me-2 text-primary"></i>Appendices</h6></div>
                <div className="p-3 p-lg-4">
                  <h6 className="text-muted fw-bold mb-3">VII. Experiences Narrative</h6>
                  <div className="row g-4">
                    <div className="col-12 col-md-6">
                      <div className="mb-3"><label className="portfolio-field-label">Phase 1: Observation Phase</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_observation || ""} onChange={e => setFormField('narrative_observation', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">Phase 2: Assisted Teaching Phase</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_assisted || ""} onChange={e => setFormField('narrative_assisted', e.target.value)}></textarea></div>
                    </div>
                    <div className="col-12 col-md-6">
                      <div className="mb-3"><label className="portfolio-field-label">Phase 3: Independent Teaching Phase</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_independent || ""} onChange={e => setFormField('narrative_independent', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">Phase 4: Final Demonstration Teaching</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_final_demo || ""} onChange={e => setFormField('narrative_final_demo', e.target.value)}></textarea></div>
                    </div>
                    <div className="col-12">
                      <div className="mb-3"><label className="portfolio-field-label">Highlight: Growth in confidence, Classroom management, Handling diverse learners</label><textarea className="form-control portfolio-field-input" rows="3" value={form.highlight_growth || ""} onChange={e => setFormField('highlight_growth', e.target.value)}></textarea></div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">VIII. Appendices Uploads</h6>
                <div className="row g-3">
                  <div className="col-12 col-md-6">{renderFileList('experience_photos', 'Upload Labeled Photos', false, true)}</div>
                  <div className="col-12 col-md-6">{renderFileList('endorsement_letter', 'Endorsement Letter')}</div>
                  <div className="col-12 col-md-6">{renderFileList('acceptance_letter', 'Internship Acceptance Letter')}</div>
                  <div className="col-12 col-md-6">{renderFileList('training_plan', 'Training Plan')}</div>
                  <div className="col-12 col-md-6">{renderFileList('evaluation_forms', 'Evaluation Forms (Midterm and Final)')}</div>
                  <div className="col-12 col-md-6">{renderFileList('certificate_completion', 'Certificate of Completion')}</div>
                  <div className="col-12 col-md-6">{renderFileList('clearance', 'Clearance')}</div>
                </div>
              </div>
            </div>
            <div className="d-flex justify-content-end mt-4 pt-3 border-top">
              <button type="submit" className="btn btn-primary px-4 py-2" disabled={saving}>
                {saving ? <><i className="fa fa-spinner fa-spin me-2"></i>Saving...</> : <><i className="fa fa-save me-2"></i>Save Appendices</>}
              </button>
            </div>
          </form>
        )}
      </div>
    </Layout>
  )
}

export default COEDPortfolioBuilder


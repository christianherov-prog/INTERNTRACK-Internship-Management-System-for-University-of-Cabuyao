import re

with open("src/pages/student/portfolio/COEDPortfolioBuilder.jsx", "r", encoding="utf-8") as f:
    content = f.read()

# Generate the new return block
new_return = """  const textFields = [
    'acknowledgement', 'teachers_creed', 'teaching_philosophy', 'why_teaching', 'beliefs_about_learners',
    'goals_as_teacher', 'cooperating_school_history', 'school_vision_mission', 'school_programs', 'learner_population',
    'observation_logs', 'classroom_management', 'teaching_environment', 'culminating_reflection', 'strengths_discovered',
    'areas_for_improvement', 'ready_for_profession', 'narrative_observation', 'narrative_assisted', 'narrative_independent',
    'narrative_final_demo', 'highlight_growth'
  ];
  const textDone = textFields.filter(k => (form[k] || '').trim().length > 10).length;

  const uploadKeys = ['updated_resume', 'application_letter', 'org_chart', 'lesson_plan', 'instructional_materials', 'worksheets', 'assessment_tools', 'student_outputs', 'experience_photos', 'endorsement_letter', 'acceptance_letter', 'training_plan', 'weekly_journal', 'evaluation_forms', 'certificate_completion', 'clearance'];
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

        {/* 📑 Custom Horizontal Tabs 📑 */}
        <div className="portfolio-tabs-container mb-4">
          <div className="portfolio-tabs">
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
                  <div className="row g-4">
                    <div className="col-12 col-md-6">
                      <div className="mb-3">
                        <label className="portfolio-field-label">Acknowledgement</label>
                        <textarea className="form-control portfolio-field-input" rows="5" value={form.acknowledgement} onChange={e => setFormField('acknowledgement', e.target.value)}></textarea>
                      </div>
                    </div>
                    <div className="col-12 col-md-6">
                      <div className="mb-3">
                        <label className="portfolio-field-label">Teacher's Creed / Personal Teaching Commitment</label>
                        <textarea className="form-control portfolio-field-input" rows="5" value={form.teachers_creed} onChange={e => setFormField('teachers_creed', e.target.value)}></textarea>
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
                      <div className="mb-3"><label className="portfolio-field-label">A. Personal Teaching Philosophy</label><textarea className="form-control portfolio-field-input" rows="4" value={form.teaching_philosophy} onChange={e => setFormField('teaching_philosophy', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">B. Why I Chose Teaching as a Profession</label><textarea className="form-control portfolio-field-input" rows="4" value={form.why_teaching} onChange={e => setFormField('why_teaching', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">C. My Beliefs about Learners and Learning</label><textarea className="form-control portfolio-field-input" rows="4" value={form.beliefs_about_learners} onChange={e => setFormField('beliefs_about_learners', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">D. My Goals as a Future Elementary Teacher</label><textarea className="form-control portfolio-field-input" rows="4" value={form.goals_as_teacher} onChange={e => setFormField('goals_as_teacher', e.target.value)}></textarea></div>
                    </div>
                    <div className="col-12 col-md-6">
                      <h6 className="text-muted fw-bold mb-3">II. School Profile</h6>
                      <div className="mb-3"><label className="portfolio-field-label">A. Brief History of the Cooperating School</label><textarea className="form-control portfolio-field-input" rows="4" value={form.cooperating_school_history} onChange={e => setFormField('cooperating_school_history', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">B. Vision and Mission</label><textarea className="form-control portfolio-field-input" rows="4" value={form.school_vision_mission} onChange={e => setFormField('school_vision_mission', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">D. School Programs and Initiatives</label><textarea className="form-control portfolio-field-input" rows="4" value={form.school_programs} onChange={e => setFormField('school_programs', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">E. Description of Learner Population</label><textarea className="form-control portfolio-field-input" rows="4" value={form.learner_population} onChange={e => setFormField('learner_population', e.target.value)}></textarea></div>
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
                      <div className="mb-3"><label className="portfolio-field-label">Observation and Participation Logs (Narrative)</label><textarea className="form-control portfolio-field-input" rows="4" value={form.observation_logs} onChange={e => setFormField('observation_logs', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">Classroom Management Practices</label><textarea className="form-control portfolio-field-input" rows="4" value={form.classroom_management} onChange={e => setFormField('classroom_management', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">Teaching Environment</label><textarea className="form-control portfolio-field-input" rows="4" value={form.teaching_environment} onChange={e => setFormField('teaching_environment', e.target.value)}></textarea></div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">Lesson Plans</h6>
                <div className="row g-3">
                  <div className="col-12">{renderFileList('lesson_plan', 'Upload 5-10 Best Lesson Plans (English, Math, Science, AP, Filipino, MAPEH)', false, false, 'image/*,application/pdf', 'Include Objectives, Materials, Procedure, Assessment, Reflection')}</div>
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
                  <div className="mb-3"><label className="portfolio-field-label">How did internship shape me as a teacher?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.culminating_reflection} onChange={e => setFormField('culminating_reflection', e.target.value)}></textarea></div>
                  <div className="mb-3"><label className="portfolio-field-label">What strengths did I discover?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.strengths_discovered} onChange={e => setFormField('strengths_discovered', e.target.value)}></textarea></div>
                  <div className="mb-3"><label className="portfolio-field-label">What areas need improvement?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.areas_for_improvement} onChange={e => setFormField('areas_for_improvement', e.target.value)}></textarea></div>
                  <div className="mb-3"><label className="portfolio-field-label">Am I ready for the teaching profession?</label><textarea className="form-control portfolio-field-input" rows="4" value={form.ready_for_profession} onChange={e => setFormField('ready_for_profession', e.target.value)}></textarea></div>
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
                      <div className="mb-3"><label className="portfolio-field-label">From Observation Phase</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_observation} onChange={e => setFormField('narrative_observation', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">To Assisted Teaching</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_assisted} onChange={e => setFormField('narrative_assisted', e.target.value)}></textarea></div>
                    </div>
                    <div className="col-12 col-md-6">
                      <div className="mb-3"><label className="portfolio-field-label">To Independent Teaching</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_independent} onChange={e => setFormField('narrative_independent', e.target.value)}></textarea></div>
                      <div className="mb-3"><label className="portfolio-field-label">To Final Demonstration Teaching</label><textarea className="form-control portfolio-field-input" rows="4" value={form.narrative_final_demo} onChange={e => setFormField('narrative_final_demo', e.target.value)}></textarea></div>
                    </div>
                    <div className="col-12">
                      <div className="mb-3"><label className="portfolio-field-label">Highlight: Growth in confidence, Classroom management, Handling diverse learners</label><textarea className="form-control portfolio-field-input" rows="3" value={form.highlight_growth} onChange={e => setFormField('highlight_growth', e.target.value)}></textarea></div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="portfolio-appendix-group">
                <h6 className="portfolio-appendix-group-title">VIII. Appendices Uploads</h6>
                <div className="row g-3">
                  <div className="col-12 col-md-6">{renderFileList('experience_photos', 'Upload Labeled Photos')}</div>
                  <div className="col-12 col-md-6">{renderFileList('endorsement_letter', 'Endorsement Letter')}</div>
                  <div className="col-12 col-md-6">{renderFileList('acceptance_letter', 'Internship Acceptance Letter')}</div>
                  <div className="col-12 col-md-6">{renderFileList('training_plan', 'Training Plan')}</div>
                  <div className="col-12 col-md-6">{renderFileList('weekly_journal', 'Weekly Journal')}</div>
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
"""

import re
# We need to replace `renderFileList = (type, title) => { ... }` with the fancy one from CCS.
fancy_renderFileList = """  const renderFileList = (type, title, requiresWeek = false, requiresLabel = false, accept = "image/*,.png,.jpg,.jpeg,.webp,.gif,.pdf", tip = "") => {
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
            <input type="file" id={`upload-${type}`} className="d-none" accept={accept} onChange={(e) => handleFileUpload(e, type, true)} />
            <label htmlFor={`upload-${type}`} className="btn btn-outline-primary btn-sm w-100 portfolio-upload-btn mb-0">
              <i className="fa fa-cloud-arrow-up me-1"></i> Upload File
            </label>
          </div>
        </div>
      </div>
    );
  }"""

# Replace `const renderFileList = ... }`
content = re.sub(r'const renderFileList = \(type, title\) => \{.*?\n    \}', fancy_renderFileList, content, flags=re.DOTALL)

# Remove `renderTextArea` since it's no longer used
content = re.sub(r'const renderTextArea = .*?\n    \)', '', content, flags=re.DOTALL)

# Replace the return block
content = re.sub(r'  return \(\n\s*<Layout.*?export default COEDPortfolioBuilder', new_return, content, flags=re.DOTALL)

with open("src/pages/student/portfolio/COEDPortfolioBuilder.jsx", "w", encoding="utf-8") as f:
    f.write(content)

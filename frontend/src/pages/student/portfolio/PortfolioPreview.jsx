import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import api from '../../../services/api'
import '../../../assets/css/portfolio-print.css'

const BACKEND_URL = 'http://127.0.0.1:8001'

function PortfolioPreview() {
  const [data, setData]     = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/student/portfolio')
      .then(res => setData(res.data))
      .catch(console.error)
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="d-flex align-items-center justify-content-center" style={{ height: '100vh' }}>
        <div className="text-center">
          <i className="fa fa-spinner fa-spin fa-3x text-success mb-3"></i>
          <p className="text-muted">Generating portfolio…</p>
        </div>
      </div>
    )
  }

  const p  = data?.internship?.portfolio
  const i  = data?.internship
  const u  = data?.user
  const sp = u?.student_profile

  // Student name: LASTNAME, FIRSTNAME M.
  const studentName = sp
    ? `${sp.last_name?.toUpperCase()}, ${sp.first_name?.toUpperCase()}${sp.middle_name ? ' ' + sp.middle_name[0].toUpperCase() + '.' : ''}`
    : '________________________'

  const section = sp?.section ?? '_________'

  const programTitle = (sp?.course_name ?? i?.program ?? '').includes('Computer Science')
    ? 'Bachelor of Science in Computer Science'
    : 'Bachelor of Science in Information Technology'

  const practicumCode = programTitle.includes('Computer Science')
    ? 'CSP115 - CS Practicum (300 hours)'
    : 'ITP113 - IT Practicum (500 hours)'

  const ayLabel = sp?.academic_year ? `A.Y. ${sp.academic_year} / ${sp.semester === 2 ? '2nd' : '1st'} SEMESTER` : 'A.Y. 2025–2026 / 2nd SEMESTER'

  const journals = i?.journals ?? []
  const photos = p?.photos || [];

  // Pagination calculation
  let currentPage = 1;
  const nextPg = () => currentPage++;
  const getPg = () => currentPage;

  // We need to calculate page numbers for the TOC
  // Let's do a dry run of the layout pages
  let tocPages = {
    ch1: 1,
    visionMission: 2,
    orgChart: p?.org_chart_path ? 3 : null,
    history: p?.org_chart_path ? 4 : 3,
  };
  
  let currentSimPage = tocPages.history + 1; // For Chapter 2 Title
  tocPages.ch2 = currentSimPage++;
  
  let journalPages = [];
  journals.forEach(j => {
    journalPages.push({ id: j.id, week: j.week_number, page: currentSimPage++ });
  });

  tocPages.ch3 = currentSimPage++; // Chapter 3 Title
  tocPages.ch3Content = currentSimPage; // first Chapter 3 content page
  currentSimPage += 3; // Chapter 3 is split across 3 A4 content pages
  
  tocPages.appendices = currentSimPage++; // Appendices Title

  const appendixForms = [
    { type: 'registration_form', label: 'Registration Form (Duly signed by the registrar)' },
    { type: 'medical_result', label: 'Medical Result' },
    { type: 'psychological_result', label: 'Psychological Test Result' },
    { type: 'application_letter', label: 'Application Letter' },
    { type: 'student_cv', label: 'Student Curriculum Vitae PNC-AA-FO-27' },
    { type: 'recommendation_request', label: 'Internship Host Establishment Request for Recommendation Letter PNC:AA-FO-26' },
    { type: 'acceptance_form', label: 'Student Internship Acceptance Form PNC:AA-FO-29' },
    { type: 'consent_form', label: 'Student Internship Consent Form PNC: AA-FO-28' },
    { type: 'training_plan', label: 'Internship Training Plan PNC: AA-FO-25.3' },
    { type: 'dtr_form', label: 'Student Internship Daily Time Record (DTR) Form PNC: AA-FO-30' },
    { type: 'performance_eval', label: 'Student Internship Performance Evaluation Form PNC: AA-FO-24' },
    { type: 'moa_document', label: 'Memorandum of Agreement' },
    { type: 'visitation_form', label: 'Internship / OJT Visitation Form' },
    { type: 'completion_certificate', label: 'Certification of Completion' },
    { type: 'hte_evaluation', label: 'Internship Host Training Establishment Evaluation Form PNC AA-FO-22' },
    { type: 'program_evaluation', label: 'Internship Program Evaluation Form PNC AA-FO-23' },
  ];

  let appendixPages = {};
  appendixForms.forEach(f => {
    const list = photos.filter(x => x.type === f.type);
    if (list.length > 0) {
      appendixPages[f.type] = currentSimPage;
      currentSimPage += list.length; // Assuming each takes roughly 1 page (might vary if many, but we enforce 1 a4-page per type group in renderPhotos)
      // Actually renderPhotos groups all of the same type into ONE a4-page right now. 
      // Let's match the logic: renderPhotos renders exactly 1 page per type.
      currentSimPage = appendixPages[f.type] + 1; 
    }
  });

  const ojtWeeks = [...new Set(photos.filter(x => x.type === 'ojt_photo').map(x => x.week_number))].sort((a,b)=>a-b);
  let ojtPages = {};
  ojtWeeks.forEach(w => {
    ojtPages[w] = currentSimPage++;
  });

  let wadhwaniPages = {};
  ['training_certificate', 'training_test_result', 'training_documentation'].forEach(t => {
    if (photos.some(x => x.type === t)) wadhwaniPages[t] = currentSimPage++;
  });

  let certPages = {};
  ['exam_certificate', 'exam_test_result', 'exam_documentation'].forEach(t => {
    if (photos.some(x => x.type === t)) certPages[t] = currentSimPage++;
  });


  const renderPhotos = (type, title, requiresWeek = false) => {
    const list = photos.filter(x => x.type === type);
    if (list.length === 0) return null;
    return (
        <div className="a4-page page-break portfolio-document position-relative">
            <PageHeader companyLogoPath={p?.company_logo_path} />
            <h5 style={{ fontWeight: 'bold', marginTop: '20px', textAlign: 'center' }}>{title}</h5>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '24px', marginTop: '20px', alignItems: 'center' }}>
            {list.map(photo => (
                <div key={photo.id} style={{ textAlign: 'center', width: '100%' }}>
                {photo.file_path.endsWith('.pdf') ? (
                    <div style={{ padding: '20px', border: '1px dashed #999', background: '#f9f9f9', width: '80%', margin: '0 auto' }}>
                        <p style={{ margin: 0, fontWeight: 'bold' }}>{photo.label || 'PDF Document'}</p>
                        <p className="small text-muted m-0" style={{ fontSize: '9pt' }}>[ Please insert physical PDF page here before final binding ]</p>
                    </div>
                ) : (
                    <img src={`${BACKEND_URL}/storage/${photo.file_path}`} alt={photo.label} style={{ maxWidth: '80%', maxHeight: '400px', objectFit: 'contain', border: '1px solid #ddd' }} />
                )}
                <p style={{ fontWeight: 'bold', marginTop: '6px', textIndent: '0', fontSize: '10pt' }}>
                    {requiresWeek && photo.week_number ? `Week ${photo.week_number} - ` : ''}
                    {photo.label}
                </p>
                </div>
            ))}
            </div>
            <div className="page-number">{nextPg()}</div>
        </div>
    )
  }

  return (
    <div style={{ background: '#e5e5e5', minHeight: '100vh', paddingBottom: '60px' }}>
      <div className="no-print" style={{
        position: 'sticky', top: 0, zIndex: 9999,
        background: '#1a1a2e', color: '#fff',
        padding: '10px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        boxShadow: '0 2px 8px rgba(0,0,0,0.4)'
      }}>
        <div className="d-flex align-items-center gap-3">
          <Link to="/student/portfolio" style={{ color: '#ccc', textDecoration: 'none', fontSize: '14px' }}>
            <i className="fa fa-arrow-left me-2"></i>Back to Builder
          </Link>
          <span style={{ color: '#555' }}>|</span>
          <span style={{ fontWeight: 600, fontSize: '15px' }}>Portfolio Preview</span>
        </div>
        <button
          onClick={() => window.print()}
          style={{
            background: '#16a34a', color: '#fff', border: 'none',
            padding: '8px 24px', borderRadius: '6px', fontWeight: 600, fontSize: '14px', cursor: 'pointer'
          }}>
          <i className="fa fa-file-pdf me-2"></i>Export to PDF
        </button>
      </div>

      {/* PAGE 1 */}
      <div className="a4-page page-break" style={{ backgroundImage: 'url(/images/cover-bg.jpg)', backgroundSize: 'cover', backgroundPosition: 'center', padding: 0, position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', top: '8%', right: '6%', textAlign: 'right', maxWidth: '55%' }}>
          <div style={{ fontFamily: '"Old English Text MT", "UnifrakturMaguntia", serif', color: '#1a5c2a', fontSize: '2rem', fontWeight: 'normal', lineHeight: 1.1 }}>University of Cabuyao</div>
          <div style={{ letterSpacing: '3px', fontSize: '10pt', fontWeight: 400, marginTop: '4px' }}>( P A M A N T A S A N &nbsp; N G &nbsp; C A B U Y A O )</div>
          <div style={{ fontSize: '8pt', marginTop: '4px' }}>Katapatan Mutual Homes, Brgy. Banay-Banay, City of Cabuyao, Laguna, Philippines 4025</div>
        </div>
        <div style={{ position: 'absolute', bottom: '30%', right: '6%', textAlign: 'right', maxWidth: '55%' }}>
          <div style={{ fontSize: '22pt', fontWeight: 'bold', color: '#fff', lineHeight: 1.2 }}>Internship Portfolio</div>
          <div style={{ fontSize: '12pt', color: '#fff', marginTop: '6px' }}>{ayLabel}</div>
          <div style={{ fontSize: '13pt', fontWeight: 'bold', color: '#fff', marginTop: '14px' }}>{studentName}</div>
          <div style={{ fontSize: '11pt', color: '#e5e5e5' }}>{section}</div>
        </div>
      </div>

      {/* PAGE 2 */}
      <div className="a4-page page-break" style={{ backgroundImage: 'url(/images/title-bg.jpg)', backgroundSize: 'cover', backgroundPosition: 'center', padding: 0, position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', bottom: '23%', left: '6%', textAlign: 'left', maxWidth: '50%' }}>
          <div style={{ fontSize: '13pt', fontWeight: 'bold', color: '#1a5c2a' }}>{practicumCode}</div>
          <div style={{ fontSize: '11pt', color: '#333', marginTop: '4px' }}>{ayLabel}</div>
          <div style={{ fontSize: '13pt', fontWeight: 'bold', color: '#222', marginTop: '12px' }}>{studentName}</div>
          <div style={{ fontSize: '11pt', color: '#333' }}>{section}</div>
        </div>
      </div>

      {/* PAGE 3 */}
      <div className="a4-page page-break portfolio-document position-relative" style={{ fontFamily: 'Arial, sans-serif', fontSize: '11pt' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
          <img src="/images/ccs-logo.png" alt="UC Seal" style={{ width: '80px', height: '80px', objectFit: 'contain' }} />
          <div style={{ textAlign: 'center', flex: 1, padding: '0 10px' }}>
            <div style={{ fontSize: '8pt' }}>Republic of the Philippines</div>
            <div style={{ fontFamily: 'Arial, sans-serif', color: '#1a5c2a', fontSize: '1.4rem', lineHeight: 1.1 }}>University of Cabuyao</div>
            <div style={{ fontSize: '9pt' }}>(Pamantasan ng cabuyao)</div>
            <div style={{ fontWeight: 'bold', fontStyle: 'italic', fontSize: '10pt' }}>COLLEGE OF COMPUTING STUDIES</div>
            <div style={{ fontSize: '8pt' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</div>
          </div>
          <div style={{ width: '100px', height: '80px', border: '1px solid transparent', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            {p?.company_logo_path && <img src={`${BACKEND_URL}/storage/${p.company_logo_path}`} alt="Company Logo" style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />}
          </div>
        </div>
        <div style={{ textAlign: 'center', marginTop: '30px' }}>
          <h4 style={{ fontWeight: 'bold', marginBottom: '24px' }}>A PRACTICUM REPORT</h4>
          <p style={{ textIndent: 0, marginBottom: '20px', textAlign: 'center' }}>A Narrative Report on the On-The-Job</p>
          <p style={{ textIndent: 0, marginBottom: '20px', textAlign: 'center' }}>
            undertaken at <strong style={{ fontStyle: 'italic' }}>{i?.company?.company_name || '___________________________'}</strong>,
          </p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>
            located at <strong style={{ fontStyle: 'italic' }}>{i?.company?.address || '___________________________'}</strong>
          </p>
        </div>
        <div style={{ textAlign: 'center', marginTop: '24px' }}>
          <p style={{ textIndent: 0, textAlign: 'center' }}>In partial fulfillment of the requirements for the course</p>
          <p style={{ textIndent: 0, fontWeight: 'bold', textAlign: 'center' }}>{practicumCode}</p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>For the Degree of</p>
          <p style={{ textIndent: 0, fontWeight: 'bold', textAlign: 'center' }}>{programTitle}</p>
        </div>
        <div style={{ textAlign: 'center', marginTop: '20px' }}>
          <p style={{ textIndent: 0, textAlign: 'center' }}>Presented to the faculty of Computing Studies</p>
          <p style={{ textIndent: 0, fontWeight: 'bold', textAlign: 'center' }}>COLLEGE OF COMPUTING STUDIES</p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>UNIVERSITY OF CABUYAO (PnC)</p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>Katapatan Homes Subdivision</p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>Banay-banay, City of Cabuyao, Laguna 4025</p>
        </div>
        <div style={{ textAlign: 'center', marginTop: '24px' }}>
          <p style={{ textIndent: 0, textAlign: 'center' }}>Submitted by:</p>
          <p style={{ textIndent: 0, fontWeight: 'bold', textAlign: 'center' }}>{studentName}</p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>{section}</p>
        </div>
        <div style={{ textAlign: 'center', marginTop: '20px' }}>
          <p style={{ textIndent: 0, textAlign: 'center' }}>Submitted to:</p>
          <p style={{ textIndent: 0, fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center' }}>
            {i?.faculty?.facultyProfile
              ? `Dr. ${i.faculty.facultyProfile.first_name} ${i.faculty.facultyProfile.last_name}`
              : '___________________________'}
          </p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>Internship Instructor</p>
          <p style={{ textIndent: 0, marginTop: '16px', fontWeight: 'bold', textAlign: 'center' }}>ASST. PROF. ARCELITO QUIATCHON</p>
          <p style={{ textIndent: 0, textAlign: 'center' }}>CCS Internship Coordinator</p>
          <p style={{ textIndent: 0, marginTop: '20px', textAlign: 'center' }}>{new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</p>
        </div>
        <div className="page-number">i</div>
      </div>

      {/* PAGE 4 */}
      <div className="a4-page page-break portfolio-document position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <p style={{ textAlign: 'right', fontWeight: 'bold', marginTop: '10px' }}>
          {new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
        </p>
        <p style={{ fontWeight: 'bold', textAlign: 'left', fontSize: '12pt' }}>Table of Contents <span style={{ fontStyle: 'italic', fontWeight: 'normal', fontSize: '10pt' }}>(Kindly add page numbers)</span></p>
        <ul className="toc-list" style={{ fontSize: '10.5pt', listStyleType: 'none', paddingLeft: 0 }}>
          <li><span className="fw-bold">CHAPTER I: INTRODUCTION</span><span>{tocPages.ch1}</span></li>
          <li className="ms-4"><span>Vision of UC</span><span>{tocPages.visionMission}</span></li>
          <li className="ms-4"><span>Mission of UC</span><span>{tocPages.visionMission}</span></li>
          <li className="ms-4"><span>Host Company Profile</span><span>{tocPages.visionMission}</span></li>
          <li className="ms-5"><span>Vision and Mission</span><span>{tocPages.visionMission}</span></li>
          {tocPages.orgChart && <li className="ms-5"><span>Organizational Chart</span><span>{tocPages.orgChart}</span></li>}
          <li className="ms-5"><span>History</span><span>{tocPages.history}</span></li>

          <li style={{ marginTop: '8px' }}><span className="fw-bold">CHAPTER II: WEEKLY PROGRESS REPORT</span><span>{tocPages.ch2}</span></li>
          {journalPages.map(j => (
            <li key={j.id} className="ms-4"><span>Week {j.week}</span><span>{j.page}</span></li>
          ))}

          <li style={{ marginTop: '8px' }}><span className="fw-bold">CHAPTER III: ASSESSMENT OF THE PROGRAM</span><span>{tocPages.ch3}</span></li>
          <li className="ms-4"><span>Professional and Ethical and Legal Responsibilities as Future IT Professionals</span><span>{tocPages.ch3Content}</span></li>
          <li className="ms-5"><span>Things I learned as future IT Professional</span><span>{tocPages.ch3Content}</span></li>
          <li className="ms-5"><span>My experience with people around me</span><span>{tocPages.ch3Content}</span></li>
          <li className="ms-5"><span>Industry-aligned best practices and standards I learned</span><span>{tocPages.ch3Content}</span></li>
          <li className="ms-4"><span>My recommendation for improvement of the Internship Program</span><span>{tocPages.ch3Content}</span></li>
          <li className="ms-4"><span>My advice to those who will take their internship in the near future</span><span>{tocPages.ch3Content}</span></li>

          <li style={{ marginTop: '8px' }}><span className="fw-bold">APPENDICES (ADDITIONAL DOCUMENTS AT THE END)</span><span>{tocPages.appendices}</span></li>
          {appendixForms.map(f => (
            appendixPages[f.type] && <li key={f.type} className="ms-4"><span>{f.label}</span><span>{appendixPages[f.type]}</span></li>
          ))}
          
          {ojtWeeks.length > 0 && <li className="ms-4"><span>Photos During OJT (Kindly add label and explanation)</span><span>{ojtPages[ojtWeeks[0]]}</span></li>}
          {ojtWeeks.map(w => (
            <li key={`toc-ojt-${w}`} className="ms-5"><span>Week {w}</span><span>{ojtPages[w]}</span></li>
          ))}

          {Object.keys(wadhwaniPages).length > 0 && <li className="ms-4"><span>ONLINE / F2F TRAINING (WADWHANI)</span><span></span></li>}
          {wadhwaniPages['training_certificate'] && <li className="ms-5"><span>CERTIFICATE OF TRAINING</span><span>{wadhwaniPages['training_certificate']}</span></li>}
          {wadhwaniPages['training_test_result'] && <li className="ms-5"><span>PRE AND POST TEST RESULT (If applicable)</span><span>{wadhwaniPages['training_test_result']}</span></li>}
          {wadhwaniPages['training_documentation'] && <li className="ms-5"><span>DOCUMENTATION OF TRAINING PROPER (PICTURES WITH DETAILED EXPLANATION)</span><span>{wadhwaniPages['training_documentation']}</span></li>}
          
          {Object.keys(certPages).length > 0 && <li className="ms-4"><span>CERTIFICATION EXAM (ONLINE / F2F)</span><span></span></li>}
          {certPages['exam_certificate'] && <li className="ms-5"><span>CERTIFICATION</span><span>{certPages['exam_certificate']}</span></li>}
          {certPages['exam_test_result'] && <li className="ms-5"><span>PRE AND POST TEST RESULT</span><span>{certPages['exam_test_result']}</span></li>}
          {certPages['exam_documentation'] && <li className="ms-5"><span>DOCUMENTATION OF DURING AND PREPARATION OF EXAM (PICTURES WITH DETAILED EXPLANATION)</span><span>{certPages['exam_documentation']}</span></li>}
        </ul>
        <div className="page-number">ii</div>
      </div>

      {/* CHAPTER I */}
      <div className="a4-page page-break portfolio-document d-flex align-items-center justify-content-center position-relative">
        <div style={{ textAlign: 'center' }}>
          <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', marginBottom: '30px' }}>CHAPTER I</h1>
          <h2 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '28pt' }}>INTRODUCTION</h2>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>
      <div className="a4-page page-break portfolio-document position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <div style={{ marginTop: '20px' }}>
          <h4 style={{ fontWeight: 'bold', textAlign: 'left' }}>Vision of UC</h4>
          <p>An institution of higher learning in Region IV. developing globally-competitive and value-laden professionals and leaders instrumental to community development and nation building.</p>
          <h4 style={{ fontWeight: 'bold', textAlign: 'left', marginTop: '20px' }}>Mission of UC</h4>
          <p>An institution of higher learning committed to equip individuals with knowledge, skills and values that will enable them to achieve professional goals & provide leadership and service for national development.</p>
          <h4 style={{ fontWeight: 'bold', textAlign: 'left', marginTop: '20px' }}>Host Company Profile</h4>
          <h5 style={{ fontWeight: 'bold', marginTop: '16px', textAlign: 'left' }}>Vision and Mission</h5>
          <p style={{ textIndent: '0.5in' }}><strong>Vision:</strong> {p?.company_vision ?? 'N/A'}</p>
          <p style={{ textIndent: '0.5in' }}><strong>Mission:</strong> {p?.company_mission ?? 'N/A'}</p>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>
      {p?.org_chart_path && (
        <div className="a4-page page-break portfolio-document position-relative">
          <PageHeader companyLogoPath={p?.company_logo_path} />
          <h5 style={{ fontWeight: 'bold', marginTop: '20px', textAlign: 'left' }}>Organizational Chart</h5>
          <div style={{ textAlign: 'center', marginTop: '20px' }}>
            <img src={`${BACKEND_URL}/storage/${p.org_chart_path}`} alt="Org Chart" style={{ maxWidth: '100%', maxHeight: '700px' }} />
          </div>
          <div className="page-number">{nextPg()}</div>
        </div>
      )}
      <div className="a4-page page-break portfolio-document position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <h5 style={{ fontWeight: 'bold', marginTop: '20px', textAlign: 'left' }}>History</h5>
        <p style={{ whiteSpace: 'pre-wrap' }}>{p?.company_background ?? 'No history provided.'}</p>
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* CHAPTER II */}
      <div className="a4-page page-break portfolio-document d-flex align-items-center justify-content-center position-relative">
        <div style={{ textAlign: 'center' }}>
          <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', marginBottom: '30px' }}>CHAPTER II</h1>
          <h2 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '28pt' }}>WEEKLY PROGRESS REPORT</h2>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>
      {journals.map((j) => (
        <div key={j.id} className="a4-page page-break portfolio-document position-relative text-center">
            <PageHeader companyLogoPath={p?.company_logo_path} />
            <h4 style={{ fontWeight: 'bold', marginTop: '20px', textAlign: 'left' }}>Week {j.week_number}</h4>
            <div style={{ marginTop: '20px', width: '100%', display: 'flex', justifyContent: 'center' }}>
            {j.file_path && j.file_path.endsWith('.pdf') ? (
                <div style={{ padding: '40px', border: '2px dashed #999', background: '#f9f9f9', width: '80%' }}>
                    <h5 className="fw-bold">PNC:AA-FO-31 Weekly Journal</h5>
                    <p className="small text-muted m-0">[ Insert physical PDF page here before final binding ]</p>
                </div>
            ) : (
                <img src={`${BACKEND_URL}/storage/${j.file_path}`} alt={`Week ${j.week_number} Journal`} style={{ maxWidth: '90%', maxHeight: '700px', objectFit: 'contain', border: '1px solid #ddd' }} />
            )}
            </div>
            <div className="page-number">{nextPg()}</div>
        </div>
      ))}

      {/* CHAPTER III
          Split across multiple A4 pages (2 sections each) so long answers
          do not overflow a single page and overlap the page number. */}
      <div className="a4-page page-break portfolio-document d-flex align-items-center justify-content-center position-relative">
        <div style={{ textAlign: 'center' }}>
          <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', marginBottom: '30px' }}>CHAPTER III</h1>
          <h2 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '28pt' }}>ASSESSMENT OF THE PROGRAM</h2>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {[
        [
          { title: 'Professional, Ethical, and Legal Responsibilities as Future IT Professionals', body: p?.prof_ethical_responsibilities, heading: 'h4' },
          { title: 'Things I Learned as a Future IT Professional', body: p?.things_learned, heading: 'h5' },
        ],
        [
          { title: 'My Experience with People Around Me', body: p?.experience_with_people, heading: 'h5' },
          { title: 'Industry-Aligned Best Practices and Standards I Learned', body: p?.industry_best_practices, heading: 'h5' },
        ],
        [
          { title: 'My Recommendation for Improvement of the Internship Program', body: p?.recommendations, heading: 'h4' },
          { title: 'My Advice to Those Who Will Take Their Internship in the Near Future', body: p?.advice, heading: 'h4' },
        ],
      ].map((sections, pageIdx) => (
        <div key={`ch3-page-${pageIdx}`} className="a4-page page-break portfolio-document position-relative">
          <PageHeader companyLogoPath={p?.company_logo_path} />
          <div className="chapter3-content" style={{ marginTop: '20px' }}>
            {sections.map((section, i) => {
              const Tag = section.heading
              return (
                <div key={section.title} className="chapter3-section" style={{ marginTop: i === 0 ? 0 : '22px' }}>
                  <Tag style={{ fontWeight: 'bold', textAlign: 'left', marginBottom: '8px' }}>{section.title}</Tag>
                  <p style={{ whiteSpace: 'pre-wrap', textAlign: 'justify' }}>{section.body?.trim() ? section.body : '___________________'}</p>
                </div>
              )
            })}
          </div>
          <div className="page-number">{nextPg()}</div>
        </div>
      ))}

      {/* APPENDICES */}
      <div className="a4-page page-break portfolio-document d-flex align-items-center justify-content-center position-relative">
        <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '48pt' }}>APPENDICES</h1>
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* Render all standard forms here */}
      {renderPhotos('registration_form', 'Registration Form')}
      {renderPhotos('medical_result', 'Medical Result')}
      {renderPhotos('psychological_result', 'Psychological Test Result')}
      {renderPhotos('application_letter', 'Application Letter')}
      {renderPhotos('student_cv', 'Student Curriculum Vitae PNC-AA-FO-27')}
      {renderPhotos('recommendation_request', 'Internship Host Establishment Request for Recommendation Letter PNC:AA-FO-26')}
      {renderPhotos('acceptance_form', 'Student Internship Acceptance Form PNC:AA-FO-29')}
      {renderPhotos('consent_form', 'Student Internship Consent Form PNC: AA-FO-28')}
      {renderPhotos('training_plan', 'Internship Training Plan PNC: AA-FO-25.3')}
      {renderPhotos('dtr_form', 'Student Internship Daily Time Record (DTR) Form PNC: AA-FO-30')}
      {renderPhotos('performance_eval', 'Student Internship Performance Evaluation Form PNC: AA-FO-24')}
      {renderPhotos('moa_document', 'Memorandum of Agreement')}
      {renderPhotos('visitation_form', 'Internship / OJT Visitation Form')}
      {renderPhotos('completion_certificate', 'Certification of Completion')}
      {renderPhotos('hte_evaluation', 'Internship Host Training Establishment Evaluation Form PNC AA-FO-22')}
      {renderPhotos('program_evaluation', 'Internship Program Evaluation Form PNC AA-FO-23')}

      {/* Render OJT Photos grouped by Week */}
      {ojtWeeks.map(w => {
         const list = photos.filter(x => x.type === 'ojt_photo' && x.week_number === w);
         return (
            <div key={`ojt-page-${w}`} className="a4-page page-break portfolio-document position-relative">
                <PageHeader companyLogoPath={p?.company_logo_path} />
                <h5 style={{ fontWeight: 'bold', marginTop: '20px', textAlign: 'left' }}>Week {w}</h5>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px', marginTop: '20px', alignItems: 'center' }}>
                {list.map(photo => (
                    <div key={photo.id} style={{ textAlign: 'center', width: '100%' }}>
                    {photo.file_path.endsWith('.pdf') ? (
                        <div style={{ padding: '20px', border: '1px dashed #999', background: '#f9f9f9', width: '80%', margin: '0 auto' }}>
                            <p style={{ margin: 0, fontWeight: 'bold' }}>{photo.label || 'PDF Document'}</p>
                            <p className="small text-muted m-0" style={{ fontSize: '9pt' }}>[ Insert physical PDF page here before final binding ]</p>
                        </div>
                    ) : (
                        <img src={`${BACKEND_URL}/storage/${photo.file_path}`} alt={photo.label} style={{ maxWidth: '80%', maxHeight: '400px', objectFit: 'contain', border: '1px solid #ddd' }} />
                    )}
                    <p style={{ fontWeight: 'bold', marginTop: '6px', textIndent: '0', fontSize: '10pt' }}>
                        {photo.label}
                    </p>
                    <p style={{ textIndent: '0.5in', fontSize: '10pt', marginTop: '4px' }}>{photo.description}</p>
                    </div>
                ))}
                </div>
                <div className="page-number">{nextPg()}</div>
            </div>
         )
      })}
      
      {/* Wadhwani / Training */}
      {Object.keys(wadhwaniPages).length > 0 && (
          <div className="a4-page page-break portfolio-document d-flex align-items-center justify-content-center position-relative">
            <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', textAlign: 'center' }}>ONLINE / F2F TRAINING (WADWHANI)</h1>
            <div className="page-number">{nextPg()}</div>
          </div>
      )}
      {renderPhotos('training_certificate', 'CERTIFICATE OF TRAINING')}
      {renderPhotos('training_test_result', 'PRE AND POST TEST RESULT (If applicable)')}
      {renderPhotos('training_documentation', 'DOCUMENTATION OF TRAINING PROPER (PICTURES WITH DETAILED EXPLANATION)')}

      {/* Certification Exam */}
      {Object.keys(certPages).length > 0 && (
          <div className="a4-page page-break portfolio-document d-flex align-items-center justify-content-center position-relative">
            <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', textAlign: 'center' }}>CERTIFICATION EXAM (ONLINE / F2F)</h1>
            <div className="page-number">{nextPg()}</div>
          </div>
      )}
      {renderPhotos('exam_certificate', 'CERTIFICATION')}
      {renderPhotos('exam_test_result', 'PRE AND POST TEST RESULT')}
      {renderPhotos('exam_documentation', 'DOCUMENTATION OF DURING AND PREPARATION OF EXAM (PICTURES WITH DETAILED EXPLANATION)')}

    </div>
  )
}

function PageHeader({ companyLogoPath }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '2px solid #15803d', paddingBottom: '8px', marginBottom: '4px' }}>
      <img src="/images/ccs-logo.png" alt="UC Seal" style={{ width: '60px', height: '60px', objectFit: 'contain' }} />
      <div style={{ textAlign: 'center', flex: 1 }}>
        <div style={{ fontSize: '8.5pt' }}>Republic of the Philippines</div>
        <div style={{ fontFamily: 'Arial, sans-serif', color: '#1a5c2a', fontSize: '18pt', lineHeight: 1.1 }}>University of Cabuyao</div>
        <div style={{ fontSize: '10pt', marginTop: '2px' }}>(Pamantasan ng cabuyao)</div>
        <div style={{ fontWeight: 'bold', fontStyle: 'italic', fontSize: '10pt', marginTop: '2px' }}>COLLEGE OF COMPUTING STUDIES</div>
        <div style={{ fontSize: '8pt' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</div>
      </div>
      <div style={{ width: '60px', height: '60px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        {companyLogoPath ? <img src={`${BACKEND_URL}/storage/${companyLogoPath}`} alt="Company Logo" style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} /> : <div style={{ width: '60px' }}></div>}
      </div>
    </div>
  )
}

export default PortfolioPreview

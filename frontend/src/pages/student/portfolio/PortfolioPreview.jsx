import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import PageError from '../../../components/PageError'
import api from '../../../services/api'
import { AuthenticatedFileImage } from '../../../components/AuthenticatedFile'
import '../../../assets/css/portfolio-print.css'

function PortfolioPreview() {
  const [data, setData]     = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]   = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/student/portfolio')
      .then(res => setData(res.data))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load portfolio.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

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

  if (error) {
    return (
      <div style={{ background: '#e5e5e5', minHeight: '100vh', padding: '24px' }}>
        <PageError message={error} onRetry={load} />
        <div className="text-center mt-3">
          <Link to="/student/portfolio" className="text-muted">Back to Builder</Link>
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
    { type: 'dtr_form', label: 'PNC:AA-FO-30 DTR (manual form upload) — Student Internship Daily Time Record' },
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
                    <AuthenticatedFileImage path={photo.file_path} alt={photo.label} style={{ maxWidth: '80%', maxHeight: '400px', objectFit: 'contain', border: '1px solid #ddd' }} />
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

      {/* PAGE 1 - Cover Page: bg image + text overlay at bottom-center */}
      <div className="a4-page page-break" style={{ backgroundImage: 'url(/images/cover-bg.jpg)', backgroundSize: '100% 100%', backgroundRepeat: 'no-repeat', padding: 0, position: 'relative', overflow: 'hidden' }}>
        <div style={{
          position: 'absolute',
          bottom: '14%',
          left: 0,
          width: '100%',
          textAlign: 'center',
        }}>
          <div style={{ fontFamily: 'Georgia, "Times New Roman", serif', fontSize: '26pt', fontWeight: 'bold', color: '#1a4731', letterSpacing: '1px' }}>
            Internship Portfolio
          </div>
          <div style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '11pt', fontWeight: 'bold', color: '#333', marginTop: '6px', letterSpacing: '2px' }}>
            {ayLabel}
          </div>
        </div>
      </div>

      {/* PAGE 2 - OJT Training Narrative page: bg image + text overlay at left */}
      <div className="a4-page page-break" style={{ backgroundImage: 'url(/images/title-bg.jpg)', backgroundSize: '100% 100%', backgroundRepeat: 'no-repeat', padding: 0, position: 'relative', overflow: 'hidden' }}>
        <div style={{
          position: 'absolute',
          top: '52%',
          left: '4%',
          width: '52%',
        }}>
          <div style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '11pt', fontWeight: 'bold', color: '#005a2b' }}>{practicumCode}</div>
          <div style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '10pt', fontWeight: 'bold', color: '#005a2b', marginTop: '2px' }}>{ayLabel}</div>
          <div style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '11pt', fontWeight: 'bold', color: '#1a1a1a', marginTop: '8px' }}>{studentName}</div>
          <div style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '10pt', fontWeight: 'bold', color: '#1a1a1a' }}>{section}</div>
        </div>
      </div>

      {/* PAGE 3 - Title Page: centered, CCS logo at bottom-left */}
      <div className="a4-page page-break position-relative" style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '11pt', color: '#000', padding: '50px 60px 60px 60px', display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center', textAlign: 'center' }}>
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center', textAlign: 'center', width: '100%' }}>
          <p style={{ margin: '0 0 12px 0', textIndent: 0 }}>A Narrative Report on the On-The-Job</p>
          <p style={{ margin: '0 0 12px 0', textIndent: 0 }}>
            undertaken at <strong style={{ fontStyle: 'italic' }}>{i?.company?.company_name || '(Name of THE)'}</strong>,
          </p>
          <p style={{ margin: '0 0 24px 0', textIndent: 0 }}>
            located at <strong style={{ fontStyle: 'italic' }}>{i?.company?.address || '(Address of THE)'}</strong>
          </p>

          <p style={{ margin: '0 0 8px 0', textIndent: 0 }}>In partial fulfillment of the requirements for the course</p>
          <p style={{ margin: '0 0 24px 0', fontWeight: 'bold', textIndent: 0 }}>{practicumCode}</p>

          <p style={{ margin: '0 0 8px 0', textIndent: 0 }}>For the Degree of</p>
          <p style={{ margin: '0 0 24px 0', fontWeight: 'bold', textIndent: 0 }}>{programTitle}</p>

          <p style={{ margin: '0 0 4px 0', textIndent: 0 }}>Presented to the faculty of Computing Studies</p>
          <p style={{ margin: '0 0 4px 0', fontWeight: 'bold', textIndent: 0 }}>COLLEGE OF COMPUTING STUDIES</p>
          <p style={{ margin: '0 0 4px 0', textIndent: 0 }}>UNIVERSITY OF CABUYAO (PnC)</p>
          <p style={{ margin: '0 0 24px 0', textIndent: 0 }}>Katapatan Homes Subdivision<br />Banay-banay, City of Cabuyao, Laguna 4025</p>

          <p style={{ margin: '0 0 4px 0', textIndent: 0 }}>Submitted by:</p>
          <p style={{ margin: '0 0 4px 0', fontWeight: 'bold', textIndent: 0 }}>{studentName}</p>
          <p style={{ margin: '0 0 24px 0', textIndent: 0 }}>{section}</p>

          <p style={{ margin: '0 0 4px 0', textIndent: 0 }}>Submitted to:</p>
          <p style={{ margin: '0 0 4px 0', fontStyle: 'italic', textIndent: 0 }}>
            {(() => {
              const fp = i?.faculty?.faculty_profile ?? i?.faculty?.facultyProfile
              return fp
                ? `${fp.first_name} ${fp.last_name}`
                : '(Name of Instructor / Moderator)'
            })()}
          </p>
          <p style={{ margin: '0 0 16px 0', textIndent: 0 }}>Internship Instructor</p>

          <p style={{ margin: '0 0 4px 0', fontWeight: 'bold', textIndent: 0 }}>ASST. PROF. ARCELITO QUIATCHON</p>
          <p style={{ margin: '0', textIndent: 0 }}>CCS Internship Coordinator</p>
        </div>

        <img src="/images/ccs-logo.png" alt="CCS Logo" style={{ position: 'absolute', bottom: '24px', left: '24px', width: '70px', objectFit: 'contain' }} />
        <div style={{ position: 'absolute', bottom: '15mm', right: '18mm', fontFamily: "'Times New Roman', Times, serif", fontSize: '11pt' }}>-i</div>
      </div>

      {/* PAGE 4 - Table of Contents with color-coded entries matching the template */}
      <div className="a4-page page-break position-relative" style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '11pt', color: '#000', padding: '40px 50px' }}>
        <h2 style={{ fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '16pt', fontWeight: 'bold', color: '#000', marginBottom: '20px' }}>
          Table of Contents <span style={{ fontWeight: 'normal', fontStyle: 'italic', fontSize: '12pt' }}>(Kindly add page numbers)</span>
        </h2>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '10pt' }}>
          <tbody>
            <tr>
              <td style={{ color: '#005a2b', fontWeight: 'bold', padding: '3px 0', paddingLeft: '0' }}>CHAPTER I: INTRODUCTION</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', width: '40px', color: '#005a2b', fontWeight: 'bold' }}>{tocPages.ch1}</td>
            </tr>
            <tr>
              <td style={{ color: '#1a5c8a', padding: '2px 0', paddingLeft: '20px' }}>Host Company Profile</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#1a5c8a' }}>{tocPages.visionMission}</td>
            </tr>
            <tr>
              <td style={{ color: '#8a6a00', padding: '2px 0', paddingLeft: '40px' }}>Vision and Mission</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#8a6a00' }}>{tocPages.visionMission}</td>
            </tr>
            {tocPages.orgChart && <tr>
              <td style={{ color: '#8a6a00', padding: '2px 0', paddingLeft: '40px' }}>Organizational Chart</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#8a6a00' }}>{tocPages.orgChart}</td>
            </tr>}
            <tr>
              <td style={{ color: '#8a6a00', padding: '2px 0', paddingLeft: '40px' }}>History</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#8a6a00' }}>{tocPages.history}</td>
            </tr>

            <tr><td style={{ height: '8px' }} /></tr>
            <tr>
              <td style={{ color: '#005a2b', fontWeight: 'bold', padding: '3px 0', paddingLeft: '0' }}>CHAPTER II: WEEKLY PROGRESS REPORT</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#005a2b', fontWeight: 'bold' }}>{tocPages.ch2}</td>
            </tr>

            <tr><td style={{ height: '8px' }} /></tr>
            <tr>
              <td style={{ color: '#005a2b', fontWeight: 'bold', padding: '3px 0', paddingLeft: '0' }}>CHAPTER III: ASSESSMENT OF THE PROGRAM</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#005a2b', fontWeight: 'bold' }}>{tocPages.ch3}</td>
            </tr>
            <tr>
              <td style={{ color: '#1a5c8a', padding: '2px 0', paddingLeft: '20px' }}>Professional and Ethical and Legal Responsibilities as Future IT Professionals</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#1a5c8a' }}>{tocPages.ch3Content}</td>
            </tr>
            <tr>
              <td style={{ color: '#8a6a00', padding: '2px 0', paddingLeft: '40px' }}>Things I learned as future IT Professional</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#8a6a00' }}>{tocPages.ch3Content}</td>
            </tr>
            <tr>
              <td style={{ color: '#8a6a00', padding: '2px 0', paddingLeft: '40px' }}>My experience with people around me</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#8a6a00' }}>{tocPages.ch3Content}</td>
            </tr>
            <tr>
              <td style={{ color: '#8a6a00', padding: '2px 0', paddingLeft: '40px' }}>Industry-aligned best practices and standards I learned</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#8a6a00' }}>{tocPages.ch3Content}</td>
            </tr>
            <tr>
              <td style={{ color: '#1a5c8a', padding: '2px 0', paddingLeft: '20px' }}>My recommendation for improvement of the Internship Program</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#1a5c8a' }}>{tocPages.ch3Content}</td>
            </tr>
            <tr>
              <td style={{ color: '#1a5c8a', padding: '2px 0', paddingLeft: '20px' }}>My advice to those who will take their internship in the near future</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#1a5c8a' }}>{tocPages.ch3Content}</td>
            </tr>

            <tr><td style={{ height: '8px' }} /></tr>
            <tr>
              <td style={{ color: '#005a2b', fontWeight: 'bold', padding: '3px 0', paddingLeft: '0' }}>APPENDICES (ADDITIONAL DOCUMENTS AT THE END)</td>
              <td style={{ textAlign: 'right', borderBottom: '1px dotted #999', color: '#005a2b', fontWeight: 'bold' }}>{tocPages.appendices}</td>
            </tr>
          </tbody>
        </table>
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
          <h4 style={{ fontWeight: 'bold', textAlign: 'left' }}>Host Company Profile</h4>
          {p?.company_profile && (
            <p style={{ whiteSpace: 'pre-wrap', textAlign: 'justify', marginTop: '10px' }}>{p.company_profile}</p>
          )}
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
            <AuthenticatedFileImage path={p.org_chart_path} alt="Org Chart" style={{ maxWidth: '100%', maxHeight: '700px' }} />
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
        <div key={j.id} className="a4-page page-break portfolio-document position-relative">
          <PageHeader companyLogoPath={p?.company_logo_path} />
          <div style={{ marginTop: '16px' }}>
            <h4 style={{ fontWeight: 'bold', textAlign: 'left', color: '#005a2b', borderBottom: '2px solid #005a2b', paddingBottom: '6px' }}>
              Week {j.week_number ?? j.entry_number}
              {j.date && <span style={{ fontWeight: 'normal', fontSize: '10pt', color: '#555', marginLeft: '12px' }}>{j.date}</span>}
            </h4>

            {/* Text-based journal entry */}
            {j.activities_summary && (
              <div style={{ marginTop: '12px' }}>
                <h6 style={{ fontWeight: 'bold', fontSize: '10pt', color: '#1a1a1a', marginBottom: '4px' }}>Activities / Tasks Performed:</h6>
                <p style={{ whiteSpace: 'pre-wrap', fontSize: '10pt', textAlign: 'justify', marginBottom: '10px' }}>{j.activities_summary}</p>
              </div>
            )}
            {j.learnings && (
              <div style={{ marginTop: '8px' }}>
                <h6 style={{ fontWeight: 'bold', fontSize: '10pt', color: '#1a1a1a', marginBottom: '4px' }}>What I Learned:</h6>
                <p style={{ whiteSpace: 'pre-wrap', fontSize: '10pt', textAlign: 'justify', marginBottom: '10px' }}>{j.learnings}</p>
              </div>
            )}
            {j.challenges && (
              <div style={{ marginTop: '8px' }}>
                <h6 style={{ fontWeight: 'bold', fontSize: '10pt', color: '#1a1a1a', marginBottom: '4px' }}>Challenges Encountered:</h6>
                <p style={{ whiteSpace: 'pre-wrap', fontSize: '10pt', textAlign: 'justify', marginBottom: '10px' }}>{j.challenges}</p>
              </div>
            )}
            {j.notes && (
              <div style={{ marginTop: '8px' }}>
                <h6 style={{ fontWeight: 'bold', fontSize: '10pt', color: '#1a1a1a', marginBottom: '4px' }}>Notes / Reflection:</h6>
                <p style={{ whiteSpace: 'pre-wrap', fontSize: '10pt', textAlign: 'justify', marginBottom: '10px' }}>{j.notes}</p>
              </div>
            )}

            {/* File attachment (uploaded form image or PDF) */}
            {j.file_path && (
              <div style={{ marginTop: '16px', width: '100%', display: 'flex', justifyContent: 'center' }}>
                {j.file_path.endsWith('.pdf') ? (
                  <div style={{ padding: '24px 40px', border: '2px dashed #999', background: '#f9f9f9', textAlign: 'center', width: '80%' }}>
                    <h6 className="fw-bold" style={{ marginBottom: '4px' }}>PNC:AA-FO-31 Daily Journal (manual form upload)</h6>
                    <p className="small text-muted m-0">[ Insert physical PDF page here before final binding ]</p>
                  </div>
                ) : (
                  <AuthenticatedFileImage
                    path={j.file_path}
                    alt={`Week ${j.week_number ?? j.entry_number} Journal`}
                    style={{ maxWidth: '90%', maxHeight: '600px', objectFit: 'contain', border: '1px solid #ddd' }}
                  />
                )}
              </div>
            )}

            {/* No content fallback */}
            {!j.activities_summary && !j.file_path && (
              <p style={{ color: '#999', fontStyle: 'italic', marginTop: '20px' }}>No content provided for this week.</p>
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
      {renderPhotos('dtr_form', 'PNC:AA-FO-30 DTR (manual form upload) — Student Internship Daily Time Record')}
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
                        <AuthenticatedFileImage path={photo.file_path} alt={photo.label} style={{ maxWidth: '80%', maxHeight: '400px', objectFit: 'contain', border: '1px solid #ddd' }} />
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
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '2px solid #005a2b', paddingBottom: '8px', marginBottom: '4px' }}>
      <img src="/images/uc-logo.png" alt="UC Logo" style={{ width: '60px', height: '60px', objectFit: 'contain' }} />
      <div style={{ textAlign: 'center', flex: 1 }}>
        <div style={{ fontSize: '9pt', marginBottom: '2px' }}>Republic of the Philippines</div>
        <div style={{ fontFamily: 'Arial, sans-serif', color: '#005a2b', fontSize: '16pt', fontWeight: 'bold', lineHeight: 1.1 }}>PAMANTASAN NG LUNGSOD NG Cabuyao</div>
        <div style={{ fontSize: '10pt', fontStyle: 'italic', marginTop: '2px' }}>(Pamantasan ng Cabuyao)</div>
        <div style={{ fontWeight: 'bold', fontStyle: 'italic', fontSize: '10pt', marginTop: '2px' }}>Office of Academic Affairs</div>
        <div style={{ fontSize: '8.5pt', marginTop: '2px' }}>Biglang-awa St., cor. Catleya St., Deparo, Cabuyao City</div>
      </div>
      <div style={{ width: '60px', height: '60px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        {companyLogoPath ? <AuthenticatedFileImage path={companyLogoPath} alt="Company Logo" style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} /> : <div style={{ width: '60px' }}></div>}
      </div>
    </div>
  )
}

export default PortfolioPreview

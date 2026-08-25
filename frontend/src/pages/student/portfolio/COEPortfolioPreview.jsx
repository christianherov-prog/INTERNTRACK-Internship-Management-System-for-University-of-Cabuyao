import React, { useState, useEffect, useRef } from 'react';
import { useReactToPrint } from 'react-to-print';
import { Link } from 'react-router-dom';
import PageError from '../../../components/PageError';
import api from '../../../services/api';
import { AuthenticatedFileImage } from '../../../components/AuthenticatedFile';
import WeeklyInternshipJournal from '../../../components/portfolio/WeeklyInternshipJournal';
import DailyTimeRecord from '../../../components/portfolio/DailyTimeRecord';
import { PrintFO24, PrintFO03, PrintFO22, PrintFO23 } from '../../../components/portfolio/EvaluationsPreview';

import { PaginatedTextSection, PaginatedImageCollection } from '../../../components/portfolio/AutoPaginatedFlow';

// --- Reusable Header Component ---
const COEHeader = ({ programTitle, companyLogoPath }) => {
  const styles = {
    headerContainer: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      paddingBottom: '8px',
      borderBottom: '3px solid #6cbe70',
      marginBottom: '20px',
      fontFamily: 'Arial, sans-serif',
      width: '100%',
      pageBreakAfter: 'avoid',
      breakAfter: 'avoid',
    },
    leftSection: {
      display: 'flex',
      alignItems: 'center',
      gap: '15px'
    },
    uniLogo: {
      width: '85px',
      height: '85px',
      objectFit: 'contain'
    },
    textContainer: {
      textAlign: 'left',
      lineHeight: '1.15'
    },
    universityName: {
      margin: '0 0 2px 0',
      fontFamily: "'Brush Script MT', 'Lucida Handwriting', cursive",
      fontSize: '26pt',
      color: '#005400',
      fontWeight: 'bold'
    },
    subText: {
      margin: '0 0 2px 0',
      fontSize: '11pt',
      color: '#000',
      letterSpacing: '0.5px',
      fontStyle: 'calibri'
    },
    address: {
      margin: '0 0 2px 0',
      fontSize: '10pt',
      color: '#000',
      letterSpacing: '0.5px',
      fontStyle: 'arial MT'
    },
    departmentText: {
      margin: '0 0 2px 0',
      fontSize: '10pt',
      fontWeight: 'bold',
      fontStyle: 'cambria',
      color: '#000'
    },
    programText: {
      margin: 0,
      fontSize: '11pt',
      fontWeight: 'bold',
      fontStyle: 'italic',
      color: '#000'
    },
    rightSection: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'flex-end',
      minWidth: '120px'
    },
    logoBox: {
      width: '120px',
      height: '60px',
      border: '1px dashed #999',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontSize: '9pt',
      textAlign: 'center',
      color: '#666'
    }
  };

  return (
    <div style={styles.headerContainer}>
      <div style={styles.leftSection}>
        <img src="/images/ccs-logo.png" alt="University Logo" style={styles.uniLogo} />
        <div style={styles.textContainer}>
          <h1 style={styles.universityName}>University of Cabuyao</h1>
          <p style={styles.subText}>(PAMANTASAN NG CABUYAO)</p>
          <p style={styles.address}>City of Cabuyao, Laguna 4025</p>
          <p style={styles.departmentText}>COLLEGE OF ENGINEERING</p>
          <p style={styles.programText}>{programTitle || 'Computer Engineering'}</p>
        </div>
      </div>

      <div style={styles.rightSection}>
        {companyLogoPath ? (
          <AuthenticatedFileImage
            path={companyLogoPath}
            alt="HTE Logo"
            style={{ width: '150px', maxHeight: '70px', objectFit: 'contain' }}
            fallback={<div style={styles.logoBox}>Logo of<br />HTE</div>}
          />
        ) : (
          <div style={styles.logoBox}>
            Logo of<br />HTE
          </div>
        )}
      </div>
    </div>
  );
};

// --- Page Wrapper Component ---
const Page = ({ children, programTitle, companyLogoPath, tocId, hideHeader = false }) => (
  <div className="a4-page force-page-break portfolio-document" data-toc-id={tocId} style={{
    width: '210mm',
    minHeight: '297mm', // keep for visual preview only
    margin: '0 auto 20px auto',
    padding: '20mm',
    background: 'white',
    fontFamily: 'Arial, sans-serif',
    position: 'relative',
    color: '#000'
  }}>
    {!hideHeader && <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />}
    <div style={{ display: 'block', width: '100%', height: '100%' }}>
      {children}
    </div>
    <div className="page-number"></div>
  </div>
);

// --- TOC Row Component ---
const TocRow = ({ label, page, level = 0, style = {}, bold = false }) => (
  <div style={{ display: 'flex', marginBottom: '6px', paddingLeft: `${level * 20}px`, fontWeight: bold ? 'bold' : 'normal', fontSize: '10.5pt', ...style }}>
    <div>{label}</div>
    <div style={{ flex: 1, borderBottom: '1px dotted #999', margin: '0 10px', position: 'relative', top: '-4px' }}></div>
    <div>{page || ''}</div>
  </div>
);

function COEPortfolioPreview() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [toc, setToc] = useState({});

  const printRef = useRef(null);
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: 'COE_Portfolio_Preview',
  });

  const load = () => {
    setLoading(true);
    setError(null);
    api.get('/student/portfolio')
      .then(res => setData(res.data))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load portfolio.');
        setData(null);
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  useEffect(() => {
    const checkDomInterval = setInterval(() => {
      const pages = document.querySelectorAll('.a4-page');
      if (pages.length > 0) {
        const newToc = {};

        pages.forEach((page, index) => {
          const pageNum = index + 1;

          if (page.hasAttribute('data-toc-id')) {
            const id = page.getAttribute('data-toc-id');
            if (!newToc[id]) newToc[id] = pageNum;
          }

          const markers = page.querySelectorAll('[data-toc-id]');
          markers.forEach(marker => {
            const id = marker.getAttribute('data-toc-id');
            if (!newToc[id]) {
              newToc[id] = pageNum;
            }
          });

          // Text fallbacks for un-tagged external components (DTR & Journals)
          const textLower = (page.textContent || "").toLowerCase();
          if (textLower.includes('weekly student internship journal')) {
            const weekMatch = textLower.match(/week\s+(\d+)/);
            if (weekMatch) {
              if (!newToc[`week-${weekMatch[1]}`]) newToc[`week-${weekMatch[1]}`] = pageNum;
            } else {
              if (!newToc['week-1']) newToc['week-1'] = pageNum;
            }
          }
          if (textLower.includes('daily time record') || textLower.includes('dtr')) {
            if (!newToc['dtr']) newToc['dtr'] = pageNum;
          }

          if (textLower.includes('problem and its solutions')) {
            if (!newToc['3.1']) newToc['3.1'] = pageNum;
          }
          if (textLower.includes('3.2. recommendations')) {
            if (!newToc['3.2']) newToc['3.2'] = pageNum;
          }
          if (textLower.includes('chapter iii')) {
            if (!newToc['chap3']) newToc['chap3'] = pageNum;
          }
          if (textLower.includes('chapter iv')) {
            if (!newToc['chap4']) newToc['chap4'] = pageNum;
          }
        });

        if (Object.keys(newToc).length > 2) {
          setToc(newToc);
          clearInterval(checkDomInterval);
        }
      }
    }, 1000);

    return () => clearInterval(checkDomInterval);
  }, [data]);

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <i className="fa fa-spinner fa-spin fa-2x mb-3" aria-hidden="true" />
        <div className="small">Checking your session…</div>
      </div>
    );
  }

  if (error) {
    return (
      <div style={{ background: '#e5e5e5', minHeight: '100vh', padding: '24px' }}>
        <PageError message={error} onRetry={load} />
        <div className="text-center mt-3">
          <Link to="/student/portfolio" className="text-muted">Back to Builder</Link>
        </div>
      </div>
    );
  }

  const p = data?.internship?.portfolio || {};
  const i = data?.internship || {};
  const u = data?.user || {};
  const sp = u?.student_profile || {};
  const custom = p.custom_fields || {};
  const photos = p.photos || [];
  const journals = i.journals || [];
  const companyLogoPath = p.company_logo_path;

  const studentName = sp.first_name ? `${sp.first_name.toUpperCase()} ${sp.last_name.toUpperCase()}` : '[STUDENT FULL NAME]';
  const programTitle = sp.program?.name || sp.course_name || i.program?.name || i.program || 'Bachelor of Science in Computer Engineering';
  const practicumCode = programTitle.includes('Computer Engineering') ? 'COE114: On-the-Job Training (240hrs)' : '[Course Code]: On-the-Job Training';

  const companyName = p.company_name || i.company?.company_name || '[COMPANY NAME]';
  const companyAddress = p.company_address || i.company?.address || '[Company Address]';

  const supervisorName = i.supervisor?.supervisorProfile
    ? `${i.supervisor.supervisorProfile.last_name}, ${i.supervisor.supervisorProfile.first_name}`
    : '[SUPERVISOR NAME]';

  const facultyName = i.faculty?.facultyProfile
    ? `ENGR. ${i.faculty.facultyProfile.first_name.toUpperCase()} ${i.faculty.facultyProfile.last_name.toUpperCase()}`
    : '[INSTRUCTOR FULL NAME WITH TITLE]';

  const date = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

  // Separate out the Approval Sheet photos if uploaded
  const approvalSheetPhotos = photos.filter(photo => photo.type === 'approval_sheet');

  const uploadChecks = [
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
  ];

  return (
    <div ref={printRef} style={{ backgroundColor: '#e5e5e5', padding: '20px', minHeight: '100vh', paddingBottom: '60px' }}>

      <div className="no-print" style={{
        position: 'sticky', top: 0, left: 0, zIndex: 1000,
        background: '#1a1a2e', color: '#fff',
        padding: '10px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        boxShadow: '0 2px 8px rgba(0,0,0,0.4)', marginBottom: '20px'
      }}>
        <div className="d-flex align-items-center gap-3">
          <Link to="/student/portfolio" style={{ color: '#ccc', textDecoration: 'none', fontSize: '14px' }}>
            <i className="fa fa-arrow-left me-2"></i>Back to Builder
          </Link>
          <span style={{ color: '#555' }}>|</span>
          <span style={{ fontWeight: 600, fontSize: '15px' }}>Portfolio Preview</span>
        </div>
        <div className="d-flex gap-2">
          <button onClick={handlePrint} style={{ background: '#16a34a', color: '#fff', border: 'none', padding: '8px 20px', borderRadius: '6px', fontWeight: 600, fontSize: '14px', cursor: 'pointer' }}>
            <i className="fa fa-print me-2"></i>Browser Print
          </button>
        </div>
      </div>

      {/* 1. COVER PAGE */}
      <Page tocId="cover" programTitle={programTitle} companyLogoPath={companyLogoPath}>
        <div style={{ textAlign: 'center', marginTop: '10px', fontFamily: 'Arial, sans-serif', fontSize: '11pt', color: '#000' }}>
          <h1 style={{ fontSize: '12pt', fontWeight: 'bold', marginBottom: '20px' }}>A PRACTICUM REPORT</h1>

          <div style={{ display: 'flex', justifyContent: 'center', margin: '20px 0 40px 0' }}>
            {companyLogoPath ? (
              <AuthenticatedFileImage
                path={companyLogoPath}
                alt="HTE Logo"
                style={{ maxWidth: '250px', maxHeight: '100px', objectFit: 'contain' }}
                fallback={
                  <div style={{ width: '200px', height: '80px', border: '1px dashed #999', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10pt', color: '#666' }}>
                    Logo of<br />HTE
                  </div>
                }
              />
            ) : (
              <div style={{ width: '200px', height: '80px', border: '1px dashed #999', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10pt', color: '#666' }}>
                Logo of<br />HTE
              </div>
            )}
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0' }}>Undertaken at <strong style={{ textTransform: 'uppercase' }}>{companyName}</strong></p>
            <p style={{ margin: '0 0 5px 0' }}>Located at <span style={{ textTransform: 'uppercase' }}>{companyAddress}</span></p>
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0' }}>In partial fulfillment of the requirements for the course</p>
            <p style={{ margin: 0, fontWeight: 'bold' }}>{practicumCode}</p>
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0' }}>For the Degree of</p>
            <p style={{ margin: 0, fontWeight: 'bold' }}>{programTitle}</p>
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0' }}>Presented to the faculty of Engineering</p>
            <p style={{ margin: 0, fontWeight: 'bold', textTransform: 'uppercase' }}>COLLEGE OF ENGINEERING</p>
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0', fontWeight: 'bold' }}>UNIVERSITY OF CABUYAO (PnC)</p>
            <p style={{ margin: '0 0 5px 0' }}>Katapatan Homes Subdivision</p>
            <p style={{ margin: 0 }}>Banay-banay, City of Cabuyao, Laguna 4025</p>
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0' }}>Submitted by:</p>
            <p style={{ margin: '0 0 5px 0', fontWeight: 'bold', textTransform: 'uppercase' }}>{studentName}</p>
            <p style={{ margin: 0 }}>{programTitle.includes('Computer Engineering') ? 'BSCPE' : programTitle}</p>
          </div>

          <div style={{ margin: '30px 0' }}>
            <p style={{ margin: '0 0 5px 0' }}>Submitted to:</p>
            <p style={{ margin: '0 0 5px 0', fontWeight: 'bold', textTransform: 'uppercase' }}>{facultyName}</p>
            <p style={{ margin: 0 }}>OJT Instructor</p>
          </div>

          <div style={{ marginTop: '40px' }}>
            <p style={{ margin: 0, fontWeight: 'bold' }}>{date}</p>
          </div>
        </div>
      </Page>

      {/* 2. APPROVAL SHEET */}
      {approvalSheetPhotos.length > 0 ? (
        approvalSheetPhotos.map(photo => (
          <Page key={photo.id} tocId="approval" programTitle={programTitle} companyLogoPath={companyLogoPath}>
            <h2 style={{ textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' }}>APPROVAL SHEET</h2>
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
              <AuthenticatedFileImage
                path={photo.file_path}
                alt="Approval Sheet"
                style={{ maxWidth: '100%', maxHeight: '750px', objectFit: 'contain' }}
              />
            </div>
          </Page>
        ))
      ) : (
        <Page tocId="approval" programTitle={programTitle} companyLogoPath={companyLogoPath}>
          <h2 style={{ textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' }}>APPROVAL SHEET</h2>
          <div style={{ textAlign: "center", marginTop: "80px", padding: "40px", border: "2px dashed #bbb", background: "#f8f9fa", borderRadius: "12px", width: "85%", margin: "80px auto 0" }}>
            <i className="fa fa-file-signature fa-3x text-warning mb-3"></i>
            <h6 className="fw-bold text-dark">Approval Sheet Not Uploaded Yet</h6>
            <p className="small text-muted mb-0" style={{ maxWidth: "450px", margin: "0 auto" }}>
              [ Draft Preview Mode: Upload your signed Approval Sheet in the Portfolio Builder to replace this placeholder. ]
            </p>
          </div>
        </Page>
      )}

      {/* 3. TABLE OF CONTENTS */}
      <Page tocId="toc" programTitle={programTitle} companyLogoPath={companyLogoPath}>
        <h2 style={{ textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' }}>TABLE OF CONTENTS</h2>
        <div style={{ padding: '0 20px', fontFamily: '"Arial", sans-serif' }}>
          <TocRow label="COVER PAGE" page={toc['cover']} bold />
          <TocRow label="APPROVAL SHEET" page={toc['approval']} bold />
          <TocRow label="TABLE OF CONTENTS" page={toc['toc']} bold />
          <TocRow label="ACKNOWLEDGEMENT" page={toc['ack']} bold />

          <TocRow label="CHAPTER I - BACKGROUND OF THE COMPANY" page={toc['chap1']} bold style={{ marginTop: '15px' }} />
          <TocRow label="1.1. Company Profile" page={toc['1.1']} level={1} />
          <TocRow label="1.2. Organizational Chart" page={toc['1.2']} level={1} />

          <TocRow label="CHAPTER II - WEEKLY PROGRESS REPORT" page={toc['chap2']} bold style={{ marginTop: '15px' }} />
          {journals.map(j => (
            <TocRow key={j.id} label={`Week ${j.week_number || j.week}`} page={toc[`week-${j.week_number || j.week}`]} level={1} />
          ))}

          <TocRow label="CHAPTER III - ASSESSMENT" page={toc['chap3']} bold style={{ marginTop: '15px' }} />
          <TocRow label="3.1. Problem and Its Solutions" page={toc['3.1']} level={1} />
          <TocRow label="3.2. Recommendations" page={toc['3.2']} level={1} />

          <TocRow label="CHAPTER IV - PERTINENT DOCUMENTS" page={toc['chap4']} bold style={{ marginTop: '15px' }} />
          {uploadChecks.map(doc => (
            <TocRow key={doc.type} label={doc.label} page={toc[doc.type]} level={1} />
          ))}
          <TocRow label="4.13. Daily Time Record" page={toc['dtr']} level={1} />
          <TocRow label="Student Intern Performance (PNC:AA-FO-24)" page={toc['fo24']} level={1} />
          <TocRow label="HTE To University Evaluation (PNC:AA-FO-03)" page={toc['fo03']} level={1} />
          <TocRow label="HTE Evaluation (PNC:AA-FO-22)" page={toc['fo22']} level={1} />
          <TocRow label="Program Evaluation (PNC:AA-FO-23)" page={toc['fo23']} level={1} />
          <TocRow label="Faculty Evaluation" page={toc['faculty_eval']} level={1} />
        </div>
      </Page>

      {/* 4. ACKNOWLEDGEMENT */}
      <PaginatedTextSection
        companyLogoPath={companyLogoPath}
        nextPg={() => { }}
        pageHeaderComponent={({ companyLogoPath }) => <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />}
        sections={[
          { type: 'heading', tag: 'h2', text: 'ACKNOWLEDGEMENT', style: { textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' } },
          { type: 'paragraph', text: custom.acknowledgement || '[Student\'s Acknowledgement Will Appear Here]', style: { whiteSpace: 'pre-wrap', textIndent: '50px', lineHeight: '1.6', marginBottom: '15px' } }
        ]}
      />

      {/* CHAPTER I */}
      <Page tocId="chap1" programTitle={programTitle} companyLogoPath={companyLogoPath}>
        <h2 style={{ textAlign: 'center', fontSize: '12pt', fontWeight: 'bold' }}>CHAPTER I</h2>
        <h2 style={{ textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' }}>BACKGROUND OF THE COMPANY</h2>
        <p style={{ textIndent: '50px', lineHeight: '1.6', marginBottom: '30px' }}>
          This chapter indicates the background information of the company where the intern underwent her on-site practicum training. This includes the company name and logo, its type, description, and location, date established, line of business, mission and vision statement, core values, and the products being offered to the industry.
        </p>

        {/* 1.1 COMPANY PROFILE TABLE */}
        <h3 data-toc-id="1.1" style={{ fontSize: '12pt', fontWeight: 'bold', textAlign: 'left', marginBottom: '15px' }}>
          1.1. Company Profile
        </h3>
        <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #000000', marginBottom: '20px' }}>
          <tbody>
            <tr>
              <td style={{ border: '1px solid #000000', padding: '15px 10px', width: '30%', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center', verticalAlign: 'middle' }}>
                Name
              </td>
              <td style={{ border: '1px solid #000000', padding: '15px 10px', textAlign: 'center', verticalAlign: 'middle' }}>
                <div style={{ fontWeight: 'bold', fontStyle: 'italic', fontSize: '12pt', marginBottom: '10px' }}>
                  {companyName}
                </div>
                {companyLogoPath ? (
                  <AuthenticatedFileImage
                    path={companyLogoPath}
                    alt="Company Logo"
                    style={{ maxWidth: '180px', maxHeight: '60px', objectFit: 'contain' }}
                  />
                ) : (
                  <div style={{ fontStyle: 'italic', color: '#000000', fontSize: '10pt' }}>[Company Logo]</div>
                )}
              </td>
            </tr>

            <tr>
              <td style={{ border: '1px solid #000000', padding: '10px', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center', verticalAlign: 'middle' }}>
                Date of<br />foundation
              </td>
              <td style={{ border: '1px solid #000000', padding: '10px', textAlign: 'center', fontStyle: 'italic', verticalAlign: 'middle' }}>
                {custom.date_of_foundation || 'July 23, 1973'}
              </td>
            </tr>

            <tr>
              <td style={{ border: '1px solid #000000', padding: '10px', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center', verticalAlign: 'middle' }}>
                Type
              </td>
              <td style={{ border: '1px solid #000000', padding: '10px', textAlign: 'center', fontStyle: 'italic', verticalAlign: 'middle' }}>
                {custom.company_type || 'Multinational Manufacturing Company'}
              </td>
            </tr>

            <tr>
              <td style={{ border: '1px solid #000000', padding: '10px', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center', verticalAlign: 'middle' }}>
                Location
              </td>
              <td style={{ border: '1px solid #000000', padding: '10px', textAlign: 'center', fontStyle: 'italic', verticalAlign: 'middle' }}>
                {companyAddress}
              </td>
            </tr>

            <tr>
              <td style={{ border: '1px solid #000000', padding: '15px 10px', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center', verticalAlign: 'middle' }}>
                Line of Business
              </td>
              <td style={{ border: '1px solid #000000', padding: '15px 10px', textAlign: 'center', fontStyle: 'italic', verticalAlign: 'middle' }}>
                {custom.line_of_business || 'Development, manufacturing, and sales of small precision motors, automotive motors, home appliance motors, commercial and industrial motors, motors for machinery, electronic and optical components, and other related products.'}
              </td>
            </tr>

            <tr>
              <td style={{ border: '1px solid #000000', padding: '15px 10px', fontWeight: 'bold', fontStyle: 'italic', textAlign: 'center', verticalAlign: 'middle' }}>
                Photos of their<br />products/services
              </td>
              <td style={{ border: '1px solid #000000', padding: '15px 10px', textAlign: 'center', verticalAlign: 'middle' }}>
                {photos.filter(photo => photo.type === 'product_photos').length > 0 ? (
                  <AuthenticatedFileImage
                    path={photos.find(photo => photo.type === 'product_photos').file_path}
                    alt="Products/Services"
                    style={{ maxWidth: '100%', maxHeight: '250px', objectFit: 'contain' }}
                  />
                ) : (
                  <div style={{ fontStyle: 'italic', color: '#000000', fontSize: '10pt', padding: '40px 0' }}>
                    [Draft Preview Mode: Upload Product Photos in Builder]
                  </div>
                )}
              </td>
            </tr>
          </tbody>
        </table>
      </Page>

      <PaginatedTextSection
        companyLogoPath={companyLogoPath}
        nextPg={() => { }}
        pageHeaderComponent={({ companyLogoPath }) => <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />}
        sections={[
          { type: 'heading', tag: 'h4', text: 'Company Description', style: { fontSize: '12pt', fontWeight: 'bold' } },
          { type: 'paragraph', text: p.company_background || '[Company Description Text]', style: { whiteSpace: 'pre-wrap', textIndent: '50px', lineHeight: '1.6' } }
        ]}
      />

      {/* CHAPTER I (Org Chart) */}
      <Page programTitle={programTitle} companyLogoPath={companyLogoPath}>
        <h3 data-toc-id="1.2" style={{ fontSize: '12pt', fontWeight: 'bold' }}>1.2. Organizational Chart</h3>
        <div style={{ width: '100%', height: '500px', border: '2px dashed #ccc', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          {photos.filter(photo => photo.type === 'org_chart').length > 0 ? (
            <AuthenticatedFileImage
              path={photos.find(photo => photo.type === 'org_chart').file_path}
              alt="Org Chart"
              style={{ maxWidth: "100%", maxHeight: "100%", objectFit: "contain" }}
            />
          ) : (
            <p style={{ color: '#666' }}>[Organizational Chart Not Uploaded]</p>
          )}
        </div>
      </Page>

      {journals.length === 0 && (
        <WeeklyInternshipJournal studentName={studentName} program={programTitle} companyLogoPath={companyLogoPath} />
      )}

      {journals.map((j) => (
        (!j.file_path || j.file_path.endsWith(".pdf")) ? (
          <WeeklyInternshipJournal key={j.id} studentName={studentName} program={programTitle} weekNumber={j.week_number || j.week} date={j.date} accomplishment={j.activities_summary || j.accomplishment} difficulties={j.challenges || j.difficulties} insights={j.learnings || j.insights} companyLogoPath={companyLogoPath} />
        ) : (
          <div key={j.id} className="a4-page portfolio-document position-relative text-center" style={{ width: '210mm', minHeight: '297mm', margin: '0 auto 20px auto', padding: '20mm', background: 'white', fontFamily: 'Arial, sans-serif' }}>
            <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />
            <h4 style={{ fontWeight: "bold", marginTop: "20px", textAlign: "left" }}>Week {j.week_number || j.week}</h4>
            <div style={{ marginTop: "20px", width: "100%", display: "flex", justifyContent: "center" }}>
              <AuthenticatedFileImage path={j.file_path} alt={`Week ${j.week_number || j.week} Journal`} style={{ maxWidth: "98%", maxHeight: "600px", objectFit: "contain", margin: "0 auto", display: "block" }} />
            </div>
            <div className="page-number"></div>
          </div>
        )
      ))}

      {/* CHAPTER III */}
      <PaginatedTextSection
        companyLogoPath={companyLogoPath}
        nextPg={() => { }}
        pageHeaderComponent={({ companyLogoPath }) => <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />}
        sections={[
          { type: 'heading', tag: 'h2', text: 'CHAPTER III', style: { textAlign: 'center', fontSize: '12pt', fontWeight: 'bold' } },
          { type: 'heading', tag: 'h2', text: 'ASSESSMENT', style: { textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' } },
          { type: 'paragraph', text: 'The content of this chapter includes the assessment where a problem is identified with its possible solutions, and recommendations for the OJT Program.', style: { textIndent: '50px', lineHeight: '1.6' } },

          { type: 'heading', tag: 'h3', text: '3.1. Problem and Its Solutions', style: { fontSize: '12pt', fontWeight: 'bold' } },
          { type: 'paragraph', inlineLabel: 'Problem', text: custom.problem || '[State the observed problem in the company/process]', style: { whiteSpace: 'pre-wrap', lineHeight: '1.6' } },
          { type: 'paragraph', inlineLabel: 'Alternative Solutions', text: custom.alternative_solutions || '[Provide Alternative Solutions]', style: { whiteSpace: 'pre-wrap', lineHeight: '1.6' } },
          { type: 'paragraph', inlineLabel: 'Design/Solution', text: custom.design_solution || '[State the best chosen solution]', style: { whiteSpace: 'pre-wrap', lineHeight: '1.6' } },
          { type: 'paragraph', inlineLabel: 'Conclusions', text: custom.conclusions || '[Conclude how the solution impacts the problem]', style: { whiteSpace: 'pre-wrap', lineHeight: '1.6', marginBottom: '30px' } },

          { type: 'heading', tag: 'h3', text: '3.2. Recommendations', style: { fontSize: '12pt', fontWeight: 'bold' } },
          { type: 'paragraph', inlineLabel: 'a. Students', text: custom.recommendation_students || '[Recommendation for future students]', style: { whiteSpace: 'pre-wrap', textIndent: '50px', lineHeight: '1.6', marginBottom: '15px' } },
          { type: 'paragraph', inlineLabel: 'b. Internship Program', text: custom.recommendation_program || '[Recommendation for the University\'s program]', style: { whiteSpace: 'pre-wrap', textIndent: '50px', lineHeight: '1.6', marginBottom: '15px' } },
          { type: 'paragraph', inlineLabel: 'c. Curriculum', text: custom.recommendation_curriculum || '[Recommendation for curriculum improvements]', style: { whiteSpace: 'pre-wrap', textIndent: '50px', lineHeight: '1.6', marginBottom: '15px' } },
          { type: 'paragraph', inlineLabel: 'd. Host Training Establishment', text: custom.recommendation_hte || '[Recommendation for the company]', style: { whiteSpace: 'pre-wrap', textIndent: '50px', lineHeight: '1.6', marginBottom: '15px' } }
        ]}
      />

      {/* DAILY TIME RECORD (DTR) */}
      {(() => {
        const dtrTypes = ['dtr_form', 'PNC:AA-FO-30 DTR (manual form upload)', 'Daily Time Record', 'dtr'];
        const dtrPhotos = photos.filter(x => dtrTypes.includes(x.type) || dtrTypes.includes(x.document_type) || dtrTypes.includes(x.original_type));
        const imageDtrs = dtrPhotos.filter(x => x.file_path && !x.file_path.endsWith('.pdf'));

        if (imageDtrs.length === 0) {
          return (
            <DailyTimeRecord studentName={studentName} program={programTitle} companyName={companyName} supervisorName={supervisorName} companyLogoPath={companyLogoPath} />
          );
        }

        return (
          <div className="a4-page portfolio-document position-relative" style={{ width: '210mm', minHeight: '297mm', margin: '0 auto 20px auto', padding: '20mm', background: 'white', fontFamily: 'Arial, sans-serif' }}>
            <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />
            <h4 style={{ fontWeight: "bold", marginTop: "20px", textAlign: "center", fontSize: "12pt" }}>Student Internship Daily Time Record</h4>
            <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: '15px', marginTop: '20px' }}>
              {imageDtrs.map((photo, index) => (
                <div key={photo.id || index}>
                  <AuthenticatedFileImage
                    path={photo.file_path}
                    alt="Daily Time Record"
                    style={{ maxWidth: '100%', maxHeight: '600px', border: '1px solid #ccc', objectFit: 'contain' }}
                  />
                </div>
              ))}
            </div>
            <div className="page-number"></div>
          </div>
        );
      })()}

      {/* CHAPTER IV (Dynamically mapped documents based on builder checks) */}
      <div className="a4-page force-page-break portfolio-document position-relative" data-toc-id="chap4" style={{ width: '210mm', minHeight: '297mm', margin: '0 auto 20px auto', padding: '20mm', background: 'white', fontFamily: 'Arial, sans-serif', color: '#000' }}>
        <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />
        <div style={{ display: 'block', width: '100%', height: '100%' }}>
          <h2 style={{ textAlign: 'center', fontSize: '12pt', fontWeight: 'bold' }}>CHAPTER IV</h2>
          <h2 style={{ textAlign: 'center', marginBottom: '30px', fontSize: '12pt', fontWeight: 'bold' }}>PERTINENT DOCUMENTS</h2>
          <p style={{ textIndent: '50px', lineHeight: '1.6', marginBottom: '40px' }}>
            This section presents the necessary documents accomplished by the intern in regards with the formal application of the on-site practicum training at <strong>{companyName}</strong>.
          </p>
        </div>
        <div className="page-number"></div>
      </div>

      {uploadChecks.map(check => {
        const items = photos.filter(p => p.type === check.type);
        if (items.length === 0) return null;
        return (
          <PaginatedImageCollection
            key={check.type}
            tocId={check.type}
            list={items}
            title={check.label}
            companyLogoPath={companyLogoPath}
            nextPg={() => { }}
            pageHeaderComponent={({ companyLogoPath }) => <COEHeader programTitle={programTitle} companyLogoPath={companyLogoPath} />}
          />
        );
      })}

      {/* EVALUATIONS */}
      {(() => {
        const evals = data?.internship?.evaluations || [];
        const fo03 = evals.find(e => e.form_type === 'FO-03') || {};
        const fo22 = evals.find(e => e.form_type === 'FO-22') || {};
        const fo23 = evals.find(e => e.form_type === 'FO-23') || {};
        const fo24 = evals.find(e => e.form_type === 'FO-24') || {};
        const facultyEval = evals.find(e => e.form_type === 'faculty_eval') || {};

        return (
          <>
            <PrintFO24 evalData={fo24} internship={data?.internship} tocId="fo24" />
            <PrintFO03 evalData={fo03} internship={data?.internship} tocId="fo03" />
            <PrintFO22 evalData={fo22} internship={data?.internship} tocId="fo22" />
            <PrintFO23 evalData={fo23} internship={data?.internship} tocId="fo23" />

          </>
        );
      })()}

    </div>
  );
}

export default COEPortfolioPreview;
import React, { useState, useEffect, useRef } from 'react';
import { useReactToPrint } from 'react-to-print';
import { Link } from 'react-router-dom';
import PageError from '../../../components/PageError';
import api from '../../../services/api';
import { AuthenticatedFileImage } from '../../../components/AuthenticatedFile';
import '../../../assets/css/portfolio-print.css';
import { PaginatedTextSection, PaginatedImageCollection } from '../../../components/portfolio/AutoPaginatedFlow';
import WeeklyInternshipJournal from '../../../components/portfolio/WeeklyInternshipJournal';
import DailyTimeRecord from '../../../components/portfolio/DailyTimeRecord';
import { PrintFO24, PrintFO03, PrintFO22, PrintFO23 } from '../../../components/portfolio/EvaluationsPreview';

// --- Reusable Header Component ---
function PageHeader({ companyLogoPath }) {
  const styles = {
    headerContainer: {
      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
      textAlign: 'center', fontFamily: 'Arial, sans-serif',
      pageBreakAfter: 'avoid', breakAfter: 'avoid',
      width: '100%', position: 'relative', paddingBottom: '10px', lineHeight: '1'
    },
    sideCol: { width: '85px', display: 'flex', justifyContent: 'center', alignItems: 'center', flexShrink: 0 },
    centerCol: { flex: 1, padding: '0 10px' },
    republic: { margin: 0, fontSize: '11pt', textAlign: 'center', marginRight: '40px' },
    university: {
      margin: '2px 0', fontFamily: "'Old English Text MT','Old English Five','UnifrakturCook',serif",
      fontSize: '22pt', color: '#0B5D2A', fontWeight: 'normal', textAlign: 'center'
    },
    pamantasan: { margin: 0, fontSize: '11pt', fontStyle: 'italic', textAlign: 'center', marginRight: '40px' },
    department: { margin: '5px 0 2px 0', fontSize: '14pt', fontWeight: 'bold', textAlign: 'center' },
    address: { fontSize: '9pt' },
    logoBox: {
      width: '78px', height: '78px', border: '1px dashed #444', display: 'flex',
      alignItems: 'center', justifyContent: 'center', fontSize: '8pt', textAlign: 'center', color: '#444'
    }
  };
  return (
    <div style={styles.headerContainer}>
      <div style={styles.sideCol}>
        <img src="/images/ccs-logo.png" alt="UC Logo" style={{ width: '78px', height: '78px', objectFit: 'contain' }} />
      </div>
      <div style={styles.centerCol}>
        <p style={styles.republic}>Republic of the Philippines</p>
        <h1 style={styles.university}>University of Cabuyao</h1>
        <p style={styles.pamantasan}>(Pamantasan ng Cabuyao)</p>
        <h2 style={styles.department}>COLLEGE OF COMPUTING STUDIES</h2>
        <p style={styles.address}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</p>
      </div>
      <div style={styles.sideCol}>
        {companyLogoPath ? (
          <AuthenticatedFileImage path={companyLogoPath} alt="HTE Logo"
            style={{ width: '78px', height: '78px', objectFit: 'contain' }}
            fallback={<div style={styles.logoBox}>Logo<br />of<br />HTE</div>}
          />
        ) : (
          <div style={styles.logoBox}>Logo<br />of<br />HTE</div>
        )}
      </div>
    </div>
  );
}

// --- FULL PAGE TOC Row Component ---
const TocRow = ({ label, page, level = 0, bold = false, style }) => (
  <div style={{
    display: 'flex',
    justifyContent: 'space-between',
    paddingLeft: `${level * 20}px`,
    fontWeight: bold ? 'bold' : 'normal',
    fontSize: '9pt',
    fontFamily: 'Arial, sans-serif',
    lineHeight: '1.2',
    ...style
  }}>
    <span>{label}</span>
    <span>{page || ''}</span>
  </div>
);

function PortfolioPreview() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [toc, setToc] = useState({});

  const printRef = useRef(null);
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: 'CCS_Portfolio_Preview'
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
  }

  useEffect(() => { load() }, []);

  // --- Dynamic DOM Polling for Page Numbers ---
  useEffect(() => {
    const checkDomInterval = setInterval(() => {
      const pages = document.querySelectorAll('.a4-page');

      if (pages.length > 0) {
        const newToc = {};

        const textMappings = [
          { id: 'vision-uc', match: 'vision of uc' },
          { id: 'mission-uc', match: 'mission of uc' },
          { id: 'host-profile', match: 'host company' },
          { id: 'vision-mission', match: 'vision & mission' },
          { id: 'org-chart', match: 'organizational chart' },
          { id: 'history', match: 'history' },

          { id: 'prof-ethical', match: 'ethical, and legal responsibilities' },
          { id: 'things-learned', match: 'things i learned' },
          { id: 'experience', match: 'experience with people around me' },
          { id: 'industry', match: 'industry-aligned best practices' },
          { id: 'recommendation', match: 'recommendation for improvement' },
          { id: 'advice', match: 'advice to those who will take' },

          { id: 'app-reg', match: 'registration form' },
          { id: 'app-med', match: 'medical result' },
          { id: 'app-psych', match: 'psychological test result' },
          { id: 'app-app', match: 'application letter' },
          { id: 'app-cv', match: 'student curriculum vitae pnc-aa-fo-27' },
          { id: 'app-req-rec', match: 'request for recommendation letter pnc:aa-fo-26' },
          { id: 'app-acc', match: 'acceptance form pnc:aa-fo-29' },
          { id: 'app-cons', match: 'consent form pnc: aa-fo-28' },
          { id: 'app-train', match: 'training plan pnc: aa-fo-25.3' },
          { id: 'app-dtr', match: 'daily time record' },
          { id: 'app-eval', match: 'performance evaluation form pnc: aa-fo-24' },
          { id: 'app-moa', match: 'memorandum of agreement' },
          { id: 'app-visit', match: 'visitation form' },
          { id: 'app-cert', match: 'certification of completion' },
          { id: 'app-hte-eval', match: 'establishment evaluation form pnc aa-fo-22' },
          { id: 'app-prog-eval', match: 'program evaluation form pnc aa-fo-23' },
          { id: 'app-photos', match: 'photos during ojt' },

          { id: 'train-wadhwani', match: 'training (wadwhani)' },
          { id: 'train-cert', match: 'certificate of training' },
          { id: 'train-prepost', match: 'pre and post test result (if applicable)' },
          { id: 'train-doc', match: 'documentation of training proper' },

          { id: 'exam', match: 'certification exam' },
          { id: 'exam-cert', match: 'certification', exclude: 'exam' },
          { id: 'exam-prepost', match: 'pre and post test result', exclude: '(if applicable)' },
          { id: 'exam-doc', match: 'documentation of during and preparation' },
        ];

        pages.forEach((page, index) => {
          const pageNum = index + 1;
          const textLower = (page.textContent || "").toLowerCase();

          if (textLower.includes('table of contents')) return;

          if (page.hasAttribute('data-toc-id')) {
            const id = page.getAttribute('data-toc-id');
            if (!newToc[id]) newToc[id] = pageNum;
          }
          const markers = page.querySelectorAll('[data-toc-id]');
          markers.forEach(marker => {
            const id = marker.getAttribute('data-toc-id');
            if (!newToc[id]) newToc[id] = pageNum;
          });

          textMappings.forEach(mapping => {
            if (!newToc[mapping.id]) {
              if (textLower.includes(mapping.match)) {
                if (mapping.exclude && textLower.includes(mapping.exclude)) return;
                newToc[mapping.id] = pageNum;
              }
            }
          });

          const weekMatch = textLower.match(/week\s+(\d+)/);
          if (weekMatch) {
            const weekNum = weekMatch[1];
            if (!newToc[`week- ${weekNum}`]) newToc[`week - ${weekNum}`] = pageNum;
          }
        });

        if (Object.keys(newToc).length > 3) {
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
    )
  }

  const p = data?.internship?.portfolio;
  const i = data?.internship;
  const u = data?.user;
  const sp = u?.student_profile;

  const studentName = sp
    ? `${sp.last_name?.toUpperCase()}, ${sp.first_name?.toUpperCase()}${sp.middle_name ? ' ' + sp.middle_name[0].toUpperCase() + '.' : ''}`
    : '________________________';

  const section = sp?.section ?? '_________';

  const programTitle = (sp?.course_name ?? i?.program ?? '').includes('Computer Science')
    ? 'Bachelor of Science in Computer Science'
    : 'Bachelor of Science in Information Technology';

  const practicumCode = programTitle.includes('Computer Science')
    ? 'CSP115 - CS Practicum (300 hours)'
    : 'ITP113 - IT Practicum (500 hours)';

  const ayLabel = sp?.academic_year ? `A.Y.${sp.academic_year} / ${sp.semester === 2 ? '2nd' : '1st'} SEMESTER` : 'A.Y. 2025–2026 / 2nd SEMESTER';

  const journals = i?.journals ?? [];
  const photos = p?.photos || [];

  let currentPage = 1;
  const nextPg = () => currentPage++;

  const ojtWeeks = [...new Set(photos.filter(x => x.type === 'ojt_photo').map(x => x.week_number))].sort((a, b) => a - b);

  const visionMissionList = photos.filter(x => ['vision_mission', 'company_vision_mission'].includes(x.type));
  if (visionMissionList.length === 0 && p?.vision_mission_path) {
    visionMissionList.push({ id: 'vm-doc', file_path: p.vision_mission_path, label: 'Company Vision & Mission' });
  }
  const hasVisionMissionImg = visionMissionList.length > 0;

  const renderPhotos = (type, title, requiresWeek = false) => {
    const types = Array.isArray(type) ? type : [type];
    const list = photos.filter(x => types.includes(x.type) || types.includes(x.document_type) || types.includes(x.original_type));
    return (
      <PaginatedImageCollection
        list={list}
        title={title}
        companyLogoPath={p?.company_logo_path}
        nextPg={nextPg}
        pageHeaderComponent={PageHeader}
        requiresWeek={requiresWeek}
        emptyMessage="[ Draft Preview Mode: This section is currently empty. You can upload this requirement in the Portfolio Builder when ready. ]"
      />
    );
  }

  return (
    <div ref={printRef} style={{ background: '#e5e5e5', minHeight: '100vh', paddingBottom: '60px' }}>
      <div className="no-print" style={{
        position: 'sticky', top: 0, left: 0, zIndex: 1000,
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
          <span className="badge bg-info text-dark ms-2" style={{ fontSize: '12px', fontWeight: '500' }}><i className="fa fa-info-circle me-1"></i>Draft Preview Mode</span>
        </div>
        <div className="d-flex gap-2">
          <button
            onClick={handlePrint}
            style={{
              background: '#16a34a', color: '#fff', border: 'none',
              padding: '8px 20px', borderRadius: '6px', fontWeight: 600, fontSize: '14px', cursor: 'pointer'
            }}>
            <i className="fa fa-print me-2"></i>Browser Print
          </button>
        </div>
      </div>

      {/* PAGE 1: COVER */}
      <div className="a4-page force-page-break cover-page-banner"
        style={{
          backgroundImage: 'url(/images/cover-bg.jpg)', backgroundSize: 'cover',
          backgroundPosition: 'center', padding: 0
        }}>
        <div style={{ position: 'absolute', top: '51.5%', left: '53%', textAlign: 'left', maxWidth: '45%' }}>
          <div style={{ fontSize: '26pt', fontWeight: 'bold', color: '#ffffff', lineHeight: 1.1, letterSpacing: '-0.5px' }}>Internship Portfolio</div>
          <div style={{ fontSize: '11pt', fontWeight: 'bold', color: '#ffffff', marginTop: '4px', textTransform: 'uppercase' }}>{ayLabel}</div>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* PAGE 2: TITLE */}
      <div className="a4-page force-page-break cover-page-banner" style={{ backgroundImage: 'url(/images/title-bg.jpg)', backgroundSize: 'cover', backgroundPosition: 'center', padding: 0, position: 'relative' }}>
        <div style={{ position: 'absolute', bottom: '45%', left: '6%', textAlign: 'left', maxWidth: '50%' }}>
          <div style={{ fontSize: '13pt', fontWeight: 'bold', color: '#1a5c2a' }}>{practicumCode}</div>
          <div style={{ fontSize: '11pt', color: '#333', marginTop: '4px' }}>{ayLabel}</div>
          <div style={{ fontSize: '13pt', fontWeight: 'bold', color: '#222', marginTop: '12px' }}>{studentName}</div>
          <div style={{ fontSize: '11pt', color: '#333' }}>{section}</div>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* PAGE 3: NARRATIVE HEAD */}
      <div className="a4-page page-break portfolio-document position-relative" style={{ fontFamily: "Arial, sans-serif", fontSize: "11pt" }}>
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <div style={{ flex: 1, width: "93%", textAlign: "center", paddingTop: "0px", paddingBottom: "40px" }}>
          <div style={{ marginBottom: "42px" }}>
            <p style={{ textAlign: "center" }}>A Narrative Report on the On-The-Job</p>
            <p style={{ margin: 0, textAlign: "center" }}>undertaken at <strong style={{ fontStyle: "italic" }}>{p?.company_name || i?.company?.company_name || "(Name of HTE)"}</strong></p>
            <p style={{ margin: 0, textAlign: "center" }}>located at <strong style={{ fontStyle: "italic" }}>{p?.company_address || i?.company?.address || "(Address of HTE)"}</strong></p>
          </div>
          <div style={{ marginBottom: "42px" }}>
            <p style={{ textAlign: "center" }}>In partial fulfillment of the requirements for the course</p>
            <p style={{ fontWeight: "bold", margin: "10px 0", textAlign: "center" }}>{practicumCode}</p>
            <p style={{ marginTop: "20px", textAlign: "center" }}>For the Degree of</p>
            <p style={{ fontWeight: "bold", textAlign: "center" }}>{programTitle}</p>
          </div>
          <div style={{ marginBottom: "42px" }}>
            <p style={{ textAlign: "center" }}>Presented to the faculty of Computing Studies</p>
            <p style={{ fontWeight: "bold", textAlign: "center" }}>COLLEGE OF COMPUTING STUDIES</p>
            <p style={{ textAlign: "center" }}>UNIVERSITY OF CABUYAO (PnC)</p>
            <p style={{ textAlign: "center" }}>Katapatan Mutual Homes Subdivision</p>
            <p style={{ textAlign: "center" }}>Brgy. Banay-banay, City of Cabuyao, Laguna 4025</p>
          </div>
          <div style={{ marginBottom: "42px" }}>
            <p style={{ textAlign: "center" }}>Submitted by:</p>
            <p style={{ fontWeight: "bold", textAlign: "center" }}>{studentName}</p>
            <p style={{ textAlign: "center" }}>{section}</p>
          </div>
          <div>
            <p style={{ textAlign: "center" }}>Submitted to:</p>
            <p style={{ fontWeight: "bold", fontStyle: "italic", marginTop: "16px", textAlign: "center" }}>
              {i?.faculty?.facultyProfile ? `Dr.${i.faculty.facultyProfile.first_name} ${i.faculty.facultyProfile.last_name}` : "____________________________"}
            </p>
            <p style={{ textAlign: "center" }}>Internship Instructor</p><br />
            <p style={{ fontWeight: "bold", textAlign: "center" }}>ASST. PROF. ARCELITO QUIATCHON</p>
            <p style={{ textAlign: "center" }}>CCS Internship Coordinator</p><br />
            <p style={{ fontWeight: "bold", textAlign: "center" }}>{new Date().toLocaleDateString("en-US", { month: "long", year: "numeric" })}</p>
          </div>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* TABLE OF CONTENTS - STRETCHED FULL PAGE */}
      <div className="a4-page page-break portfolio-document position-relative d-flex flex-column">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <h3 style={{ textAlign: 'center', fontWeight: 'bold', marginBottom: '15px', fontSize: '12pt', fontFamily: '"Arial", sans-serif' }}>Table of Contents</h3>

        {/* flex-grow-1 and space-between distribute the items perfectly across the full A4 height */}
        <div style={{ padding: '0 10px', flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', paddingBottom: '20px' }}>

          <TocRow label="CHAPTER I: INTRODUCTION" page={toc['chap1']} bold />
          <TocRow label="Vision of UC" page={toc['vision-uc']} level={0} />
          <TocRow label="Mission of UC" page={toc['mission-uc']} level={0} />
          <TocRow label="Host Company Profile" page={toc['host-profile']} level={0} />
          <TocRow label="Vision and Mission" page={toc['vision-mission']} level={1} />
          <TocRow label="Organizational Chart" page={toc['org-chart']} level={1} />
          <TocRow label="History" page={toc['history']} level={1} />

          <TocRow label="CHAPTER II: WEEKLY PROGRESS REPORT" page={toc['chap2']} bold style={{ marginTop: '8px' }} />
          {journals.length > 0 ? (
            journals.map(j => (
              <TocRow key={j.id} label={`Week ${j.week_number || j.week}`} page={toc[`week - ${j.week_number || j.week}`]} level={0} />
            ))
          ) : (
            <>
              <TocRow label="Week 1" page={toc['week-1']} level={0} />
              <TocRow label="Week 2" page={toc['week-2']} level={0} />
              <TocRow label="Week 3" page={toc['week-3']} level={0} />
            </>
          )}

          <TocRow label="CHAPTER III: ASSESSMENT OF THE PROGRAM" page={toc['chap3']} bold style={{ marginTop: '8px' }} />
          <TocRow label="Professional and Ethical and Legal Responsibilities as Future IT Professionals" page={toc['prof-ethical']} level={0} />
          <TocRow label="Things I learned as future IT Professional" page={toc['things-learned']} level={1} />
          <TocRow label="My experience with people around me" page={toc['experience']} level={1} />
          <TocRow label="Industry-aligned best practices and standards I learned" page={toc['industry']} level={1} />
          <TocRow label="My recommendation for improvement of the Internship Program" page={toc['recommendation']} level={0} />
          <TocRow label="My advice to those who will take their internship in the near future" page={toc['advice']} level={0} />

          <TocRow label="APPENDICES (ADDITIONAL DOCUMENTS AT THE END)" page={toc['appendices']} bold style={{ marginTop: '8px' }} />
          <TocRow label={<span>Registration Form (<span style={{ fontStyle: 'italic' }}>Duly signed by the registrar</span>)</span>} page={toc['app-reg']} level={0} />
          <TocRow label="Medical Result" page={toc['app-med']} level={0} />
          <TocRow label="Psychological Test Result" page={toc['app-psych']} level={0} />
          <TocRow label="Application Letter" page={toc['app-app']} level={0} />
          <TocRow label="Student Curriculum Vitae PNC-AA-FO-27" page={toc['app-cv']} level={0} />
          <TocRow label="Internship Host Establishment Request for Recommendation Letter PNC:AA-FO-26" page={toc['app-req-rec']} level={0} />
          <TocRow label="Student Internship Acceptance Form PNC:AA-FO-29" page={toc['app-acc']} level={0} />
          <TocRow label="Student Internship Consent Form PNC: AA-FO-28" page={toc['app-cons']} level={0} />
          <TocRow label="Internship Training Plan PNC: AA-FO-25.3" page={toc['app-train']} level={0} />
          <TocRow label="Student Internship Daily Time Record (DTR) Form PNC: AA-FO-30" page={toc['app-dtr']} level={0} />

          <TocRow label="Memorandum of Agreement" page={toc['app-moa']} level={0} />
          <TocRow label="Internship / OJT Visitation Form" page={toc['app-visit']} level={0} />
          <TocRow label="Certification of Completion" page={toc['app-cert']} level={0} />
          <TocRow label="HTE Evaluation To University Internship Program PNC AA-FO-03" page={toc['fo03']} level={0} />
          <TocRow label="Internship Host Training Establishment Evaluation Form PNC AA-FO-22" page={toc['fo22']} level={0} />
          <TocRow label="Internship Program Evaluation Form PNC AA-FO-23" page={toc['fo23']} level={0} />
          <TocRow label="Student Internship Performance Evaluation Form PNC: AA-FO-24" page={toc['fo24']} level={0} />
          <TocRow label="Faculty Evaluation" page={toc['faculty_eval']} level={0} />
          <TocRow label={<span>Photos During OJT (<span style={{ fontStyle: 'italic' }}>Kindly add label and explanation</span>)</span>} page={toc['app-photos']} level={0} />

          <TocRow label={<span>ONLINE / F2F TRAINING (<span style={{ fontStyle: 'italic' }}>WADWHANI</span>)</span>} page={toc['train-wadhwani']} level={0} />
          <TocRow label="CERTIFCATE OF TRAINING" page={toc['train-cert']} level={0.5} />
          <TocRow label="PRE AND POST TEST RESULT (If applicable)" page={toc['train-prepost']} level={1} />
          <TocRow label="DOCUMENTATION OF TRAINING PROPER (PICTURES WITH DETAILED EXPLANATION)" page={toc['train-doc']} level={1} />

          <TocRow label="CERTIFICATION EXAM (ONLINE / F2F)" page={toc['exam']} level={0} />
          <TocRow label="CERTIFICATION" page={toc['exam-cert']} level={0.5} />
          <TocRow label="PRE AND POST TEST RESULT" page={toc['exam-prepost']} level={0.5} />
          <TocRow label="DOCUMENTATION OF DURING AND PREPARATION OF EXAM (PICTURES WITH DETAILED EXPLANATION)" page={toc['exam-doc']} level={0.5} />
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* CHAPTER I */}
      <div data-toc-id="chap1" className="a4-page page-break portfolio-document d-flex flex-column justify-content-between position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <div style={{ textAlign: "center", margin: "auto 0" }}>
          <h1 style={{ fontFamily: "Arial, sans-serif", fontWeight: "bold", fontSize: "36pt", marginBottom: "30px" }}>CHAPTER I</h1>
          <h2 style={{ fontFamily: "Arial, sans-serif", fontWeight: "bold", fontSize: "28pt" }}>INTRODUCTION</h2>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {hasVisionMissionImg ? (
        <div className="a4-page page-break portfolio-document position-relative">
          <PageHeader companyLogoPath={p?.company_logo_path} />

          <div style={{ marginTop: '20px', textAlign: 'left' }}>
            <h3 style={{ fontWeight: 'bold', textAlign: 'left', margin: '0 0 10px 0' }}>Vision of UC</h3>
            <p style={{ textAlign: 'left', textIndent: '0', margin: '0' }}>An institution of higher learning in Region IV. developing globally-competitive and value-laden professionals and leaders instrumental to community development and nation building.</p>
          </div>

          <div style={{ marginTop: '20px', textAlign: 'left' }}>
            <h3 style={{ fontWeight: 'bold', textAlign: 'left', margin: '0 0 10px 0' }}>Mission of UC</h3>
            <p style={{ textAlign: 'left', textIndent: '0', margin: '0' }}>An institution of higher learning committed to equip individuals with knowledge, skills and values that will enable them to achieve professional goals & provide leadership and service for national development.</p>
          </div>

          <h3 style={{ marginTop: '30px', marginBottom: '15px', fontWeight: 'bold', textAlign: 'left' }}>Host Company Profile</h3>
          <h4 style={{ marginTop: '10px', marginBottom: '10px', fontWeight: 'bold', textAlign: 'left' }}>Vision & Mission</h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', alignItems: 'center' }}>
            {visionMissionList.map((img, i) => (
              <AuthenticatedFileImage key={i} path={img.file_path || img.path} style={{ maxWidth: '98%', maxHeight: '400px', objectFit: 'contain' }} />
            ))}
          </div>

          <div className="page-number">{nextPg()}</div>
        </div>
      ) : (
        <PaginatedTextSection
          companyLogoPath={p?.company_logo_path}
          nextPg={nextPg}
          pageHeaderComponent={PageHeader}
          sections={[
            { title: 'Vision of UC', body: 'An institution of higher learning in Region IV. developing globally-competitive and value-laden professionals and leaders instrumental to community development and nation building.', heading: 'h4' },
            { title: 'Mission of UC', body: 'An institution of higher learning committed to equip individuals with knowledge, skills and values that will enable them to achieve professional goals & provide leadership and service for national development.', heading: 'h4' },
            { title: 'Host Company Profile ', body: null, placeholder: ' ', heading: 'h2', style: { marginTop: '20px', marginBottom: '10px' } },
            { title: 'Vision & Mission', body: null, heading: 'h4', style: { marginTop: '20px', marginBottom: '10px' } },
            { title: 'Vision', body: p?.company_vision ?? 'N/A', heading: 'strong', inlineTitle: true, indent: true },
            { title: 'Mission', body: p?.company_mission ?? 'N/A', heading: 'strong', inlineTitle: true, indent: true },
          ]}
        />
      )}

      {/* Organizational Chart */}
      <div className="a4-page page-break portfolio-document position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <h5 style={{ fontWeight: "bold", marginTop: "20px", textAlign: "left" }}>Organizational Chart</h5>
        {p?.org_chart_path ? (
          <div style={{ textAlign: "center", marginTop: "20px" }}>
            <AuthenticatedFileImage path={p.org_chart_path} alt="Org Chart" style={{ maxWidth: "98%", maxHeight: "780px", objectFit: "contain", margin: "0 auto", display: "block" }} />
          </div>
        ) : (
          <div style={{ textAlign: "center", marginTop: "80px", padding: "40px", border: "2px dashed #bbb", background: "#f8f9fa", borderRadius: "12px", width: "85%", margin: "80px auto 0" }}>
            <i className="fa fa-sitemap fa-3x text-warning mb-3"></i>
            <h6 className="fw-bold text-dark">Organizational Chart Not Uploaded Yet</h6>
          </div>
        )}
        <div className="page-number">{nextPg()}</div>
      </div>

      {/* Host Company History */}
      <PaginatedTextSection companyLogoPath={p?.company_logo_path} nextPg={nextPg} pageHeaderComponent={PageHeader} sections={[
        { title: "History", body: p?.company_background ?? "No history provided.", heading: "h5", inlineTitle: false }
      ]} />

      {/* CHAPTER II */}
      <div data-toc-id="chap2" className="a4-page page-break portfolio-document d-flex flex-column justify-content-between position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <div style={{ textAlign: "center", margin: "auto 0" }}>
          <h1 style={{ fontFamily: "Arial, sans-serif", fontWeight: "bold", fontSize: "36pt", marginBottom: "30px" }}>CHAPTER II</h1>
          <h2 style={{ fontFamily: "Arial, sans-serif", fontWeight: "bold", fontSize: "28pt" }}>WEEKLY PROGRESS REPORT</h2>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {journals.length === 0 && <WeeklyInternshipJournal studentName={studentName} program={programTitle} nextPg={nextPg} />}

      {journals.map((j) => (
        (!j.file_path || j.file_path.endsWith(".pdf")) ? (
          <WeeklyInternshipJournal key={j.id} studentName={studentName} program={programTitle} weekNumber={j.week_number || j.week} date={j.date} accomplishment={j.activities_summary || j.accomplishment} difficulties={j.challenges || j.difficulties} insights={j.learnings || j.insights} nextPg={nextPg} />
        ) : (
          <div key={j.id} className="a4-page page-break portfolio-document position-relative text-center">
            <PageHeader companyLogoPath={p?.company_logo_path} />
            <h4 style={{ fontWeight: "bold", marginTop: "20px", textAlign: "left" }}>Week {j.week_number || j.week}</h4>
            <div style={{ marginTop: "20px", width: "100%", display: "flex", justifyContent: "center" }}>
              <AuthenticatedFileImage path={j.file_path} alt={`Week ${j.week_number || j.week} Journal`} style={{ maxWidth: "98%", maxHeight: "600px", objectFit: "contain", margin: "0 auto", display: "block" }} />
            </div>
            <div className="page-number">{nextPg()}</div>
          </div>
        )
      ))}

      {/* CHAPTER III */}
      <div data-toc-id="chap3" className="a4-page page-break portfolio-document d-flex flex-column justify-content-between position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <div style={{ textAlign: 'center', margin: 'auto 0' }}>
          <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', marginBottom: '30px' }}>CHAPTER III</h1>
          <h2 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '28pt' }}>ASSESSMENT OF THE PROGRAM</h2>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      <PaginatedTextSection companyLogoPath={p?.company_logo_path} nextPg={nextPg} pageHeaderComponent={PageHeader} sections={[
        { title: 'Professional, Ethical, and Legal Responsibilities as Future IT Professionals', body: p?.prof_ethical_responsibilities, heading: 'h4' },
        { title: 'Things I Learned as a Future IT Professional', body: p?.things_learned, heading: 'h5' },
        { title: 'My Experience with People Around Me', body: p?.experience_with_people, heading: 'h5' },
        { title: 'Industry-Aligned Best Practices and Standards I Learned', body: p?.industry_best_practices, heading: 'h5' },
        { title: 'My Recommendation for Improvement of the Internship Program', body: p?.recommendations, heading: 'h4' },
        { title: 'My Advice to Those Who Will Take Their Internship in the Near Future', body: p?.advice, heading: 'h4' },
      ]} />

      {/* APPENDICES */}
      <div data-toc-id="appendices" className="a4-page page-break portfolio-document d-flex flex-column justify-content-between position-relative">
        <PageHeader companyLogoPath={p?.company_logo_path} />
        <div style={{ textAlign: 'center', margin: 'auto 0' }}>
          <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '48pt' }}>APPENDICES</h1>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>

      {renderPhotos(['registration_form'], 'Registration Form')}
      {renderPhotos(['medical_result', 'Medical Clearance'], 'Medical Result')}
      {renderPhotos(['psychological_result', 'Psychological Assessment Certificate'], 'Psychological Test Result')}
      {renderPhotos(['application_letter', 'Application Letter'], 'Application Letter')}
      {renderPhotos(['student_cv', 'Curriculum Vitae (PNC:AA-FO-27)', 'Curriculum Vitae'], 'Student Curriculum Vitae PNC-AA-FO-27')}
      {renderPhotos(['recommendation_request', 'Recommendation Letter'], 'Internship Host Establishment Request for Recommendation Letter PNC:AA-FO-26')}
      {renderPhotos(['acceptance_form', 'Student Internship Acceptance Form (PNC:AA-FO-29)', 'Student Internship Acceptance Form (PNC: AA-FO-29)'], 'Student Internship Acceptance Form PNC:AA-FO-29')}
      {renderPhotos(['consent_form', 'Notarized Student Internship Consent Form (PNC:AA-FO-28)', 'Notarized Student Internship Consent Form (PNC: AA-FO-28)'], 'Student Internship Consent Form PNC: AA-FO-28')}
      {renderPhotos(['training_plan', 'Training Plan', 'Internship Training Plan'], 'Internship Training Plan PNC: AA-FO-25.3')}

      {(() => {
        const dtrTypes = ['dtr_form', 'PNC:AA-FO-30 DTR (manual form upload)', 'Daily Time Record'];
        const dtrPhotos = photos.filter(x => dtrTypes.includes(x.type) || dtrTypes.includes(x.document_type) || dtrTypes.includes(x.original_type));
        const imageDtrs = dtrPhotos.filter(x => x.file_path && !x.file_path.endsWith('.pdf'));
        if (imageDtrs.length === 0) {
          return <DailyTimeRecord studentName={studentName} program={programTitle} companyName={p?.company_name} supervisorName={p?.supervisor_name} companyLogoPath={p?.company_logo_path} pageHeaderComponent={PageHeader} nextPg={nextPg} />;
        }
        return renderPhotos(dtrTypes, 'PNC:AA-FO-30 DTR (manual form upload) — Student Internship Daily Time Record');
      })()}


      {renderPhotos(['moa_document', 'MOA / LOA / TOR', 'Memorandum of Agreement'], 'Memorandum of Agreement')}
      {renderPhotos(['visitation_form', 'Internship / OJT Visitation Form'], 'Internship / OJT Visitation Form')}
      {renderPhotos(['completion_certificate', 'Certificate of Completion', 'Certification of Completion'], 'Certification of Completion')}
      {/* EVALUATIONS */}
      {(() => {
        const evals = data?.internship?.evaluations || [];
        const fo03 = evals.find(e => e.form_type === 'FO-03');
        const fo22 = evals.find(e => e.form_type === 'FO-22');
        const fo23 = evals.find(e => e.form_type === 'FO-23');
        const fo24 = evals.find(e => e.form_type === 'FO-24');

        return (
          <>
            <PrintFO03 evalData={fo03 || null} internship={data?.internship} tocId="fo03" />
            <PrintFO22 evalData={fo22 || null} internship={data?.internship} tocId="fo22" />
            <PrintFO23 evalData={fo23 || null} internship={data?.internship} tocId="fo23" />
            <PrintFO24 evalData={fo24 || null} internship={data?.internship} tocId="fo24" />
          </>
        );
      })()}


      {ojtWeeks.length === 0 ? (
        <PaginatedImageCollection list={[]} title="Photos During OJT" companyLogoPath={p?.company_logo_path} nextPg={nextPg} pageHeaderComponent={PageHeader} emptyMessage="[ Draft Preview Mode: Upload your weekly OJT pictures with captions in the Portfolio Builder to populate this section. ]" />
      ) : (
        ojtWeeks.map(w => {
          const list = photos.filter(x => x.type === 'ojt_photo' && x.week_number === w);
          return <PaginatedImageCollection key={`ojt - week - ${w}`} list={list} title={`Week ${w}`} companyLogoPath={p?.company_logo_path} nextPg={nextPg} pageHeaderComponent={PageHeader} />
        })
      )}

      {photos.some(x => ['training_certificate', 'training_test_result', 'training_documentation'].includes(x.type)) && (
        <div className="a4-page page-break portfolio-document d-flex flex-column justify-content-between position-relative">
          <PageHeader companyLogoPath={p?.company_logo_path} />
          <div style={{ textAlign: 'center', margin: 'auto 0' }}>
            <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', textAlign: 'center' }}>ONLINE / F2F TRAINING (WADWHANI)</h1>
          </div>
          <div className="page-number">{nextPg()}</div>
        </div>
      )}
      {renderPhotos('training_certificate', 'CERTIFICATE OF TRAINING')}
      {renderPhotos('training_test_result', 'PRE AND POST TEST RESULT (If applicable)')}
      {renderPhotos('training_documentation', 'DOCUMENTATION OF TRAINING PROPER (PICTURES WITH DETAILED EXPLANATION)')}

      {photos.some(x => ['exam_certificate', 'exam_test_result', 'exam_documentation'].includes(x.type)) && (
        <div className="a4-page page-break portfolio-document d-flex flex-column justify-content-between">
          <PageHeader companyLogoPath={p?.company_logo_path} />
          <div style={{ textAlign: 'center', margin: 'auto 0' }}>
            <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', textAlign: 'center' }}>CERTIFICATION EXAM (ONLINE / F2F)</h1>
          </div>
          <div className="page-number">{nextPg()}</div>
        </div>
      )}
      {renderPhotos('exam_certificate', 'CERTIFICATION')}
      {renderPhotos('exam_test_result', 'PRE AND POST TEST RESULT')}
      {renderPhotos('exam_documentation', 'DOCUMENTATION OF DURING AND PREPARATION OF EXAM (PICTURES WITH DETAILED EXPLANATION)')}
    </div>
  )
}

export default PortfolioPreview;
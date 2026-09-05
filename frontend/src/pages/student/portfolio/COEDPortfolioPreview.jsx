import React, { useState, useEffect, useRef } from 'react';
import { useReactToPrint } from 'react-to-print';
import { Link } from 'react-router-dom';
import PageError from '../../../components/PageError';
import api from '../../../services/api';
import '../../../assets/css/portfolio-print.css';
import WeeklyInternshipJournal from '../../../components/portfolio/WeeklyInternshipJournal';
import DailyTimeRecord from '../../../components/portfolio/DailyTimeRecord';
import { PrintFO24, PrintFO03, PrintFO22, PrintFO23 } from '../../../components/portfolio/EvaluationsPreview';
import { displayLabel } from '../../../utils/displayLabel';

const TocRow = ({ label, page = '', bold = false, indent = 0 }) => (
  <div style={{
    display: 'flex', justifyContent: 'space-between', paddingLeft: `${indent}px`,
    fontWeight: bold ? 'bold' : 'normal', fontSize: '10.5pt', marginBottom: '4px', lineHeight: '1.2'
  }}>
    <span>{label}</span>
    <span>{page}</span>
  </div>
);

function COEDPortfolioPreview() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const printRef = useRef(null);
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: 'COED_Portfolio_Preview',
  });

  useEffect(() => {
    setLoading(true);
    api.get('/student/portfolio')
      .then(res => setData(res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to load portfolio.'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="text-center p-5"><i className="fa fa-spinner fa-spin fa-3x text-muted"></i></div>;
  if (error) return <PageError message={error} />;
  if (!data || !data.internship) return <PageError message="No internship record found." />;

  const p = data.portfolio || {};
  const custom = p.custom_fields || {};
  const user = data.user || {};
  const profile = user.student_profile || {};

  // --- Extracted Information for the Cover Page ---
  const fullName = `${profile.first_name || ''} ${profile.middle_name ? profile.middle_name[0] + '.' : ''} ${profile.last_name || ''}`.trim();
  const program = displayLabel(profile.program, 'Bachelor of Elementary Education');
  const section = profile.section || '';
  const schoolYear = data.internship.school_year || '2025 - 2026';

  // Pictures and Organization Data
  const studentPicture = profile.profile_picture || user.avatar_url;
  const deploymentSchool = data.internship.company?.company_name || custom.cooperating_school || 'BIGAA ELEMENTARY SCHOOL';

  // Supervisors
  const supervisor = data.internship.supervisor?.supervisor_profile;
  const faculty = data.internship.faculty?.faculty_profile;
  const cooperatingTeacher = supervisor ? `${supervisor.last_name}, ${supervisor.first_name}` : '';
  const facultySupervisor = faculty ? `${faculty.last_name}, ${faculty.first_name}` : '';

  const PageWrap = ({ children, title = '' }) => (
    <div className="a4-page page-break portfolio-document position-relative">
      {/* Background Image */}
      <div style={{
        position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, zIndex: 0,
        backgroundImage: 'url(/images/coed_bg.jpg)', backgroundSize: '100% 100%', backgroundPosition: 'center', backgroundRepeat: 'no-repeat'
      }} />

      {/* Footer Wave Image */}
      <div style={{
        position: 'absolute', bottom: 0, left: 0, right: 0, height: '130px', zIndex: 1,
        backgroundImage: 'url(/images/coed_page_footer_bg.png)', backgroundSize: '100% 100%', backgroundPosition: 'bottom center', backgroundRepeat: 'no-repeat'
      }} />

      {/* HTE LOGO OVERLAY */}
      <div style={{
        position: 'absolute', top: '60px', right: '20px', width: '80px', height: '80px',
        zIndex: 3, backgroundColor: '#ffffff', border: '1px solid #000', display: 'flex',
        alignItems: 'center', justifyContent: 'center', textAlign: 'center',
        fontSize: '12pt', fontFamily: 'Arial, sans-serif', color: '#000'
      }}>
        Logo of<br />HTE
      </div>

      <div style={{ position: 'relative', zIndex: 2, paddingTop: '120px', height: '100%', display: 'flex', flexDirection: 'column' }}>
        {title && (
          <div style={{ textAlign: 'center', margin: '0 0 30px 0', display: 'flex', justifyContent: 'center' }}>
            <div style={{
              backgroundImage: 'url(/images/coed_page_title_bg.png)', backgroundSize: '100% 100%',
              backgroundPosition: 'center', backgroundRepeat: 'no-repeat', width: '500px',
              minHeight: '60px', padding: '15px 40px', display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
              <h2 style={{
                color: '#FFD700', fontFamily: "Cambria, serif", fontStyle: 'italic',
                fontSize: '16pt', fontWeight: '100', margin: 0, textShadow: '1px 1px 3px rgba(0,0,0,0.5)',
                textAlign: 'center', lineHeight: '1.2', whiteSpace: 'normal', wordWrap: 'break-word'
              }}>
                {title}
              </h2>
            </div>
          </div>
        )}
        <div style={{ flex: 1, fontSize: '11pt', lineHeight: '1.6', textAlign: 'justify', padding: '0 40px 120px 40px' }}>
          {children}
        </div>
      </div>
    </div>
  );

  return (
    <div style={{ background: '#e5e5e5', minHeight: '100vh', paddingBottom: '60px' }}>
      <div className="no-print" style={{
        position: 'sticky', top: 0, left: 0, zIndex: 1000, background: '#1a1a2e', color: '#fff',
        padding: '10px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        boxShadow: '0 2px 8px rgba(0,0,0,0.4)'
      }}>
        <div className="d-flex align-items-center gap-3">
          <Link to="/student/portfolio" style={{ color: '#ccc', textDecoration: 'none', fontSize: '14px' }}>
            <i className="fa fa-arrow-left me-2"></i>Back to Builder
          </Link>
          <span style={{ color: '#555' }}>|</span>
          <span style={{ fontWeight: 600, fontSize: '15px' }}>COED Portfolio Preview</span>
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

      <div ref={printRef} className="portfolio-print-container">

        {/* ========================================= */}
        {/*          MAGAZINE-STYLE TITLE PAGE        */}
        {/* ========================================= */}
        <div className="a4-page force-page-break portfolio-document cover-page-banner" style={{ position: 'relative', padding: 0, overflow: 'hidden' }}>
          <div style={{
            position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, zIndex: 0,
            backgroundImage: 'url(/images/coed_cover_bg.jpg)', backgroundSize: '100% 100%', backgroundPosition: 'center', backgroundRepeat: 'no-repeat'
          }} />

          <div style={{ position: 'relative', zIndex: 1, width: '100%', height: '100%' }}>

            <div style={{ position: 'absolute', top: '56%', left: '10%', right: 0, textAlign: 'center' }}>
              <h2 style={{
                fontFamily: "Impact, 'Arial Black', sans-serif", fontSize: '25pt', color: '#fff',
                WebkitTextStroke: '1.5px #000', textShadow: '2px 2px 4px rgba(0,0,0,0.7)', margin: '0 0 5px 0', lineHeight: '0.5'
              }}>
                {deploymentSchool.toUpperCase()}
              </h2>
              <h3 style={{
                fontFamily: "Impact, 'Arial Black', sans-serif", fontSize: '22pt', color: '#fff',
                WebkitTextStroke: '1.5px #000', textShadow: '2px 2px 4px rgba(0,0,0,0.7)', margin: 0
              }}>
                S. Y. {schoolYear}
              </h3>
            </div>

            <div style={{ position: 'absolute', top: '76%', left: '7%', right: '50px', textAlign: 'center' }}>
              <h2 style={{ fontFamily: "Georgia, 'Times New Roman', serif", fontStyle: 'italic', color: '#FFD700', fontSize: '19pt', margin: '0 0 5px 0', whiteSpace: 'normal' }}>
                {'Christian Hero A. Valinado'}
              </h2>
              <h4 style={{ fontFamily: "Arial, sans-serif", fontWeight: 'bold', color: '#fff', fontSize: '11pt', margin: '0 0 15px 0', letterSpacing: '1px' }}>
                PRE-SERVICE TEACHER
              </h4>
              <h3 style={{
                fontFamily: "Georgia, 'Times New Roman', serif", fontStyle: 'italic', color: '#FFD700',
                fontSize: '20pt', margin: '0 auto', lineHeight: '1.2', whiteSpace: 'normal', width: '300px', textAlign: 'center'
              }}>
                {program}
              </h3>
            </div>

            <div style={{ position: 'absolute', top: '96%', height: '100px', zIndex: 4 }}>
              <div style={{ position: 'absolute', top: '30px', right: '50px', textAlign: 'center', width: '20px' }}>
                <p style={{ fontFamily: "Georgia, 'Times New Roman', serif", fontStyle: 'italic', color: '#FFD700', fontSize: '15pt', margin: '0 0 2px 0', whiteSpace: 'nowrap' }}>
                  {facultySupervisor || 'Dr. Rhea M. Dizon'}
                </p>
                <p style={{ fontFamily: "Arial, sans-serif", fontWeight: 'bold', color: '#fff', fontSize: '11pt', margin: 0, letterSpacing: '0.5px', whiteSpace: 'nowrap' }}>
                  INTERNSHIP SUPERVISOR
                </p>
              </div>

              <div style={{ position: 'absolute', bottom: '25px', left: '250px', textAlign: 'center', width: '120px' }}>
                <p style={{ fontFamily: "Georgia, 'Times New Roman', serif", fontStyle: 'italic', color: '#FFD700', fontSize: '16pt', margin: 0, whiteSpace: 'nowrap' }}>
                  {section || '4EED-A'}
                </p>
              </div>

              <div style={{ position: 'absolute', top: '30px', bottom: '25px', left: '490px', textAlign: 'center', width: '120px' }}>
                <p style={{ fontFamily: "Georgia, 'Times New Roman', serif", fontStyle: 'italic', color: '#FFD700', fontSize: '15pt', margin: '0 0 2px 0', whiteSpace: 'nowrap' }}>
                  {cooperatingTeacher || 'Mrs. Eva S. Andaya'}
                </p>
                <p style={{ fontFamily: "Arial, sans-serif", fontWeight: 'bold', color: '#fff', fontSize: '11pt', margin: 0, letterSpacing: '0.5px', whiteSpace: 'nowrap' }}>
                  COOPERATING TEACHER
                </p>
              </div>
            </div>

            <div style={{
              position: 'absolute', right: '0', bottom: '5%', width: '60%', height: '85%', zIndex: 2,
              left: '65%', top: '8.9%', clipPath: 'polygon(0 0, 100% 0, 100% 82%, 85% 86%, 65% 91%, 40% 96%, 0 100%)'
            }}>
              <img src="/images/id-uniform-COED.png" alt="Student Outline" style={{ width: '100%', height: '100%', objectFit: 'contain', objectPosition: 'bottom right' }} />
            </div>

            <div style={{ position: 'absolute', bottom: '11%', right: '3%', width: '250px', height: '350px', zIndex: 2, display: 'flex', alignItems: 'flex-end', justifyContent: 'center' }}>
              {studentPicture ? (
                <img
                  src={studentPicture} alt="Student"
                  style={{
                    width: '100%', height: '100%', objectFit: 'contain', objectPosition: 'bottom',
                    clipPath: 'polygon(0 0, 100% 0, 100% 80%, 90% 84%, 75% 88%, 55% 93%, 30% 97%, 0 100%)'
                  }}
                />
              ) : (
                <div style={{
                  width: '220px', height: '300px', backgroundColor: 'rgba(255,255,255,0.8)',
                  border: '2px dashed #333', display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: '#333', fontWeight: 'bold', marginBottom: '10px', textAlign: 'center'
                }}>
                  Transparent/Cutout<br />Picture Here
                </div>
              )}
            </div>
          </div>
        </div>
        {/* ========================================= */}

        {/* PRELIMINARIES */}
        <PageWrap title="PNC Vision, Mission & Core Values">
          <div style={{ textAlign: 'center', marginBottom: '30px' }}>
            <h4 style={{ fontWeight: 'bold' }}>Vision</h4>
            <p>Pamantasan ng Cabuyao shall be the premier institution of higher learning in CALABARZON, cultivating dynamic and competent individuals.</p>
          </div>
          <div style={{ textAlign: 'center', marginBottom: '30px' }}>
            <h4 style={{ fontWeight: 'bold' }}>Mission</h4>
            <p>Pamantasan ng Cabuyao is committed to providing quality education, advanced research, and community extension programs.</p>
          </div>
          <div style={{ textAlign: 'center' }}>
            <h4 style={{ fontWeight: 'bold' }}>Core Values (P-N-C)</h4>
            <p><strong>P</strong>ersonal Dignity<br /><strong>N</strong>urturing Community<br /><strong>C</strong>ommitment to Excellence</p>
          </div>
        </PageWrap>

        <PageWrap title="Teacher's Prayer">
          <div style={{ whiteSpace: 'pre-wrap', fontStyle: 'italic', fontSize: '12pt', textAlign: 'center', marginTop: '50px' }}>
            {custom.teachers_prayer || 'No prayer provided.'}
          </div>
        </PageWrap>

        <PageWrap title="Acknowledgement">
          <div style={{ whiteSpace: 'pre-wrap' }}>{custom.acknowledgement || 'No acknowledgement provided.'}</div>
        </PageWrap>

        <PageWrap title="TABLE OF CONTENTS">
          <div style={{ textAlign: 'right', fontWeight: 'bold', marginBottom: '10px' }}>PAGE</div>
          <TocRow label="PNC VISION, MISSION & CORE VALUES" bold />
          <TocRow label="TEACHER'S PRAYER" bold />
          <TocRow label="ACKNOWLEDGEMENT" bold />
          <TocRow label="UPDATED RESUME" bold />
          <TocRow label="APPLICATION LETTER" bold />
          <TocRow label="TEACHER'S CREED/PERSONAL TEACHING COMMITMENT" bold />
          <TocRow label="I. INTRODUCTION" bold />
          <TocRow label="Personal Teaching Philosophy" indent={20} />
          <TocRow label="Why I chose teaching as a profession" indent={20} />
          <TocRow label="My beliefs about learners and learning" indent={20} />
          <TocRow label="My goals as a future elementary teacher" indent={20} />
          <TocRow label="II. SCHOOL PROFILE" bold />
          <TocRow label="History of the Public School" indent={20} />
          <TocRow label="DepEd Vision and Mission" indent={20} />
          <TocRow label="School Vision and Mission" indent={20} />
          <TocRow label="Organizational Structure" indent={20} />
          <TocRow label="School Programs and Initiatives" indent={20} />
          <TocRow label="Description of Learner Population" indent={20} />
          <TocRow label="III. CLASSROOM DOCUMENTATION" bold />
          <TocRow label="Observation and Participation Logs" indent={20} />
          <TocRow label="Classroom Management Practices" indent={20} />
          <TocRow label="Teaching Environment" indent={20} />
          <TocRow label="WEEKLY INTERNSHIP JOURNALS (FO-31)" bold />
          <TocRow label="IV. LESSON PLANS" bold />
          <TocRow label="Best Lesson Plans" indent={20} />
          <TocRow label="V. TEACHING ARTIFACTS" bold />
          <TocRow label="VI. REFLECTIONS" bold />
          <TocRow label="VII. EXPERIENCES AND PROFESSIONAL GROWTH" bold />
          <TocRow label="VIII. APPENDICES" bold />
          <TocRow label="DAILY TIME RECORD (FO-30)" indent={20} />
          <TocRow label="EVALUATION FORMS (FO-03, FO-22, FO-23, FO-24)" indent={20} />
        </PageWrap>

        <PageWrap title="Teacher's Creed / Personal Teaching Commitment">
          <div style={{ whiteSpace: 'pre-wrap', fontStyle: 'italic', fontSize: '12pt', textAlign: 'center', marginTop: '50px' }}>
            "{custom.teachers_creed || 'No creed provided.'}"
          </div>
        </PageWrap>

        {/* I. INTRODUCTION */}
        <PageWrap title="I. INTRODUCTION">
          <h4 style={{ fontWeight: 'bold', marginTop: '20px' }}>A. Personal Teaching Philosophy</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.teaching_philosophy || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>B. Why I Chose Teaching as a Profession</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.why_teaching || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>C. My Beliefs about Learners and Learning</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.beliefs_about_learners || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>D. My Goals as a Future Elementary Teacher</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.goals_as_teacher || 'N/A'}</p>
        </PageWrap>

        {/* II. SCHOOL PROFILE */}
        <PageWrap title="II. SCHOOL PROFILE">
          <h4 style={{ fontWeight: 'bold', marginTop: '20px' }}>Brief History of the Cooperating School</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.cooperating_school_history || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>DepEd Vision & Mission</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.deped_vision_mission || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>School Vision and Mission</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.school_vision_mission || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Organizational Structure</h4>
          <p style={{ fontStyle: 'italic' }}>Please see attached Organizational Chart in Appendices.</p>

          <h4 style={{ fontWeight: 'bold', marginTop: '20px' }}>School Programs and Initiatives</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.school_programs || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Description of Learner Population</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.learner_population || 'N/A'}</p>
        </PageWrap>

        {/* III. CLASSROOM DOCUMENTATION & JOURNALS */}
        {(() => {
          const journals = data?.internship?.journals || [];

          // Filter out invalid paths first so we don't map over nulls
          const validJournals = journals.filter(j =>
            !j.file_path || j.file_path.endsWith(".pdf")
          );

          // Render default if no valid journals exist
          if (validJournals.length === 0) {
            return (
              <PageWrap title="III. CLASSROOM DOCUMENTATION">
                <div style={{ textAlign: 'center', marginBottom: '15px' }}>
                  <h4 style={{ fontWeight: 'bold', fontStyle: 'italic', marginTop: '0', fontSize: '12pt', color: '#000' }}>
                    A. Observation and Participation Logs
                  </h4>
                  <h5 style={{ fontWeight: 'bold', fontSize: '11pt', margin: '5px 0 0 0', color: '#000' }}>
                    Weekly Internship Log (Weekly Journal)
                  </h5>
                  <div style={{
                    display: 'flex', justifyContent: 'center', width: '100%',
                    transform: 'scale(0.57)',
                    transformOrigin: 'top center'
                  }}>
                    <WeeklyInternshipJournal studentName={fullName} program={program} />
                  </div>
                </div>
              </PageWrap>
            );
          }

          // Render each valid journal on its OWN page
          return validJournals.map((j, index) => (
            <PageWrap
              key={j.id}
              title={index === 0 ? "III. CLASSROOM DOCUMENTATION" : ""}
            >
              {/* Only show the subheadings on the FIRST journal page */}
              {index === 0 && (
                <div style={{ textAlign: 'center', marginBottom: '15px', marginTop: '-10px' }}>
                  <h4 style={{ fontWeight: 'bold', fontStyle: 'italic', margin: '0 0 5px 0', fontSize: '12pt', color: '#000' }}>
                    A. Observation and Participation Logs
                  </h4>
                  <h5 style={{ fontWeight: 'bold', fontSize: '11pt', margin: '0', color: '#000' }}>
                    Weekly Internship Log (Weekly Journal)
                  </h5>
                </div>
              )}

              {/* Simple Text Header */}
              <div style={{ textAlign: 'center', marginBottom: '15px', marginTop: index === 0 ? '0px' : '30px' }}>
                <h3 style={{ fontSize: '22pt', fontWeight: 'normal', fontFamily: 'Arial, sans-serif' }}>
                  WEEK {j.week_number || j.week || index + 1}
                </h3>
              </div>

              {/* Centered and Sized Journal Component */}
              <div style={{
                display: 'flex',
                justifyContent: 'center',
                width: '100%',
                transform: 'scale(0.57)', /* Slightly reduced to prevent bottom overlap */
                transformOrigin: 'top center'
              }}>
                <WeeklyInternshipJournal
                  studentName={fullName}
                  program={program}
                  weekNumber={j.week_number || j.week || index + 1}
                  date={j.date}
                  accomplishment={j.activities_summary || j.accomplishment}
                  difficulties={j.challenges || j.difficulties}
                  insights={j.learnings || j.insights}
                />
              </div>
            </PageWrap>
          ));
        })()}

        {/* CONTINUATION OF SECTION III */}
        <PageWrap>
          <h4 style={{ fontWeight: 'bold' }}>B. Classroom Management Practices</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.classroom_management || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>C. Teaching Environment</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.teaching_environment || 'N/A'}</p>
        </PageWrap>

        {/* IV. LESSON PLANS (Placeholder for print) */}
        <PageWrap title="IV. LESSON PLANS">
          <div style={{ textAlign: 'center', marginTop: '100px', color: '#666' }}>
            <i className="fa fa-file-pdf fa-4x mb-3"></i>
            <h3>Lesson Plans</h3>
            <p>Please attach the printed lesson plans after this page.</p>
          </div>
        </PageWrap>

        {/* V. TEACHING ARTIFACTS (Placeholder) */}
        <PageWrap title="V. TEACHING ARTIFACTS">
          <div style={{ textAlign: 'center', marginTop: '100px', color: '#666' }}>
            <i className="fa fa-images fa-4x mb-3"></i>
            <h3>Instructional Materials, Worksheets, & Assessment Tools</h3>
            <p>Please attach the printed teaching artifacts after this page.</p>
          </div>
        </PageWrap>

        {/* VI. REFLECTIONS */}
        <PageWrap title="VI. REFLECTIONS">
          <h4 style={{ fontWeight: 'bold', marginTop: '20px' }}>How did internship shape me as a teacher?</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.culminating_reflection || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>What strengths did I discover?</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.strengths_discovered || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>What areas need improvement?</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.areas_for_improvement || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Am I ready for the teaching profession?</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.ready_for_profession || 'N/A'}</p>
        </PageWrap>

        {/* VII. EXPERIENCES */}
        <PageWrap title="VII. EXPERIENCES AND PROFESSIONAL GROWTH">
          <h4 style={{ fontWeight: 'bold', marginTop: '20px' }}>Phase 1: Observation Phase</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.narrative_observation || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Phase 2: Assisted Teaching Phase</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.narrative_assisted || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Phase 3: Independent Teaching Phase</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.narrative_independent || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Phase 4: Final Demonstration Teaching</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.narrative_final_demo || 'N/A'}</p>

          <h4 style={{ fontWeight: 'bold' }}>Highlight: Growth in Confidence & Management</h4>
          <p style={{ whiteSpace: 'pre-wrap', marginBottom: '20px' }}>{custom.highlight_growth || 'N/A'}</p>
        </PageWrap>

        {/* VIII. APPENDICES - Just the Text */}
        <PageWrap title="VIII. APPENDICES">
          <div style={{ textAlign: 'center', marginTop: '100px', color: '#666' }}>
            <i className="fa fa-paperclip fa-4x mb-3"></i>
            <h3>Appendices</h3>
            <p>Include Endorsement Letter, Acceptance Letter, Training Plan, Journals, Evaluations, Certificate of Completion, and Clearance after this page.</p>
          </div>
        </PageWrap>

        {/* DAILY TIME RECORD (Moved to its own page with scaling) */}
        <PageWrap>
          <div style={{
            display: 'flex', justifyContent: 'center', width: '100%',
            transform: 'scale(0.57)', transformOrigin: 'top center'
          }}>
            <DailyTimeRecord
              studentName={fullName}
              program={program}
              companyName={deploymentSchool}
              supervisorName={cooperatingTeacher}
              companyLogoPath={p?.company_logo_path}
            />
          </div>
        </PageWrap>

        {/* EVALUATIONS (Scaled down to fit inside the wrapper) */}
        {(() => {
          const evals = data?.internship?.evaluations || [];
          const fo03 = evals.find(e => e.form_type === 'FO-03');
          const fo22 = evals.find(e => e.form_type === 'FO-22');
          const fo23 = evals.find(e => e.form_type === 'FO-23');
          const fo24 = evals.find(e => e.form_type === 'FO-24');

          return (
            <>
              <PageWrap>
                <div style={{ display: 'flex', justifyContent: 'center', width: '100%', transform: 'scale(0.57)', transformOrigin: 'top center' }}>
                  <PrintFO03 evalData={fo03 || null} internship={data?.internship} />
                </div>
              </PageWrap>
              <PageWrap>
                <div style={{ display: 'flex', justifyContent: 'center', width: '100%', transform: 'scale(0.57)', transformOrigin: 'top center' }}>
                  <PrintFO22 evalData={fo22 || null} internship={data?.internship} />
                </div>
              </PageWrap>
              <PageWrap>
                <div style={{ display: 'flex', justifyContent: 'center', width: '100%', transform: 'scale(0.57)', transformOrigin: 'top center' }}>
                  <PrintFO23 evalData={fo23 || null} internship={data?.internship} />
                </div>
              </PageWrap>
              <PageWrap>
                <div style={{ display: 'flex', justifyContent: 'center', width: '100%', transform: 'scale(0.57)', transformOrigin: 'top center' }}>
                  <PrintFO24 evalData={fo24 || null} internship={data?.internship} />
                </div>
              </PageWrap>
            </>
          );
        })()}

      </div>
    </div>
  );
}

export default COEDPortfolioPreview;
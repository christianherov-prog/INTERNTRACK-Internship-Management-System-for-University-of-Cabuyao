import React, { useState, useEffect, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import { Link } from 'react-router-dom'
import PageError from '../../../components/PageError'
import api from '../../../services/api'
import '../../../assets/css/portfolio-print.css'
import { PaginatedTextSection, PaginatedImageCollection } from '../../../components/portfolio/AutoPaginatedFlow'
import {
  NUR_COURSE,
  NUR_ROTATIONS,
  NUR_COLLEGE,
  NUR_ADDRESS,
  emptyNursingFields,
  ROTATION_UPLOADS,
  GLOBAL_UPLOADS,
  rotationDocType,
  globalDocType,
} from './nursingPortfolioStructure'

// --- NEW CSS FOR AUTOMATIC PAGE NUMBERING ---
const pageNumberStyles = `
  .portfolio-print-container {
    counter-reset: a4-page-counter;
  }
  /* Ensure every page increments the counter */
  .a4-page {
    counter-increment: a4-page-counter;
    position: relative;
  }
  /* The styling perfectly matches image_0e5938.png */
  .vertical-page-number {
    position: absolute;
    bottom: 15%;
    right: 45px;
    transform: rotate(-90deg);
    transform-origin: bottom right;
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    font-family: 'Cambria (Headings)', 'Times New Roman', serif;
    user-select: none;
    pointer-events: none;
    z-index: 50;
  }
  .vertical-page-number::before {
    content: "Page";
    font-size: 14pt;
    font-weight: normal;
    color: #000;
  }
  .vertical-page-number::after {
    content: counter(a4-page-counter);
    font-size: 24pt;
    font-weight: bold;
    color: #000;
    line-height: 1;
  }
`;

function PncWatermark() {
  return (
    <img 
      style={{ opacity: 0.2, width: '80%', height: '70%', objectFit: 'contain', margin: '0 auto', display: 'block', left: '20px', bottom: '20px', position: 'relative' }} 
      src="/images/pnc-logo.png" 
      alt="CCS Logo" 
      className="pnc-page-watermark" 
      aria-hidden="true" 
    />
  )
}

// --- UPDATED FOOTER ---
function ChasPageFooter() {
  return (
    <div className="chas-page-footer">
      {/* CSS Counter automatically injects the correct page number on ALL pages */}
      <div className="vertical-page-number"></div>
      
      <img 
        style={{ width: '80%', height: '70%', objectFit: 'contain', margin: '0 auto', display: 'block', left: '20px', bottom: '20px', position: 'relative' }} 
        src="/images/chas-nursing-footer.png" 
        alt="BS Nursing — Dangal ng Bayan" 
      />
    </div>
  )
}

function ChasPageHeader() {
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
      fontSize: '22pt', color: '#0B5D2A', fontWeight: 'bold', textAlign: 'center'
    },
    pamantasan: { fontFamily: 'Copperplate Gothic Light', margin: 0, fontSize: '12pt', textAlign: 'center', marginRight: '40px' },
    department: { fontFamily: 'Copperplate Gothic Light', margin: '5px 0 2px 0', fontSize: '9pt', fontWeight: 'normal', textAlign: 'center' },
    address: { fontSize: '9pt' },
  }
  return (
    <>
      <PncWatermark />
      <div style={{ ...styles.headerContainer, lineHeight: '0.2' }}>
        <div style={styles.sideCol}>
          <img src="/images/pnc-logo.png" alt="UC Logo" style={{ width: '78px', height: '78px', objectFit: 'contain' }} />
        </div>
        <div style={styles.centerCol}>
          <p style={styles.republic}>Republic of the Philippines</p>
          <h1 style={styles.university}>University of Cabuyao</h1>
          <p style={styles.pamantasan}>(Pamantasan ng Cabuyao)</p>
          <h2 style={styles.department}>COLLEGE OF HEALTH AND ALLIED SCIENCES</h2>
          <p style={styles.address}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</p>
        </div>
        <div style={styles.sideCol}>
          <img src="/images/chas-logo.png" alt="CHAS Logo" style={{ width: '78px', height: '78px', objectFit: 'contain' }} />
        </div>
      </div>
    </>
  )
}

// --- UPDATED PAGE WRAPPER ---
// Removed manual pageNumber prop since the footer handles it globally now
function PageWrapper({ children, tocId }) {
  return (
    <div className="a4-page portfolio-document" data-toc-id={tocId} style={{
      position: 'relative', minHeight: '1122px', paddingBottom: '100px', boxSizing: 'border-box',
    }}>
      {children}
      <ChasPageFooter />
    </div>
  )
}

const TocRow = ({ label, indent = 0, bold = false }) => (
  <tr>
    <td style={{
      border: '1px solid black', padding: '4px 8px', paddingLeft: indent ? `${indent + 8}px` : '8px',
      fontWeight: bold ? 'bold' : 'normal', fontSize: '10pt', fontFamily: 'Arial, sans-serif',
    }}>{label}</td>
    <td style={{ border: '1px solid black', padding: '4px 8px', textAlign: 'center', width: '60px', fontSize: '10pt' }} />
  </tr>
)

function coverMonthYear(internship) {
  const raw = internship?.start_date || internship?.created_at
  const d = raw ? new Date(raw) : new Date()
  if (Number.isNaN(d.getTime())) return new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' })
  return d.toLocaleString('en-US', { month: 'long', year: 'numeric' })
}

function displayName(profile, user) {
  if (!profile) return user?.name || '________________'
  const last = (profile.last_name || '').toUpperCase()
  const first = (profile.first_name || '').toUpperCase()
  const mi = profile.middle_name ? ` ${String(profile.middle_name)[0].toUpperCase()}.` : ''
  if (!last && !first) return user?.name || '________________'
  return `${last}, ${first}${mi}`.trim()
}

function NursingPortfolioPreview() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const printRef = useRef(null)
  const handlePrint = useReactToPrint({ contentRef: printRef, documentTitle: 'Nursing_Internship_Portfolio' })

  useEffect(() => {
    api.get('/student/portfolio')
      .then((res) => setData(res.data))
      .catch((err) => setError(err.response?.data?.message || 'Failed to load portfolio.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <i className="fa fa-spinner fa-spin fa-2x mb-3" aria-hidden="true" />
        <div className="small">Loading preview…</div>
      </div>
    )
  }
  if (error) {
    return (
      <div style={{ background: '#e5e5e5', minHeight: '100vh', padding: '24px' }}>
        <PageError message={error} />
        <div className="text-center mt-3"><Link to="/student/portfolio" className="text-muted">Back to Builder</Link></div>
      </div>
    )
  }

  const internship = data?.internship
  const p = internship?.portfolio || data?.portfolio || {}
  const user = data?.user || {}
  const profile = user.student_profile || {}
  const photos = p.photos || []
  const saved = p.custom_fields?.nursing || {}
  
  const empty = typeof emptyNursingFields === 'function' ? emptyNursingFields() : (emptyNursingFields || {})
  const emptyRots = empty.rotations || {}
  const savedRots = saved.rotations || {}

  const fields = {
    ...empty,
    ...saved,
    rotations: {
      1: { ...(emptyRots[1] || {}), ...(savedRots[1] || savedRots['1'] || {}) },
      2: { ...(emptyRots[2] || {}), ...(savedRots[2] || savedRots['2'] || {}) },
      3: { ...(emptyRots[3] || {}), ...(savedRots[3] || savedRots['3'] || {}) },
      4: { ...(emptyRots[4] || {}), ...(savedRots[4] || savedRots['4'] || {}) },
      5: { ...(emptyRots[5] || {}), ...(savedRots[5] || savedRots['5'] || {}) },
    },
  }
  
  const programName = typeof user.program === 'string'
    ? user.program
    : (user.program?.name || profile.program?.name || 'Bachelor of Science in Nursing')
  const collegeName = typeof user.department === 'object'
    ? (user.department?.name || NUR_COLLEGE)
    : (user.department || profile.department?.name || NUR_COLLEGE)
  const studentName = displayName(profile, user)
  const monthYear = coverMonthYear(internship)
  const bodyFont = { fontFamily: 'Arial, sans-serif', fontSize: '11pt', lineHeight: 1.55, textAlign: 'justify' }

  // We no longer need nextPg for numbering, but we can pass it if AutoPaginatedFlow expects it
  let pageCounter = 1
  const nextPg = () => pageCounter++
  const matchesType = (item, type) => item.type === type || item.document_type === type || item.original_type === type

  const renderPhotos = (type, title) => (
    <PaginatedImageCollection
      list={photos.filter((x) => matchesType(x, type))}
      title={title}
      nextPg={nextPg}
      pageHeaderComponent={ChasPageHeader}
      pageFooterComponent={ChasPageFooter}
      emptyMessage="[ Draft Preview Mode: This section is currently empty. Upload it in the Portfolio Builder when ready. ]"
    />
  )

  const tocRows = [
    { label: 'A. Cover Page', bold: true },
    { label: 'B. Biographical Sketch', bold: true },
    { label: 'C. Acknowledgement', bold: true },
    { label: 'D. Table of Contents', bold: true },
    { label: 'E. Host Training Establishment Profile', bold: true },
    ...NUR_ROTATIONS.map((r) => {
      const site = fields.rotations[r.id] || {}
      return {
        label: `${r.title}${site.hte_name ? ` — ${site.hte_name}` : ''}`,
        indent: 16,
      }
    }),
    { label: 'F. Internship Proper', bold: true },
    { label: 'Narrative & Insights of Internship Learning Experiences', indent: 16 },
    { label: 'Recommendations', indent: 16 },
    { label: 'a. Students', indent: 32 },
    { label: 'b. Internship Program', indent: 32 },
    { label: 'c. Curriculum', indent: 32 },
    { label: 'd. Host Training Establishments', indent: 32 },
    { label: 'Accomplished Internship Training Plan', indent: 16 },
    { label: 'Weekly Student Internship Journal', indent: 16 },
    { label: 'G. Appendices', bold: true },
    ...GLOBAL_UPLOADS.map((i) => ({ label: i.label, indent: 16 })),
    ...NUR_ROTATIONS.flatMap((r) => ROTATION_UPLOADS.filter((u) => !['hte_photos', 'training_plan', 'journal', 'documentation'].includes(u.suffix)).map((u) => ({
      label: `${u.label} (${r.title})`,
      indent: 16,
    }))),
  ]

  return (
    <div style={{ background: '#e5e5e5', minHeight: '100vh', paddingBottom: '60px' }}>
      
      {/* Injecting CSS Counters for automatic page numbering across all dynamic pages */}
      <style>{pageNumberStyles}</style>

      <div className="no-print" style={{
        position: 'sticky', top: 0, left: 0, zIndex: 1000, background: '#1a1a2e', color: '#fff',
        padding: '10px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
      }}>
        <div className="d-flex align-items-center gap-3">
          <Link to="/student/portfolio" style={{ color: '#ccc', textDecoration: 'none', fontSize: '14px' }}>
            <i className="fa fa-arrow-left me-2"></i>Back to Builder
          </Link>
          <span style={{ color: '#555' }}>|</span>
          <span style={{ fontWeight: 600, fontSize: '15px' }}>BS Nursing Portfolio Preview</span>
        </div>
        <button type="button" onClick={handlePrint} style={{ background: '#16a34a', color: '#fff', border: 'none', padding: '8px 20px', borderRadius: '6px', fontWeight: 600, cursor: 'pointer' }}>
          <i className="fa fa-print me-2"></i>Browser Print
        </button>
      </div>

      <div ref={printRef} className="portfolio-print-container">
        {/* Manual page wrappers no longer need the pageNumber={nextPg()} prop */}
        <PageWrapper tocId="cover">
          <ChasPageHeader />
          <div style={{ ...bodyFont, textAlign: 'center', paddingTop: '80px' }}>
            <h2 style={{ letterSpacing: '4px' }}>INTERNSHIP PORTFOLIO</h2>
            <p style={{ marginTop: '28px', fontWeight: 700, textAlign: 'center', textIndent: 0 }}>{studentName}</p>
            <p style={{ textAlign: 'center', textIndent: 0 }}>{programName}</p>
            <p style={{ textAlign: 'center', textIndent: 0 }}>{NUR_COURSE}</p>
            <p style={{ textAlign: 'center', textIndent: 0 }}>{monthYear}</p>
            <p style={{ fontWeight: 700, textAlign: 'center', textIndent: 0, color: '#0B5D2A' }}>{collegeName}</p>
          </div>
        </PageWrapper>

        <PaginatedTextSection
          pageHeaderComponent={ChasPageHeader}
          pageFooterComponent={ChasPageFooter}
          nextPg={nextPg}
          pageTitle="Biographical Sketch"
          sections={[{ title: '', text: fields.bio_sketch || '[ Draft Preview Mode: Write your biographical sketch in the Portfolio Builder. ]' }]}
        />
        <PaginatedTextSection
          pageHeaderComponent={ChasPageHeader}
          pageFooterComponent={ChasPageFooter}
          nextPg={nextPg}
          pageTitle="Acknowledgement"
          sections={[{ title: '', text: fields.acknowledgement || '[ Draft Preview Mode: Write your acknowledgement in the Portfolio Builder. ]' }]}
        />

        <PageWrapper tocId="toc">
          <ChasPageHeader />
          <table style={{ width: '100%', borderCollapse: 'collapse', fontFamily: 'Arial, sans-serif' }}>
            <thead>
              <tr>
                <th style={{ border: '1px solid black', padding: '6px 8px', textAlign: 'left', fontSize: '10pt' }}>TABLE OF CONTENTS</th>
                <th style={{ border: '1px solid black', padding: '6px 8px', width: '60px', fontSize: '10pt' }}>Page</th>
              </tr>
            </thead>
            <tbody>
              {tocRows.map((row, idx) => <TocRow key={idx} label={row.label} indent={row.indent || 0} bold={row.bold} />)}
            </tbody>
          </table>
        </PageWrapper>

        <PageWrapper tocId="hte-divider">
          <ChasPageHeader />
          <div style={{ ...bodyFont, textAlign: 'center', paddingTop: '120px' }}>
            <h2>HOST TRAINING ESTABLISHMENT PROFILE</h2>
          </div>
        </PageWrapper>

        {NUR_ROTATIONS.map((rotation) => {
          const site = fields.rotations[rotation.id] || {}
          const siteTitle = site.hte_name ? `${rotation.title}\n${site.hte_name}` : rotation.title
          return (
            <React.Fragment key={`hte-${rotation.id}`}>
              <PaginatedTextSection
                pageHeaderComponent={ChasPageHeader}
                pageFooterComponent={ChasPageFooter}
                nextPg={nextPg}
                pageTitle={siteTitle}
                sections={[
                  { title: '', text: [site.hte_address, site.hte_profile].filter(Boolean).join('\n\n') || '[ Draft Preview Mode: Write this HTE profile in the Portfolio Builder. ]' },
                  site.hte_vision ? { title: 'Vision', text: site.hte_vision } : null,
                  site.hte_mission ? { title: 'Mission', text: site.hte_mission } : null,
                  site.hte_values ? { title: 'Core Values / Objectives', text: site.hte_values } : null,
                ].filter(Boolean)}
              />
              {renderPhotos(rotationDocType(rotation.id, 'hte_photos'), `${rotation.title} — Profile Photos`)}
            </React.Fragment>
          )
        })}

        <PageWrapper tocId="proper">
          <ChasPageHeader />
          <div style={{ ...bodyFont, textAlign: 'center', paddingTop: '120px' }}>
            <h2>INTERNSHIP PROPER</h2>
          </div>
        </PageWrapper>

        <PaginatedTextSection
          pageHeaderComponent={ChasPageHeader}
          pageFooterComponent={ChasPageFooter}
          nextPg={nextPg}
          pageTitle="Narrative & Insights of Internship Learning Experiences"
          sections={[{ title: '', text: fields.narrative || '[ Draft Preview Mode: Write your narrative in the Portfolio Builder. ]' }]}
        />
        <PaginatedTextSection
          pageHeaderComponent={ChasPageHeader}
          pageFooterComponent={ChasPageFooter}
          nextPg={nextPg}
          pageTitle="Recommendations"
          sections={[
            { title: 'a. Students', text: fields.rec_students || '[ Draft ]' },
            { title: 'b. Internship Program', text: fields.rec_program || '[ Draft ]' },
            { title: 'c. Curriculum', text: fields.rec_curriculum || '[ Draft ]' },
            { title: 'd. Host Training Establishments', text: fields.rec_hte || '[ Draft ]' },
          ]}
        />

        {NUR_ROTATIONS.map((rotation) => (
          <React.Fragment key={`plan-${rotation.id}`}>
            {renderPhotos(rotationDocType(rotation.id, 'training_plan'), `Accomplished Internship Training Plan — ${rotation.title}`)}
            {renderPhotos(rotationDocType(rotation.id, 'journal'), `Weekly Student Internship Journal — ${rotation.title}`)}
          </React.Fragment>
        ))}

        <PageWrapper tocId="appendices">
          <ChasPageHeader />
          <div style={{ ...bodyFont, textAlign: 'center', paddingTop: '120px' }}>
            <h2>APPENDICES</h2>
          </div>
        </PageWrapper>

        {GLOBAL_UPLOADS.map((item) => (
          <React.Fragment key={globalDocType(item.suffix)}>
            {renderPhotos(globalDocType(item.suffix), item.label)}
          </React.Fragment>
        ))}
        {NUR_ROTATIONS.map((rotation) => (
          <React.Fragment key={`app-${rotation.id}`}>
            {ROTATION_UPLOADS.filter((u) => !['hte_photos', 'training_plan', 'journal'].includes(u.suffix)).map((item) => (
              <React.Fragment key={rotationDocType(rotation.id, item.suffix)}>
                {renderPhotos(rotationDocType(rotation.id, item.suffix), `${item.label} — ${rotation.title}`)}
              </React.Fragment>
            ))}
          </React.Fragment>
        ))}
      </div>
    </div>
  )
}

export default NursingPortfolioPreview
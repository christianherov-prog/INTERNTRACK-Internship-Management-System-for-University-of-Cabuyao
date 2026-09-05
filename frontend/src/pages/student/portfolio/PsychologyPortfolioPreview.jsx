import React, { useState, useEffect, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import { Link } from 'react-router-dom'
import PageError from '../../../components/PageError'
import api from '../../../services/api'
import '../../../assets/css/portfolio-print.css'
import { PaginatedTextSection, PaginatedImageCollection } from '../../../components/portfolio/AutoPaginatedFlow'
import { displayLabel } from '../../../utils/displayLabel'
import {
  PSY_COURSE,
  PSY_ROTATIONS,
  emptyPsychologyFields,
  PRE_INTERNSHIP_UPLOADS,
  INTERNSHIP_UPLOADS,
  POST_INTERNSHIP_UPLOADS,
  APPENDIX_UPLOADS,
  extrasForRotation,
  docType,
  PNC_FRONT_MATTER,
} from './psychologyPortfolioStructure'

// Updated Header Component with Maroon Bar and Overlapping Logo
function PncWatermark() {
  return (
    <img
      src="/images/pnc-seal.png"
      alt=""
      className="pnc-page-watermark"
      aria-hidden="true"
    />
  )
}

function CasPageFooter() {
  return (
    <div className="cas-page-footer">
      <span>College of Arts and Sciences</span>
    </div>
  )
}

function CasPageHeader() {
  return (
    <>
      <PncWatermark />
      <div style={{ position: 'relative', zIndex: 1, width: '100%', height: '70px', marginBottom: '20px', fontFamily: 'Arial, sans-serif' }}>
        {/* Maroon Bar */}
        <div style={{
          position: 'absolute', top: '20px', left: 0, right: '45px', height: '24px',
          backgroundColor: '#680000',
          display: 'flex', alignItems: 'center', paddingLeft: '15px'
        }}>
          <span style={{ color: '#fff', fontWeight: 'bold', fontSize: '13pt', letterSpacing: '1px'}}>
            BS PSYCHOLOGY
          </span>
        </div>
        {/* Overlapping CAS Logo */}
        <img
          src="/images/cas-seal.png"
          alt="CAS Logo"
          style={{ position: 'absolute', right: '-15px', top: '-30px', width: '90px', height: '90px', zIndex: 10, objectFit: 'contain' }}
        />
      </div>
      <CasPageFooter />
    </>
  )
}

// Reusable Page Wrapper for Watermark and Footer
function PageWrapper({ children, pageNumber, tocId }) {
  return (
    <div className="a4-page portfolio-document" data-toc-id={tocId} style={{ 
      position: 'relative', 
      minHeight: '1122px', 
      paddingBottom: '120px', // Increased padding to prevent overlap
      boxSizing: 'border-box', // Ensures padding shrinks the content area
      overflow: 'hidden' 
    }}>
      {children}
      <div className="page-number">{pageNumber}</div>
    </div>
  )
}


const TocRow = ({ label, page = '', bold = false, indent = 0 }) => (
  <tr>
    <td style={{
      border: '1px solid black',
      padding: '4px 8px',
      paddingLeft: indent ? `${indent + 8}px` : '8px',
      fontWeight: bold ? 'bold' : 'normal',
      fontSize: '12pt',
      fontFamily: 'Arial, sans-serif',
      lineHeight: 1.35
    }}>
      {label}
    </td>
    <td style={{
      border: '1px solid black',
      padding: '4px 8px',
      textAlign: 'center',
      fontWeight: 'bold',
      color: '#4CAF50', // Green text for page numbers as seen in the image
      fontSize: '10pt',
      fontFamily: 'Arial, sans-serif',
      width: '60px'
    }}>
      {page}
    </td>
  </tr>
)

function coverMonthYear(internship) {
  const raw = internship?.start_date || internship?.created_at
  const d = raw ? new Date(raw) : new Date()
  if (Number.isNaN(d.getTime())) {
    return new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' })
  }
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

function PsychologyPortfolioPreview() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const printRef = useRef(null)
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: 'Psychology_Internship_Portfolio',
  })

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
  const rotations = {
    ...emptyPsychologyFields(),
    ...(p.custom_fields?.psychology?.rotations || {}),
  }
  const programName = displayLabel(user.program || profile.program, 'Bachelor of Science in Psychology')
  const collegeName = typeof user.department === 'object'
    ? (user.department?.name || 'College of Arts and Sciences')
    : (user.department || profile.department?.name || 'College of Arts and Sciences')
  const studentName = displayName(profile, user)
  const monthYear = coverMonthYear(internship)
  const fm = PNC_FRONT_MATTER

  let pageCounter = 1
  const nextPg = () => pageCounter++

  const matchesType = (item, type) => item.type === type || item.document_type === type || item.original_type === type

  const renderPhotos = (type, title) => {
    const list = photos.filter((x) => matchesType(x, type))
    return (
      <PaginatedImageCollection
        list={list}
        title={title}
        nextPg={nextPg}
        pageHeaderComponent={CasPageHeader}
        companyLogoPath={null}
        emptyMessage="[ Draft Preview Mode: This section is currently empty. Upload it in the Portfolio Builder when ready. ]"
      />
      
    )
  }

  const renderUploads = (rotationId, items) => items.map((item) => (
    <React.Fragment key={docType(rotationId, item.suffix)}>
      {renderPhotos(docType(rotationId, item.suffix), item.label)}
    </React.Fragment>
  ))

  const tocEntriesFor = (rotationId) => {
    const extras = extrasForRotation(rotationId)
    return [
      { label: 'Pre-Internship Phase', bold: true },
      { label: 'Host Training Establishment Profile', indent: 16 },
      ...PRE_INTERNSHIP_UPLOADS.map((i) => ({ label: i.label, indent: 16 })),
      { label: 'Internship Phase', bold: true },
      { label: 'Narrative and Insights of Internship Learning Experiences', indent: 16 },
      ...INTERNSHIP_UPLOADS.map((i) => ({ label: i.label, indent: 16 })),
      { label: 'Post-Internship Phase', bold: true },
      { label: 'Recommendations (Students, Internship Program, Curriculum, HTE)', indent: 16 },
      ...POST_INTERNSHIP_UPLOADS.map((i) => ({ label: i.label, indent: 16 })),
      { label: 'Appendices', bold: true },
      ...APPENDIX_UPLOADS.map((i) => ({ label: i.label, indent: 16 })),
      ...extras.map((i) => ({ label: i.label, indent: 16 })),
    ]
  }

  const TOC_TABLE_MAX_HEIGHT = 860
  const tocRowHeight = (row) => {
    const charsPerLine = row.indent ? 72 : 80
    const lines = Math.max(1, Math.ceil((row.label || '').length / charsPerLine))
    return 10 + lines * 18
  }
  const chunkTocToFillPage = (rows) => {
    const theadHeight = 38
    const pages = []
    let current = []
    let used = theadHeight
    rows.forEach((row) => {
      const height = tocRowHeight(row)
      if (current.length > 0 && used + height > TOC_TABLE_MAX_HEIGHT) {
        pages.push(current)
        current = [row]
        used = theadHeight + height
      } else {
        current.push(row)
        used += height
      }
    })
    if (current.length) pages.push(current)
    return pages.length ? pages : [[]]
  }

  const bodyFont = { fontFamily: 'Arial, sans-serif', fontSize: '11pt', lineHeight: 1.55, textAlign: 'justify' }

  return (
    <div style={{ background: '#e5e5e5', minHeight: '100vh', paddingBottom: '60px' }}>
      <div className="no-print" style={{
        position: 'sticky', top: 0, left: 0, zIndex: 1000, background: '#1a1a2e', color: '#fff',
        padding: '10px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        boxShadow: '0 2px 8px rgba(0,0,0,0.4)',
      }}>
        <div className="d-flex align-items-center gap-3">
          <Link to="/student/portfolio" style={{ color: '#ccc', textDecoration: 'none', fontSize: '14px' }}>
            <i className="fa fa-arrow-left me-2"></i>Back to Builder
          </Link>
          <span style={{ color: '#555' }}>|</span>
          <span style={{ fontWeight: 600, fontSize: '15px' }}>BS Psychology Portfolio Preview</span>
        </div>
        <button
          type="button"
          onClick={handlePrint}
          style={{
            background: '#16a34a', color: '#fff', border: 'none',
            padding: '8px 20px', borderRadius: '6px', fontWeight: 600, fontSize: '14px', cursor: 'pointer',
          }}
        >
          <i className="fa fa-print me-2"></i>Browser Print
        </button>
      </div>

      <div ref={printRef} className="portfolio-print-container">
        
        {/* COVER PAGE */}
        <div className="a4-page force-page-break portfolio-document cover-page" style={{ position: 'relative', padding: 0, overflow: 'hidden', height: '1122px' }} data-toc-id="cover">
          <img
            src="/images/cas-cover-header.jpg"
            alt="Cover Background"
            style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', objectFit: 'cover' }}
          />
          <div style={{
            position: 'absolute', top: '52%', right: '8%', width: '45%',
            display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center',
            color: '#ffffff', fontFamily: 'Arial, sans-serif'
          }}>
            <div style={{ fontSize: '26pt', fontWeight: 900, lineHeight: 1.1, textShadow: '1px 1px 4px rgba(0,0,0,0.3)' }}>INTERNSHIP</div>
            <div style={{ fontSize: '26pt', fontWeight: 900, lineHeight: 1.1, marginBottom: '24px', textShadow: '1px 1px 4px rgba(0,0,0,0.3)' }}>PORTFOLIO</div>
            
            <div style={{ fontSize: '12pt', fontWeight: 700, marginTop: '10px' }}>{studentName}</div>
            <div style={{ fontSize: '11pt', fontWeight: 700, marginTop: '4px' }}>{programName}</div>
            <div style={{ fontSize: '11pt', fontWeight: 700, marginTop: '4px' }}>{PSY_COURSE}</div>
            <div style={{ fontSize: '11pt', fontWeight: 700, marginTop: '4px' }}>{monthYear}</div>
            <div style={{ fontSize: '11pt', fontWeight: 700, marginTop: '4px' }}>{collegeName}</div>
          </div>
        </div>

        {/* MISSION & VISION */}
        <PageWrapper tocId="mission" pageNumber={nextPg()}>
          <CasPageHeader />
          <div style={bodyFont}>
            <h3 style={{ textAlign: 'center' }}>PNC MISSION</h3>
            <p>{fm.mission}</p>
            <h3 style={{ textAlign: 'center' }}>PNC VISION</h3>
            <p>{fm.vision}</p>
            <h3 style={{ textAlign: 'center' }}>PNC QUALITY POLICY</h3>
            <p>{fm.qualityPolicy}</p>
            <h3 style={{ textAlign: 'center' }}>PNC CORE VALUES</h3>
            <p>{fm.coreValuesIntro}</p>
            <ul>
              {fm.coreValues.map((v) => <li key={v}><strong>{v}</strong></li>)}
            </ul>
          </div>
        </PageWrapper>

        {/* OBJECTIVES */}
        <PageWrapper tocId="objectives" pageNumber={nextPg()}>
          <CasPageHeader />
          <div style={bodyFont}>
            <h3 style={{ textAlign: 'center' }}>PNC QUALITY OBJECTIVES</h3>
            <ol>
              {fm.qualityObjectives.map((item) => <li key={item} style={{ marginBottom: '10px' }}>{item}</li>)}
            </ol>
          </div>
        </PageWrapper>

       
        {/* TABLES OF CONTENTS */}
        {PSY_ROTATIONS.map((rotation) => {
          const allRows = [
            ...(rotation.id === 1
              ? [
                  { label: 'Mission' },
                  { label: 'Vision' },
                  { label: 'Quality Policy' },
                  { label: 'Core Values' },
                  { label: 'Quality Objectives' },
                ]
              : []),
            ...tocEntriesFor(rotation.id),
          ]
          return chunkTocToFillPage(allRows).map((chunk, pageIdx) => (
            <PageWrapper key={`toc-${rotation.id}-${pageIdx}`} tocId={pageIdx === 0 ? `toc-r${rotation.id}` : undefined} pageNumber={nextPg()}>
              <CasPageHeader />
              <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: '-10px', fontFamily: 'Arial, sans-serif' }}>
                <thead>
                  <tr>
                    <th style={{ border: '1px solid black', padding: '6px 8px', textAlign: 'left', fontSize: '10pt', fontWeight: 'bold' }}>
                      TABLE OF CONTENTS ({rotation.title}){pageIdx > 0 ? ' (cont.)' : ''}
                    </th>
                    <th style={{ border: '1px solid black', padding: '6px 8px', textAlign: 'center', fontSize: '10pt', fontWeight: 'bold', width: '60px' }}>
                      Page
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {chunk.map((row, idx) => (
                    <TocRow key={`${rotation.id}-${pageIdx}-${idx}`} label={row.label} bold={row.bold} indent={row.indent || 0} />
                  ))}
                </tbody>
              </table>
            </PageWrapper>
          ))
        })}

        {/* ROTATION CONTENTS */}
        {PSY_ROTATIONS.map((rotation) => {
          const fields = rotations[rotation.id] || rotations[String(rotation.id)] || emptyPsychologyFields()[rotation.id]
          const extras = extrasForRotation(rotation.id)
          const recText = [
            fields.rec_students && `Students: ${fields.rec_students}`,
            fields.rec_program && `Internship Program: ${fields.rec_program}`,
            fields.rec_curriculum && `Curriculum: ${fields.rec_curriculum}`,
            fields.rec_hte && `HTE: ${fields.rec_hte}`,
          ].filter(Boolean).join('\n\n')

          return (
            <React.Fragment key={`rot-${rotation.id}`}>
              {/* DIVIDER PAGE */}
              <PageWrapper tocId={`r${rotation.id}-divider`} pageNumber={nextPg()}>
                <CasPageHeader />
                <div style={{ ...bodyFont, textAlign: 'center', paddingTop: '80px' }}>
                  <h2>{rotation.title}</h2>
                  <p>Pre-Internship Phase</p>
                  {(fields.hte_name || fields.hte_address) && (
                    <p style={{ marginTop: '24px' }}>
                      <strong>{fields.hte_name}</strong><br />
                      {fields.hte_address}
                    </p>
                  )}
                </div>
              </PageWrapper>

              <PaginatedTextSection
                pageHeaderComponent={CasPageHeader}
                nextPg={nextPg}
                pageTitle="Host Training Establishment Profile"
                sections={[{ title: '', text: fields.hte_profile || '[ Draft Preview Mode: Write the HTE profile for this rotation in the Portfolio Builder. ]' }]}
              />
              {renderUploads(rotation.id, PRE_INTERNSHIP_UPLOADS)}

              <PaginatedTextSection
                pageHeaderComponent={CasPageHeader}
                nextPg={nextPg}
                pageTitle="Narrative and Insights of Internship Learning Experiences"
                sections={[{ title: '', text: fields.narrative || '[ Draft Preview Mode: Write your narrative for this rotation in the Portfolio Builder. ]' }]}
              />
              {renderUploads(rotation.id, INTERNSHIP_UPLOADS)}

              <PaginatedTextSection
                pageHeaderComponent={CasPageHeader}
                nextPg={nextPg}
                pageTitle="Recommendations (Students, Internship Program, Curriculum, HTE)"
                sections={[{ title: '', text: recText || '[ Draft Preview Mode: Write recommendations for this rotation in the Portfolio Builder. ]' }]}
              />
              {renderUploads(rotation.id, POST_INTERNSHIP_UPLOADS)}
              {renderUploads(rotation.id, APPENDIX_UPLOADS)}
              {extras.length > 0 && renderUploads(rotation.id, extras)}
            </React.Fragment>
          )
        })}
      </div>
    </div>
  )
}

export default PsychologyPortfolioPreview
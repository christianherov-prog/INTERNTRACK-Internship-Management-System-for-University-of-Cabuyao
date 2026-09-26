import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useReactToPrint } from 'react-to-print'
import { Link } from 'react-router-dom'
import PageError from '../../../components/PageError'
import InternTrackLoader from '../../../components/InternTrackLoader'
import api from '../../../services/api'
import { AuthenticatedFileImage } from '../../../components/AuthenticatedFile'
import '../../../assets/css/portfolio-print.css'
import { PaginatedImageCollection } from '../../../components/portfolio/AutoPaginatedFlow'
import PaginatedRichText from '../../../components/portfolio/PaginatedRichText'
import PdfPages from '../../../components/portfolio/PdfPages'
import WeeklyInternshipJournal from '../../../components/portfolio/WeeklyInternshipJournal'
import DailyTimeRecord from '../../../components/portfolio/DailyTimeRecord'
import { PrintFO03, PrintFO22, PrintFO23, PrintFO24 } from '../../../components/portfolio/EvaluationsPreview'
import { displayLabel } from '../../../utils/displayLabel'
import { useCachedPage } from '../../../hooks/useCachedPage'
import { previewScaleFor } from '../../../hooks/usePortfolioPreviewScale'
import { plainTextToHtml } from '../../../utils/sanitizeHtml'
import {
  APPENDICES,
  COMPANY_EXTRA_FIELDS,
  HTE_PHOTO_TYPE,
  TRAINING_PLAN_ALIASES,
  appendixState,
  authoritativeAttendance,
  buildToc,
  findDocs,
  isStudentUpload,
  pickEvaluation,
  trainingPlanTitle,
} from './cbaaPortfolioStructure'
import '../../../styles/cbaa-portfolio.css'

const EVAL_COMPONENTS = { 'FO-22': PrintFO22, 'FO-23': PrintFO23, 'FO-24': PrintFO24, 'FO-03': PrintFO03 }
const TOC_ROWS_PER_PAGE = 46
const ZOOM_MIN = 0.5
const ZOOM_MAX = 2

const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const isPdf = (doc) => String(doc?.mime_type || '').includes('pdf') || /\.pdf$/i.test(String(doc?.file_path || doc?.file_name || ''))
const isFileNameLabel = (label) => /\.(png|jpe?g|webp|gif|pdf)$/i.test(String(label || ''))

// --- Reusable Header Component (same template as the other colleges) ---
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
    department: { margin: '5px 0 2px 0', fontSize: '12pt', fontWeight: 'bold', textAlign: 'center' },
    address: { fontSize: '9pt' },
    logoBox: {
      width: '78px', height: '78px', border: '1px dashed #444', display: 'flex',
      alignItems: 'center', justifyContent: 'center', fontSize: '8pt', textAlign: 'center', color: '#444'
    }
  }
  return (
    <div style={styles.headerContainer}>
      <div style={styles.sideCol}>
        <img src="/images/pnc-logo.png" alt="UC Logo" style={{ width: '78px', height: '78px', objectFit: 'contain' }} />
      </div>
      <div style={styles.centerCol}>
        <p style={styles.republic}>Republic of the Philippines</p>
        <h1 style={styles.university}>University of Cabuyao</h1>
        <p style={styles.pamantasan}>(Pamantasan ng Cabuyao)</p>
        <h2 style={styles.department}>COLLEGE OF BUSINESS, ACCOUNTANCY AND ADMINISTRATION</h2>
        <p style={styles.address}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025</p>
      </div>
      <div style={styles.sideCol}>
        <img src="/images/CBAA.png" alt="UC Logo" style={{ width: '78px', height: '78px', objectFit: 'contain' }} />
         
      
      </div>
    </div>
  )
}

// --- FULL PAGE TOC Row Component (same template as the other colleges) ---
const TocRow = ({ label, page, level = 0, bold = false, style }) => (
  <div style={{
    display: 'flex',
    justifyContent: 'space-between',
    gap: '12px',
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
)

/** Chapter divider page in the template style ("CHAPTER I / INTRODUCTION"). */
function ChapterPage({ tocId, companyLogoPath, numeral, title }) {
  return (
    <div data-toc-id={tocId} className="a4-page page-break portfolio-document d-flex flex-column justify-content-between position-relative">
      <PageHeader companyLogoPath={companyLogoPath} />
      <div style={{ textAlign: 'center', margin: 'auto 0' }}>
        {numeral && <h1 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: '36pt', marginBottom: '30px' }}>{numeral}</h1>}
        <h2 style={{ fontFamily: 'Arial, sans-serif', fontWeight: 'bold', fontSize: numeral ? '28pt' : '48pt' }}>{title}</h2>
      </div>
      <div className="page-number"></div>
    </div>
  )
}

/** Template-style "empty" sheet with an accurate status badge. */
function EmptySheet({ tocId, companyLogoPath, title, status, message }) {
  const badge = status === 'awaiting' ? 'Awaiting Approval' : status === 'unavailable' ? 'Not Yet Available' : 'Not Uploaded Yet'
  return (
    <div className="a4-page page-break portfolio-document position-relative" data-toc-id={tocId}>
      <PageHeader companyLogoPath={companyLogoPath} />
      <h5 style={{ fontWeight: 'bold', marginTop: '16px', fontSize: '13pt', lineHeight: 1.3, textAlign: 'center' }}>{title}</h5>
      <div style={{ textAlign: 'center', padding: '25px 20px', border: '2px dashed #bbb', background: '#f8f9fa', borderRadius: '12px', width: '85%', margin: '30px auto 0' }}>
        <i className={`fa ${status === 'awaiting' ? 'fa-hourglass-half' : 'fa-file-circle-exclamation'} fa-3x text-warning mb-2`}></i>
        <h6 className="fw-bold text-dark" style={{ fontSize: '11pt', lineHeight: 1.4, margin: '8px auto', maxWidth: '90%' }}>{title}</h6>
        <div className="badge bg-warning text-dark mt-1 mb-2 px-3 py-1" style={{ fontSize: '9.5pt', fontWeight: 600 }}>{badge}</div>
        {message && <p className="small text-muted mb-0" style={{ maxWidth: '450px', margin: '6px auto 0', fontSize: '9pt', lineHeight: 1.4 }}>{message}</p>}
      </div>
      <div className="page-number"></div>
    </div>
  )
}

/** Documents as template sheets: PDFs page-by-page, images one per sheet. */
function DocumentSheets({ docs, title, tocId, companyLogoPath }) {
  const sheet = (key, withToc, heading, content, caption) => (
    <div key={key} className="a4-page page-break portfolio-document position-relative" data-toc-id={withToc ? tocId : undefined}>
      <PageHeader companyLogoPath={companyLogoPath} />
      {heading && <h5 style={{ fontWeight: 'bold', marginTop: '16px', fontSize: '13pt', lineHeight: 1.3, textAlign: 'center' }}>{heading}</h5>}
      <div style={{ display: 'flex', justifyContent: 'center', marginTop: heading ? '16px' : '8px' }}>{content}</div>
      {caption && <p style={{ fontWeight: 'bold', marginTop: '12px', textIndent: 0, fontSize: '11pt', textAlign: 'center' }}>{caption}</p>}
      <div className="page-number"></div>
    </div>
  )
  return docs.map((doc, di) => {
    const caption = isStudentUpload(doc) && doc.label && !isFileNameLabel(doc.label) ? doc.label : ''
    const heading = di === 0 ? title : `${title} (cont.)`
    if (isPdf(doc)) {
      return (
        <PdfPages
          key={doc.id}
          path={doc.file_path}
          title={title}
          caption={caption}
          renderPage={(i, content) => sheet(`${doc.id}-${i}`, di === 0 && i === 0, i === 0 ? heading : null, content, i === 0 ? caption : '')}
        />
      )
    }
    return sheet(doc.id, di === 0, heading, (
      <AuthenticatedFileImage path={doc.file_path} alt={caption || title} style={{ maxWidth: '98%', maxHeight: '215mm', objectFit: 'contain', display: 'block' }} />
    ), caption)
  })
}

function CBAAPortfolioPreview({ preloadedData = null, backTo = '/student/portfolio', backLabel = 'Back to Builder', modeLabel = 'Draft Preview Mode' } = {}) {
  const { loading: cacheLoading, seed, run } = useCachedPage('student:portfolio')
  const loading = preloadedData ? false : cacheLoading
  const [data, setData] = useState(preloadedData ?? seed ?? null)
  const [error, setError] = useState(null)
  const [toc, setToc] = useState({})
  const [pageCount, setPageCount] = useState(0)
  const [currentPage, setCurrentPage] = useState(1)
  const [zoom, setZoom] = useState(1)
  const [fit, setFit] = useState(() => previewScaleFor(window.innerWidth))
  const printRef = useRef(null)
  const docRef = useRef(null)

  const handlePrint = useReactToPrint({ contentRef: printRef, documentTitle: 'CBAA_Portfolio_Preview' })

  const load = useCallback(() => {
    setError(null)
    if (preloadedData) { setData(preloadedData); return }
    run(() => api.get('/student/portfolio').then((res) => res.data))
      .then((next) => { if (next) setData(next) })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load portfolio.'))
  }, [preloadedData, run])

  useEffect(() => { load() }, [load])

  // ---------- Zoom (screen only; printing keeps true A4) ----------
  useEffect(() => {
    const onResize = () => setFit(previewScaleFor(window.innerWidth))
    window.addEventListener('resize', onResize)
    window.addEventListener('orientationchange', onResize)
    return () => {
      window.removeEventListener('resize', onResize)
      window.removeEventListener('orientationchange', onResize)
    }
  }, [])

  useEffect(() => {
    const root = document.documentElement
    root.style.setProperty('--portfolio-preview-scale', String(fit * zoom))
    return () => root.style.removeProperty('--portfolio-preview-scale')
  }, [fit, zoom])

  // ---------- Page discovery: TOC page numbers, page count, current page ----------
  const topLevelPages = useCallback(() => {
    const root = docRef.current
    if (!root) return []
    return Array.from(root.querySelectorAll('.a4-page')).filter((el) => !el.parentElement?.closest('.a4-page'))
  }, [])

  useEffect(() => {
    const root = docRef.current
    if (!root) return undefined
    let timer = null
    const compute = () => {
      const pages = topLevelPages()
      setPageCount(pages.length)
      const next = {}
      root.querySelectorAll('[data-toc-id]').forEach((marker) => {
        if (marker.closest('.cbaa-measure')) return
        const id = marker.getAttribute('data-toc-id')
        if (!id || next[id]) return
        let page = pages.includes(marker) ? marker : pages.find((p) => p.contains(marker))
        if (!page) page = pages.find((p) => marker.compareDocumentPosition(p) & Node.DOCUMENT_POSITION_FOLLOWING)
        if (page) next[id] = pages.indexOf(page) + 1
      })
      setToc((prev) => (JSON.stringify(prev) === JSON.stringify(next) ? prev : next))
    }
    const schedule = () => {
      clearTimeout(timer)
      timer = setTimeout(compute, 150)
    }
    schedule()
    const observer = new MutationObserver(schedule)
    observer.observe(root, { childList: true, subtree: true })
    return () => {
      observer.disconnect()
      clearTimeout(timer)
    }
  }, [data, topLevelPages])

  useEffect(() => {
    let frame = null
    const onScroll = () => {
      cancelAnimationFrame(frame)
      frame = requestAnimationFrame(() => {
        const line = window.innerHeight * 0.35
        let idx = 0
        topLevelPages().forEach((p, i) => { if (p.getBoundingClientRect().top <= line) idx = i })
        setCurrentPage(idx + 1)
      })
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()
    return () => {
      window.removeEventListener('scroll', onScroll)
      cancelAnimationFrame(frame)
    }
  }, [topLevelPages, pageCount])

  const goTo = (n) => {
    const pages = topLevelPages()
    pages[Math.max(0, Math.min(pages.length - 1, n - 1))]?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  // ---------- Data ----------
  const view = useMemo(() => {
    if (!data?.internship) return null
    const i = data.internship
    const p = i.portfolio || {}
    const idn = data.identity || {}
    const u = data.user
    const sp = u?.student_profile || u?.studentProfile
    const photos = p.photos || []
    const hte = i.company || {}
    const extrasSaved = p.custom_fields?.cbaa?.company || {}
    const programTitle = displayLabel(idn.program || sp?.course_name || sp?.program || i.program)
    const programCode = sp?.program?.code || u?.program_code || ''
    const tpTitle = trainingPlanTitle(programCode, programTitle)
    const journals = [...(i.journals || [])].sort((a, b) => (a.week_number || 0) - (b.week_number || 0) || String(a.date).localeCompare(String(b.date)))
    const semesterBit = idn.semester
      ? (idn.semester === '2nd' ? '2nd SEMESTER' : (idn.semester === '1st' ? '1st SEMESTER' : `${idn.semester} SEMESTER`))
      : ''
    const hoursLabel = idn.target_hours ? `${idn.target_hours} hours` : ''

    return {
      i,
      p,
      idn,
      u,
      sections: Object.fromEntries(Object.entries(p.sections || {}).map(([k, v]) => [k, v?.content || ''])),
      extras: {
        industry: extrasSaved.industry || hte.industry || '',
        year_established: extrasSaved.year_established || '',
        contact_person: extrasSaved.contact_person || hte.contact_person || idn.supervisor_name || '',
        contact_number: extrasSaved.contact_number || hte.contact_number || '',
        email: extrasSaved.email || hte.contact_email || '',
        website: extrasSaved.website || '',
      },
      studentName: idn.student_name || (sp ? `${sp.last_name?.toUpperCase()}, ${sp.first_name?.toUpperCase()}` : ''),
      section: idn.section || sp?.section || '',
      programTitle,
      practicumCode: hoursLabel ? `${programTitle} Practicum (${hoursLabel})` : `${programTitle} Practicum`,
      ayLabel: idn.academic_year ? `A.Y. ${idn.academic_year}${semesterBit ? ` / ${semesterBit}` : ''}` : (sp?.school_year ? `A.Y. ${sp.school_year}` : ''),
      facultyName: idn.faculty_name || '',
      coordinatorName: idn.coordinator_name || '',
      companyName: idn.company_name || p.company_name || hte.company_name || '',
      companyAddress: idn.company_address || p.company_address || hte.address || '',
      companyLogoPath: p.company_logo_path || idn.company_logo_path || '',
      journals,
      attendance: authoritativeAttendance(i.attendance || i.attendance_logs || []),
      htePhotos: findDocs(photos, [HTE_PHOTO_TYPE]),
      trainingPlanDocs: findDocs(photos, TRAINING_PLAN_ALIASES),
      trainingPlanTitle: tpTitle,
      appendices: APPENDICES.map((entry) => ({ ...entry, ...appendixState(entry, { photos, recordStatus: i.record_status, evaluations: i.evaluations }) })),
      tocRows: buildToc({ trainingPlanTitle: tpTitle, journals }),
    }
  }, [data])

  if (loading && !data) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <InternTrackLoader />
        <div className="small">Loading preview…</div>
      </div>
    )
  }

  if (error || !view) {
    return (
      <div style={{ background: '#e5e5e5', minHeight: '100vh', padding: '24px' }}>
        <PageError message={error || 'No internship record found.'} onRetry={error ? load : null} />
        <div className="text-center mt-3"><Link to={backTo} className="text-muted">{backLabel}</Link></div>
      </div>
    )
  }

  const { i, p, idn, sections, extras, companyLogoPath } = view
  const header = <PageHeader companyLogoPath={companyLogoPath} />
  const tocLabel = (id) => view.tocRows.find((r) => r.id === id)?.label || ''

  const companyHtml = [
    [['Company Name', view.companyName], ['Address', view.companyAddress], ...COMPANY_EXTRA_FIELDS.map((f) => [f.label, extras[f.key]])]
      .filter(([, v]) => String(v || '').trim())
      .map(([k, v]) => `<p class="cbaa-kv"><strong>${esc(k)}:</strong> ${esc(v)}</p>`)
      .join(''),
    sections.company_profile ? `<h4>History / Background</h4>${sections.company_profile}` : '',
    p.company_vision ? `<h4>Vision</h4>${plainTextToHtml(p.company_vision)}` : '',
    p.company_mission ? `<h4>Mission</h4>${plainTextToHtml(p.company_mission)}` : '',
  ].join('')

  const tocPages = []
  for (let n = 0; n < view.tocRows.length; n += TOC_ROWS_PER_PAGE) tocPages.push(view.tocRows.slice(n, n + TOC_ROWS_PER_PAGE))

  const htePhotoList = view.htePhotos.map((ph, n) => ({
    ...ph,
    type: 'ojt_photo',
    label: `Figure ${n + 1}.${ph.label && !isFileNameLabel(ph.label) ? ` ${ph.label}` : ''}`,
  }))

  return (
    <div ref={printRef} className="cbaa-preview" style={{ background: '#e5e5e5', minHeight: '100vh', paddingBottom: '60px' }}>
      <div className="no-print portfolio-preview-toolbar" style={{
        position: 'sticky', top: 0, left: 0, zIndex: 1000,
        background: '#1a1a2e', color: '#fff',
        padding: '10px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        boxShadow: '0 2px 8px rgba(0,0,0,0.4)'
      }}>
        <div className="d-flex align-items-center gap-3">
          <Link to={backTo} style={{ color: '#ccc', textDecoration: 'none', fontSize: '14px' }}>
            <i className="fa fa-arrow-left me-2"></i>{backLabel}
          </Link>
          <span className="portfolio-preview-sep" style={{ color: '#555' }}>|</span>
          <span style={{ fontWeight: 600, fontSize: '15px' }}>Portfolio Preview</span>
          <span className="badge bg-info text-dark ms-2" style={{ fontSize: '12px', fontWeight: '500' }}><i className="fa fa-info-circle me-1"></i>{modeLabel}</span>
        </div>
        <div className="d-flex align-items-center gap-1" role="group" aria-label="Page navigation">
          <button type="button" className="cbaa-tb-btn" onClick={() => goTo(currentPage - 1)} disabled={currentPage <= 1} aria-label="Previous page"><i className="fa fa-chevron-left"></i></button>
          <span className="cbaa-tb-page" aria-live="polite">Page {currentPage} / {pageCount || '…'}</span>
          <button type="button" className="cbaa-tb-btn" onClick={() => goTo(currentPage + 1)} disabled={currentPage >= pageCount} aria-label="Next page"><i className="fa fa-chevron-right"></i></button>
          <span className="portfolio-preview-sep mx-1" style={{ color: '#555' }}>|</span>
          <button type="button" className="cbaa-tb-btn" onClick={() => setZoom((z) => Math.max(ZOOM_MIN, +(z - 0.1).toFixed(2)))} disabled={zoom <= ZOOM_MIN} aria-label="Zoom out"><i className="fa fa-magnifying-glass-minus"></i></button>
          <button type="button" className="cbaa-tb-btn cbaa-tb-zoom" onClick={() => setZoom(1)} title="Reset zoom">{Math.round(zoom * 100)}%</button>
          <button type="button" className="cbaa-tb-btn" onClick={() => setZoom((z) => Math.min(ZOOM_MAX, +(z + 0.1).toFixed(2)))} disabled={zoom >= ZOOM_MAX} aria-label="Zoom in"><i className="fa fa-magnifying-glass-plus"></i></button>
        </div>
        <div className="d-flex gap-2">
          <button
            onClick={handlePrint}
            style={{ background: '#16a34a', color: '#fff', border: 'none', padding: '8px 20px', borderRadius: '6px', fontWeight: 600, fontSize: '14px', cursor: 'pointer' }}>
            <i className="fa fa-print me-2"></i>Browser Print
          </button>
        </div>
      </div>

      <div ref={docRef}>
        {/* PAGE 1: COVER */}
        <div className="a4-page force-page-break cover-page-banner"
          style={{ backgroundImage: 'url(/images/cover-bg.jpg)', backgroundSize: 'cover', backgroundPosition: 'center', padding: 0 }}>
          <div style={{ position: 'absolute', top: '51.5%', left: '53%', textAlign: 'left', maxWidth: '45%' }}>
            <div style={{ fontSize: '26pt', fontWeight: 'bold', color: '#ffffff', lineHeight: 1.1, letterSpacing: '-0.5px' }}>Internship Portfolio</div>
            <div style={{ fontSize: '11pt', fontWeight: 'bold', color: '#ffffff', marginTop: '4px', textTransform: 'uppercase' }}>{view.ayLabel}</div>
          </div>
          <div className="page-number"></div>
        </div>

        

        {/* PAGE 3: NARRATIVE HEAD */}
        <div className="a4-page page-break portfolio-document position-relative" style={{ fontFamily: 'Arial, sans-serif', fontSize: '11pt' }}>
          {header}
          <div style={{ flex: 1, width: '93%', textAlign: 'center', paddingBottom: '40px' }}>
            <div style={{ marginBottom: '42px' }}>
              <p style={{ textAlign: 'center' }}>A Narrative Report on the On-The-Job Training</p>
              <p style={{ margin: 0, textAlign: 'center' }}>undertaken at <strong style={{ fontStyle: 'italic' }}>{view.companyName || '(Name of HTE)'}</strong></p>
              <p style={{ margin: 0, textAlign: 'center' }}>located at <strong style={{ fontStyle: 'italic' }}>{view.companyAddress || '(Address of HTE)'}</strong></p>
            </div>
            <div style={{ marginBottom: '42px' }}>
              <p style={{ textAlign: 'center' }}>In partial fulfillment of the requirements for the course</p>
              <p style={{ fontWeight: 'bold', margin: '10px 0', textAlign: 'center' }}>{view.practicumCode}</p>
              <p style={{ marginTop: '20px', textAlign: 'center' }}>For the Degree of</p>
              <p style={{ fontWeight: 'bold', textAlign: 'center' }}>{view.programTitle}</p>
            </div>
            <div style={{ marginBottom: '42px' }}>
              <p style={{ textAlign: 'center' }}>Presented to the faculty of Business, Accountancy and Administration</p>
              <p style={{ fontWeight: 'bold', textAlign: 'center' }}>COLLEGE OF BUSINESS, ACCOUNTANCY AND ADMINISTRATION</p>
              <p style={{ textAlign: 'center' }}>UNIVERSITY OF CABUYAO (PnC)</p>
              <p style={{ textAlign: 'center' }}>Katapatan Mutual Homes Subdivision</p>
              <p style={{ textAlign: 'center' }}>Brgy. Banay-banay, City of Cabuyao, Laguna 4025</p>
            </div>
            <div style={{ marginBottom: '42px' }}>
              <p style={{ textAlign: 'center' }}>Submitted by:</p>
              <p style={{ fontWeight: 'bold', textAlign: 'center' }}>{view.studentName}</p>
              <p style={{ textAlign: 'center' }}>{view.section}</p>
            </div>
            <div>
              <p style={{ textAlign: 'center' }}>Submitted to:</p>
              <p style={{ fontWeight: 'bold', fontStyle: 'italic', marginTop: '16px', textAlign: 'center' }}>{view.facultyName || '____________________________'}</p>
              <p style={{ textAlign: 'center' }}>Internship Instructor</p><br />
              {view.coordinatorName ? (
                <>
                  <p style={{ fontWeight: 'bold', textAlign: 'center' }}>{view.coordinatorName}</p>
                  <p style={{ textAlign: 'center' }}>Internship Coordinator</p><br />
                </>
              ) : null}
              <p style={{ fontWeight: 'bold', textAlign: 'center' }}>{new Date().toLocaleDateString('en-PH', { month: 'long', year: 'numeric', timeZone: 'Asia/Manila' })}</p>
            </div>
          </div>
          <div className="page-number"></div>
        </div>

        {/* I. BIOGRAPHICAL SKETCH */}
        <PaginatedRichText header={header} sections={[{ tocId: 'bio_sketch', title: tocLabel('bio_sketch'), level: 'h3', html: sections.bio_sketch, emptyText: 'Biographical sketch not yet written.' }]} />

        {/* II. ACKNOWLEDGMENT */}
        <PaginatedRichText header={header} sections={[{ tocId: 'acknowledgment', title: tocLabel('acknowledgment'), level: 'h3', html: sections.acknowledgment, emptyText: 'Acknowledgment not yet written.' }]} />

        {/* III. TABLE OF CONTENTS — same rows as the Builder's Table of Contents tab */}
        {tocPages.map((rows, pi) => (
          <div key={`toc-${pi}`} className="a4-page page-break portfolio-document position-relative d-flex flex-column" data-toc-id={pi === 0 ? 'toc' : undefined}>
            {header}
            <h3 style={{ textAlign: 'center', fontWeight: 'bold', marginBottom: '15px', fontSize: '12pt', fontFamily: '"Arial", sans-serif' }}>
              {pi === 0 ? 'Table of Contents' : 'Table of Contents (cont.)'}
            </h3>
            <div style={{ padding: '0 10px', flex: 1, display: 'flex', flexDirection: 'column', justifyContent: rows.length > 24 ? 'space-between' : 'flex-start', gap: rows.length > 24 ? 0 : '8px', paddingBottom: '20px' }}>
              {rows.map((r) => (
                <TocRow key={r.id} label={r.label} page={toc[r.id]} level={r.level || 0} bold={r.bold} style={r.bold ? { marginTop: '8px' } : undefined} />
              ))}
            </div>
            <div className="page-number"></div>
          </div>
        ))}

        {/* IV. HOST TRAINING ESTABLISHMENT */}
        <ChapterPage tocId="hte" companyLogoPath={companyLogoPath} numeral="IV" title="HOST TRAINING ESTABLISHMENT" />
        <PaginatedRichText header={header} sections={[{ tocId: 'company_profile', title: tocLabel('company_profile'), level: 'h4', html: companyHtml, emptyText: 'Company profile not yet provided.' }]} />
        <PaginatedImageCollection
          list={htePhotoList}
          title={tocLabel('photos')}
          tocId="photos"
          companyLogoPath={companyLogoPath}
          nextPg={() => ''}
          pageHeaderComponent={PageHeader}
          emptyMessage="[ Draft Preview Mode: Add photos with captions in the Portfolio Builder to populate this section. ]"
        />

        {/* V. INTERNSHIP PROPER */}
        <ChapterPage tocId="proper" companyLogoPath={companyLogoPath} numeral="V" title="INTERNSHIP PROPER" />
        <PaginatedRichText
          header={header}
          sections={[
            { tocId: 'narrative', title: tocLabel('narrative'), level: 'h4', html: sections.narrative, emptyText: 'Narrative insights not yet written.' },
            { tocId: 'recommendations', title: tocLabel('recommendations'), level: 'h4', html: '', emptyText: null },
            { tocId: 'rec_students', title: tocLabel('rec_students'), level: 'h5', html: sections.rec_students, emptyText: 'Not yet written.' },
            { tocId: 'rec_program', title: tocLabel('rec_program'), level: 'h5', html: sections.rec_program, emptyText: 'Not yet written.' },
            { tocId: 'rec_hte', title: tocLabel('rec_hte'), level: 'h5', html: sections.rec_hte, emptyText: 'Not yet written.' },
          ]}
        />

        {view.trainingPlanDocs.length > 0
          ? <DocumentSheets docs={view.trainingPlanDocs} title={tocLabel('training_plan')} tocId="training_plan" companyLogoPath={companyLogoPath} />
          : <EmptySheet tocId="training_plan" companyLogoPath={companyLogoPath} title={tocLabel('training_plan')} status="missing" message="[ Draft Preview Mode: Upload or submit your accomplished training plan to populate this section. ]" />}

        <div data-toc-id="dtr" className="cbaa-anchor" aria-hidden="true"></div>
        <DailyTimeRecord
          studentName={view.studentName}
          program={view.programTitle}
          companyName={view.companyName}
          supervisorName={idn.supervisor_name || ''}
          companyLogoPath={companyLogoPath}
          studentSignaturePath={idn.student_signature_path || ''}
          supervisorSignaturePath={idn.supervisor_signature_path || ''}
          logs={view.attendance}
          nextPg={() => ''}
        />

        <div data-toc-id="journal" className="cbaa-anchor" aria-hidden="true"></div>
        {view.journals.length === 0 ? (
          <WeeklyInternshipJournal
            studentName={view.studentName}
            program={view.programTitle}
            studentSignaturePath={idn.student_signature_path || ''}
            companyLogoPath={companyLogoPath}
            nextPg={() => ''}
          />
        ) : view.journals.map((j) => (
          <div key={j.id} style={{ display: 'contents' }}>
            <div data-toc-id={`week-${j.id}`} className="cbaa-anchor" aria-hidden="true"></div>
            <WeeklyInternshipJournal
              studentName={view.studentName}
              program={view.programTitle}
              weekNumber={j.week_number || j.week}
              date={j.date}
              endDate={j.end_date}
              accomplishment={j.activities_summary || j.accomplishment}
              difficulties={j.challenges || j.difficulties}
              insights={j.learnings || j.insights}
              studentSignaturePath={idn.student_signature_path || ''}
              companyLogoPath={companyLogoPath}
              nextPg={() => ''}
            />
          </div>
        ))}

        {/* VI. APPENDICES */}
        <ChapterPage tocId="appendices" companyLogoPath={companyLogoPath} title="APPENDICES" />
        {view.appendices.map((a) => {
          const title = tocLabel(`app-${a.key}`)
          const tocId = `app-${a.key}`
          if (a.evaluation) {
            // Same as the other colleges: the official form is always shown, blank until submitted.
            const Eval = EVAL_COMPONENTS[a.evaluation]
            return <Eval key={a.key} evalData={pickEvaluation(i.evaluations, a.evaluation)} tocId={tocId} internship={i} user={view.u} identity={idn} />
          }
          if (a.docs.length > 0) {
            return <DocumentSheets key={a.key} docs={a.docs} title={title} tocId={tocId} companyLogoPath={companyLogoPath} />
          }
          return (
            <EmptySheet
              key={a.key}
              tocId={tocId}
              companyLogoPath={companyLogoPath}
              title={title}
              status={a.status}
              message={a.status === 'awaiting'
                ? '[ Submitted in Requirements and awaiting approval. It will appear here automatically once approved. ]'
                : a.status === 'unavailable'
                  ? `[ ${a.unavailableHint || 'Not yet available in InternTrack.'} ]`
                  : '[ Draft Preview Mode: You can upload this requirement in the Portfolio Builder when ready. ]'}
            />
          )
        })}
      </div>
    </div>
  )
}

export default CBAAPortfolioPreview

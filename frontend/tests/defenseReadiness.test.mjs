// REPORT-UI-01..06, ADMIN-UI-01..02, DATE-UI-01..04 and report-data integrity:
// shared report previews, status chips, print sizing, green tabs, date
// formatting and the MISD dashboard layout contract.
import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { importBundle, FRONTEND_ROOT } from './helpers/bundle.mjs'

const SRC = join(FRONTEND_ROOT, 'src')
const read = (rel) => readFileSync(join(SRC, rel), 'utf8').replace(/\r\n/g, '\n')
const STYLES = read('styles/styles.css')
const MODAL_CSS = read('styles/modal-system.css')

function rule(css, selector) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const m = css.match(new RegExp(`(?:^|[},])[ \\t]*${escaped}\\s*\\{([^}]*)\\}`, 'm'))
  assert.ok(m, `missing rule ${selector}`)
  return m[1].replace(/\s+/g, ' ')
}

/** WCAG relative-luminance contrast ratio of two #rrggbb colors. */
function contrast(a, b) {
  const lum = (hex) => {
    const [r, g, bl] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
      .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
    return 0.2126 * r + 0.7152 * g + 0.0722 * bl
  }
  const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x)
  return (hi + 0.05) / (lo + 0.05)
}

let ui
before(async () => {
  ui = await importBundle(`
    export { default as ReportExportModal } from './src/components/modals/ReportExportModal.jsx'
    export { default as ComplianceRequirementsStatus, formatRequirementsStatusCsv, parseRequirementsStatusCsv, normalizeRequirementStatuses } from './src/components/ComplianceRequirementsStatus.jsx'
    export { default as ComplianceApprovedProgress } from './src/components/ComplianceApprovedProgress.jsx'
    export { StudentSummaryTable, ComplianceTable } from './src/components/reports/ReportTables.jsx'
    export { getStatusVariant, formatStatusLabel } from './src/utils/statusVariant.js'
    export { REPORT_PRINT_PAGE_STYLE } from './src/utils/reportPrint.js'
    export * as time from './src/utils/manilaTime.js'
    export { renderToStaticMarkup } from 'react-dom/server'
    export { createElement as h } from 'react'
  `)
})

const render = (el) => ui.renderToStaticMarkup(el)

const COMPLIANCE_ROW = {
  student_name: 'Terrence John Manlapaz',
  program: 'Bachelor of Science in Computer Science',
  compliance_pct: 46,
  approved_docs: 6,
  required_docs: 13,
  requirements: [
    { name: 'Application Letter Urgent', status: 'approved', status_label: 'Approved' },
    { name: 'Daily Time Record', status: 'approved', status_label: 'Completed' },
    { name: 'Training Plan', status: 'pending', status_label: 'Pending Review' },
    { name: 'Consent Form', status: 'rejected', status_label: 'Rejected' },
    { name: 'Performance Evaluation', status: 'missing', status_label: 'Missing' },
    { name: 'Certificate of Completion', status: 'pending', status_label: 'Pending Review' },
  ],
}

test('REPORT-UI-01 CSV preview header is dark with high-contrast light text', () => {
  const html = render(ui.h(ui.ReportExportModal, {
    preview: { title: 'Student Summary', filename: 'x', rows: [{ Student: 'A', 'Hours Rendered': 10 }] },
    onClose() {},
  }))
  assert.match(html, /<thead class="it-report-preview__head">/)
  assert.doesNotMatch(html, /table-dark/, 'no reliance on Bootstrap table-dark')
  const head = rule(MODAL_CSS, '.it-modal__table-wrap .it-report-preview__head th')
  assert.match(head, /background: var\(--green-dark, #0a5c2e\)/)
  assert.match(head, /color: #f8fafc/)
  assert.ok(contrast('#f8fafc', '#0a5c2e') >= 7, 'header text meets WCAG AAA contrast')
})

test('REPORT-UI-02 requirement statuses render as labelled green / amber / red chips', () => {
  const html = render(ui.h(ui.ComplianceRequirementsStatus, { row: COMPLIANCE_ROW }))
  const chip = (name) => html.match(new RegExp(`it-status-chip--(\\w+)[^"]*" title="${name}: ([^"]+)"`))
  assert.deepEqual(chip('Application Letter Urgent').slice(1), ['success', 'Approved'])
  assert.deepEqual(chip('Daily Time Record').slice(1), ['success', 'Completed'])
  assert.deepEqual(chip('Training Plan').slice(1), ['warning', 'Pending Review'])
  assert.deepEqual(chip('Consent Form').slice(1), ['danger', 'Rejected'])
  assert.deepEqual(chip('Performance Evaluation').slice(1), ['danger', 'Missing'])
  // Readable without color: every chip prints its status word.
  for (const word of ['Approved', 'Completed', 'Pending Review', 'Rejected', 'Missing']) {
    assert.match(html, new RegExp(`it-status-chip__status">${word}<`))
  }
  for (const [status, variant] of Object.entries({
    approved: 'success', completed: 'success', satisfied: 'success',
    pending: 'warning', 'Pending Review': 'warning', submitted: 'warning', awaiting_review: 'warning',
    missing: 'danger', rejected: 'danger', returned: 'danger', incomplete: 'danger',
    not_applicable: 'neutral', 'Not Yet Required': 'neutral',
  })) {
    assert.equal(ui.getStatusVariant(status), variant, status)
  }
  assert.match(rule(STYLES, '.it-status-chip--success'), /color: #166534/)
  assert.match(rule(STYLES, '.it-status-chip--warning'), /color: #92400e/)
  assert.match(rule(STYLES, '.it-status-chip--danger'), /color: #b91c1c/)
})

test('REPORT-UI-02b CSV stays plain text while the preview shows chips', () => {
  const csv = ui.formatRequirementsStatusCsv(COMPLIANCE_ROW)
  assert.equal(csv, 'Application Letter Urgent: Approved; Daily Time Record: Completed; Training Plan: Pending Review; Consent Form: Rejected; Performance Evaluation: Missing; Certificate of Completion: Pending Review')
  assert.doesNotMatch(csv, /[<>]/)
  assert.deepEqual(ui.parseRequirementsStatusCsv(csv).map((i) => `${i.name}: ${i.status_label}`).join('; '), csv)

  const rows = [{ Student: 'T', 'Requirements Status': csv, Status: 'pending_placement' }]
  const snapshot = JSON.stringify(rows)
  const html = render(ui.h(ui.ReportExportModal, {
    preview: { filename: 'c', rows, requirementColumns: ['Requirements Status'], statusColumns: ['Status'] },
    onClose() {},
  }))
  assert.match(html, /it-status-chip-list/)
  assert.match(html, /title="Consent Form: Rejected"/)
  // Status chips show the exact exported value.
  assert.match(html, /it-status-chip__status">pending_placement</)
  assert.equal(JSON.stringify(rows), snapshot, 'preview never mutates the CSV rows')
})

test('REPORT-UI-03 previews and printed tables stay readable and responsive', () => {
  assert.match(rule(STYLES, '.it-status-chip-list'), /flex-wrap: wrap/)
  assert.match(rule(MODAL_CSS, '.it-modal__table-wrap'), /overflow-x: auto/)
  const print = ui.REPORT_PRINT_PAGE_STYLE
  assert.doesNotMatch(print, /word-break:\s*break-word/, 'no per-character header/number breaking')
  assert.match(print, /overflow-wrap: break-word/)
  assert.match(print, /th \{[^}]*white-space: nowrap/)
  assert.match(print, /\.it-col-num,\s*\.it-col-nowrap \{[^}]*white-space: nowrap/)
  assert.match(print, /display: table-header-group/, 'header repeats on each printed page')

  const html = render(ui.h(ui.StudentSummaryTable, { data: { students: [] }, empty: 'none' }))
  assert.equal(html, 'none')
  const table = render(ui.h(ui.StudentSummaryTable, { data: { students: [{ student_name: 'A', student_number: '1', status: 'active', hours_rendered: 0, target_hours: 300, progress_pct: 0, validated_days: 0, approved_journals: 0, approved_docs: 0, required_docs: 13 }] } }))
  for (const head of ['Hours', 'Days', 'Journals ✓', 'Docs ✓']) {
    assert.match(table, new RegExp(`<th class="it-col-num">${head}</th>`), `${head} is a compact one-line column`)
  }
})

test('REPORT-DATA report tables render every value exactly as the API returns it', () => {
  const row = {
    student_name: 'Christian Hero Aboy Valinado', student_number: '2300600',
    program: 'Bachelor of Science in Information Technology', company: 'Tata Consultancy Services (TCS) Philippines',
    status: 'pending_placement', hours_rendered: 240, target_hours: 500, progress_pct: 48,
    validated_days: 30, approved_journals: 5, approved_docs: 11, required_docs: 18,
  }
  const html = render(ui.h(ui.StudentSummaryTable, { data: { students: [row] } }))
  for (const value of ['Christian Hero Aboy Valinado', '2300600', 'Bachelor of Science in Information Technology',
    'Tata Consultancy Services (TCS) Philippines', '240/500', '48%', '>30<', '>5<', '11/18']) {
    assert.ok(html.includes(value), `renders ${value}`)
  }
  assert.match(html, /width:48%/)

  const compliance = render(ui.h(ui.ComplianceTable, { data: { rows: [COMPLIANCE_ROW] } }))
  assert.match(compliance, />46%</, 'compliance percentage unchanged')
  assert.match(compliance, /6\/13 approved/)
  assert.match(compliance, /1 missing · 1 rejected/)

  const done = render(ui.h(ui.ComplianceApprovedProgress, { pct: 100, approved: 13, required: 13, requirements: [] }))
  assert.match(done, /bg-success/)
  assert.doesNotMatch(done, /compliance-problem-indicator/)
})

test('REPORT-UI-04 Faculty tabs and the hub tab bars use the shared green tabs', () => {
  const faculty = read('pages/faculty/FacultyAssignedStudents.jsx')
  const tabs = faculty.slice(faculty.indexOf('{/* Tab bar */}'))
  assert.match(tabs, /nav nav-tabs custom-tabs/)
  for (const label of ['Student Roster', 'Journal Review Queue', 'Attendance Monitor']) assert.ok(faculty.includes(label))
  for (const hub of ['pages/coordinator/CoordPlacementHub.jsx', 'pages/director/DirectorMoaHub.jsx']) {
    const src = read(hub)
    assert.match(src, /custom-tabs/, hub)
    assert.doesNotMatch(src, /text-primary/, `${hub} has no blue tab text`)
  }
  assert.match(rule(STYLES, '.custom-tabs .nav-link.active'), /var\(--green-dark, #0a5c2e\).*var\(--green-main, #1a7a3f\)/)
  assert.match(rule(STYLES, '.custom-tabs .nav-link i'), /color: var\(--green-main, #1a7a3f\)/)
})

test('REPORT-UI-05 the FO-24 explanatory banner is gone and the actions remain', () => {
  const src = read('pages/faculty/FacultyEvaluations.jsx')
  assert.doesNotMatch(src, /As Faculty, you have access to the/)
  assert.doesNotMatch(src, /fo24-review__info/)
  assert.doesNotMatch(STYLES, /\.fo24-review__info/)
  for (const kept of ['Approve', 'Release', 'Preview', 'fo24-review__filters']) {
    assert.ok(src.includes(kept), `${kept} still present`)
  }
})

test('REPORT-UI-06 / DATE-UI-01 company MOA expiry renders as a readable date', () => {
  assert.equal(ui.time.formatDisplayDate('2028-01-05T00:00:00.000000Z', { month: 'short', day: 'numeric', year: 'numeric' }), 'Jan 5, 2028')
  for (const page of ['pages/director/DirectorCompanies.jsx', 'pages/director/DirectorMOAManagement.jsx', 'pages/director/DirectorMOAMonitoring.jsx']) {
    const src = read(page)
    assert.match(src, /formatDisplayDate\(c\.moa_expiry_date/, page)
    assert.doesNotMatch(src, /\{c\.moa_expiry_date \?\? /, `${page} renders no raw expiry`)
  }
})

test('DATE-UI-02..04 date-only, timestamp (Asia/Manila) and empty values', () => {
  assert.equal(ui.time.formatDisplayDate('2028-01-05'), 'January 5, 2028')
  assert.doesNotMatch(ui.time.formatDisplayDate('2028-01-05T00:00:00.000000Z'), /T|Z|:/)
  // Timestamps convert to Manila: 06:58 UTC is 2:58 PM, and 17:30 UTC is already the next Manila day.
  assert.equal(ui.time.formatManilaDateTime('2026-09-25T06:58:00Z'), 'Sep 25, 2026, 2:58 PM')
  assert.equal(ui.time.formatManilaDate('2026-09-25T17:30:00Z'), 'Sep 26, 2026')
  assert.equal(ui.time.formatManilaDateTime(null), '—')
  assert.equal(ui.time.formatManilaDate(undefined), '—')
  assert.equal(ui.time.formatDisplayDate(null) || '—', '—')
  assert.equal(ui.time.formatManilaDateTime('not a date'), '—')
})

test('ADMIN-UI-01 Quick Actions are an evenly sized, wrapping action grid', () => {
  const src = read('pages/admin/MisdDashboard.jsx')
  for (const label of ['Assign Director', 'Manage Coordinators', 'Section Mappings', 'All Users', 'Sync Students']) {
    assert.ok(src.includes(`label: '${label}'`), label)
  }
  assert.match(rule(STYLES, '.misd-actions'), /grid-template-columns: repeat\(auto-fit, minmax\(min\(100%, 12\.5rem\), 1fr\)\)/)
  assert.match(rule(STYLES, '.misd-action'), /min-height: 64px/)
})

test('ADMIN-UI-02 metric cards align and label active vs total counts', () => {
  const src = read('pages/admin/MisdDashboard.jsx')
  assert.match(rule(STYLES, '.misd-metrics'), /grid-template-columns: repeat\(auto-fit, minmax\(min\(100%, 8\.5rem\), 1fr\)\)/)
  assert.match(rule(STYLES, '.misd-metric'), /display: flex; flex-direction: column;/)
  assert.match(src, /active \{active === 1 \? 'account' : 'accounts'\}/)
  assert.match(src, /\{total\} total/)
  // Truthful integration presentation, no development labels or internal URIs.
  for (const banned of ['Mock iEnroll', 'Live API', 'status?.base_url', 'in-process']) {
    assert.ok(!src.includes(banned), `dashboard does not show ${banned}`)
  }
})

test('DEFENSE-TEXT development-only helpers are hidden unless explicitly enabled', () => {
  const app = read('App.jsx')
  assert.match(app, /\{DEV_TOOLS_ENABLED && \(\s*<Route path="\/demo\/\*"/)
  assert.match(read('config/devTools.js'), /VITE_ENABLE_DEV_TOOLS === 'true'/)
  for (const builder of ['pages/student/portfolio/CCSPortfolioBuilder.jsx', 'pages/student/portfolio/COEPortfolioBuilder.jsx']) {
    const src = read(builder)
    assert.match(src, /\{DEV_TOOLS_ENABLED && \(\s*<button[\s\S]*?Fill Sample\s*<\/button>\s*\)\}/, builder)
  }
  const visible = [
    ['pages/admin/MisdSyncMonitor.jsx', /mock|localhost|Laravel\)/i],
    ['pages/supervisor/SupervisorAbsorption.jsx', /for demos|DEMO-0|DIR-1001/],
    ['pages/LoginPage.jsx', /Dev Link/],
    ['pages/student/portfolio/COEPortfolioPreview.jsx', /small precision motors|replace this placeholder/],
  ]
  for (const [file, pattern] of visible) assert.doesNotMatch(read(file), pattern, file)
})

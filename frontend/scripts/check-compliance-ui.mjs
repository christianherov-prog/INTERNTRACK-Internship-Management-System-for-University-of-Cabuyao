/**
 * Smoke checks for compliance UI terminology + green approved progress.
 * Run: node scripts/check-compliance-ui.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const src = path.join(root, 'src')

function read(rel) {
  return fs.readFileSync(path.join(src, rel), 'utf8')
}

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) walk(full, out)
    else if (/\.(jsx|js|css)$/.test(entry.name)) out.push(full)
  }
  return out
}

const files = walk(src)
const missingDocumentsHits = files
  .map((f) => ({ f, text: fs.readFileSync(f, 'utf8') }))
  .filter(({ text }) => /Missing Documents/i.test(text))

const coord = read('pages/coordinator/CoordReports.jsx')
const faculty = read('pages/faculty/FacultyReports.jsx')
const progress = read('components/ComplianceApprovedProgress.jsx')
const statusComp = read('components/ComplianceRequirementsStatus.jsx')
const css = read('styles/styles.css')

const checks = [
  ['shared progress uses bg-success', /bg-success/.test(progress)],
  ['shared progress does not use bg-danger', !/bg-danger/.test(progress)],
  ['CoordReports uses ComplianceApprovedProgress', /ComplianceApprovedProgress/.test(coord)],
  ['FacultyReports uses ComplianceApprovedProgress', /ComplianceApprovedProgress/.test(faculty)],
  ['CoordReports has Requirements Status', /Requirements Status/.test(coord)],
  ['FacultyReports has Requirements Status', /Requirements Status/.test(faculty)],
  ['CoordReports uses ComplianceRequirementsStatus', /ComplianceRequirementsStatus/.test(coord)],
  ['FacultyReports uses ComplianceRequirementsStatus', /ComplianceRequirementsStatus/.test(faculty)],
  ['no Missing Requirements column heading', !/Missing Requirements/.test(coord + faculty)],
  ['status component has approved class', /req-status-approved/.test(statusComp)],
  ['status component has pending class', /req-status-pending/.test(statusComp)],
  ['status component has rejected class', /req-status-rejected/.test(statusComp)],
  ['status component has missing class', /req-status-missing/.test(statusComp)],
  ['list omits visible status label spans', !/compliance-req-status-label/.test(statusComp)],
  ['all-approved summary message present', /All Applicable Requirements Approved/.test(statusComp)],
  ['collapses when every requirement approved', /isAllRequirementsApproved/.test(statusComp)],
  ['CSS styles requirement status list', /\.compliance-req-status-list/.test(css)],
  ['CSS uses smaller missing indicator', /\.compliance-req-status-icon i[\s\S]*?font-size:\s*0\.48rem/.test(css)],
  ['no threshold danger bar in CoordReports', !/compliance_pct\s*>=\s*80[\s\S]*bg-danger/.test(coord)],
  ['no threshold danger bar in FacultyReports', !/compliance_pct\s*>=\s*80[\s\S]*bg-danger/.test(faculty)],
  ['no user-facing Missing Documents in src', missingDocumentsHits.length === 0],
]

let failed = 0
for (const [label, ok] of checks) {
  if (!ok) {
    console.error('FAIL', label)
    if (label.includes('Missing Documents')) {
      missingDocumentsHits.forEach(({ f }) => console.error('  ', path.relative(src, f)))
    }
    failed += 1
  } else {
    console.log('PASS', label)
  }
}

if (failed) process.exit(1)
console.log(`OK ${checks.length} compliance UI checks`)

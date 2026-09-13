/**
 * Smoke checks: attendance validation modal date formatting + topbar avatar hover.
 * Run: node scripts/check-attendance-date-and-avatar-ui.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { formatDisplayDate, toDateOnlyString } from '../src/utils/manilaTime.js'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const page = fs.readFileSync(path.join(root, 'src/pages/supervisor/SupervisorAttendanceValidation.jsx'), 'utf8')
const faculty = fs.readFileSync(path.join(root, 'src/pages/faculty/FacultyAssignedStudents.jsx'), 'utf8')
const css = fs.readFileSync(path.join(root, 'src/styles/styles.css'), 'utf8')
const topbar = fs.readFileSync(path.join(root, 'src/components/Topbar.jsx'), 'utf8')

const iso = '2026-09-13T00:00:00.000000Z'
const displayed = formatDisplayDate(iso)
const dateOnly = toDateOnlyString(iso)

const checks = [
  ['toDateOnlyString keeps calendar day', dateOnly === '2026-09-13'],
  ['formatDisplayDate human readable', displayed === 'September 13, 2026'],
  ['no raw ISO in formatDisplayDate', !displayed.includes('T00:00:00')],
  ['supervisor page imports formatDisplayDate', /formatDisplayDate/.test(page)],
  ['validate modal uses formatDisplayDate', /formatDisplayDate\(log\.date\)/.test(page)],
  ['reject modal uses formatDisplayDate', /formatDisplayDate\(rejectModal\.date\)/.test(page)],
  ['faculty correction confirm formats date', /formatDisplayDate\(correction\.date\)/.test(faculty)],
  ['shared Topbar uses topbar-avatar', /className="topbar-avatar"/.test(topbar)],
  ['global avatar hover override present', /GLOBAL TOPBAR AVATAR/.test(css)],
  ['avatar hover forces transform none', /\.topbar-avatar:hover[\s\S]*?transform:\s*none\s*!important/.test(css)],
  ['avatar focus-visible kept', /\.topbar-avatar:focus-visible/.test(css)],
]

let failed = 0
for (const [label, ok] of checks) {
  if (!ok) {
    console.error('FAIL', label, label.includes('human') ? `(got: ${displayed})` : '')
    failed += 1
  } else {
    console.log('PASS', label)
  }
}
if (failed) process.exit(1)
console.log(`OK ${checks.length} attendance-date/avatar checks`)

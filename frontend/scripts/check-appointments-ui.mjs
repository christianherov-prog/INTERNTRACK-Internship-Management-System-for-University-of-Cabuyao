/**
 * Smoke checks for Appointments terminology (user-facing).
 * Run: node scripts/check-appointments-ui.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const sidebar = fs.readFileSync(path.join(root, 'src/components/Sidebar.jsx'), 'utf8')
const page = fs.readFileSync(path.join(root, 'src/pages/shared/MeetingsPage.jsx'), 'utf8')

const checks = [
  ['sidebar labels Appointments', /text: 'Appointments'/.test(sidebar)],
  ['sidebar has no Meetings nav text', !/text: 'Meetings'/.test(sidebar)],
  ['page title Appointments', /title="Appointments"/.test(page)],
  ['Schedule Appointment', /Schedule Appointment/.test(page)],
  ['Create Appointment', /Create Appointment/.test(page)],
  ['No Appointments empty state', /title="No Appointments"/.test(page)],
  ['Appointment Link label', /Appointment Link/.test(page)],
  ['Appointment Type label', /Appointment Type/.test(page)],
  ['internal /meetings API retained', /api\.(get|post|patch)\(`?['"]\/meetings/.test(page) || /api\.get\('\/meetings'\)/.test(page)],
]

let failed = 0
for (const [label, ok] of checks) {
  if (!ok) {
    console.error('FAIL', label)
    failed += 1
  } else {
    console.log('PASS', label)
  }
}

if (failed) process.exit(1)
console.log(`OK ${checks.length} appointments UI checks`)

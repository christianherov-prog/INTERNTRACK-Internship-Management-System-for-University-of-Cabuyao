// ATT-TZ-01..03 (UI side): attendance clock values arrive already resolved to
// Asia/Manila (clock_in_display, *_display correction fields, schedules) and
// are shown in 12-hour form without any further timezone conversion.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { FRONTEND_ROOT } from './helpers/bundle.mjs'
import { formatClock12 } from '../src/utils/manilaTime.js'

const read = (rel) => readFileSync(join(FRONTEND_ROOT, 'src', rel), 'utf8').replace(/\r\n/g, '\n')

test('ATT-TZ-01..03 Manila wall-clock values render as 12-hour times', () => {
  assert.equal(formatClock12('08:00'), '8:00 AM')
  assert.equal(formatClock12('17:00'), '5:00 PM')
  assert.equal(formatClock12('13:00'), '1:00 PM')
  assert.equal(formatClock12('12:00:00'), '12:00 PM')
  assert.equal(formatClock12('00:05'), '12:05 AM')
  assert.equal(formatClock12('07:58:00'), '7:58 AM')
  assert.equal(formatClock12(null), '—')
  assert.equal(formatClock12(''), '—')
  assert.equal(formatClock12('not a time'), '—')
})

test('ATT-UI attendance tables use API display values and the shared formatter', () => {
  const pages = {
    'pages/student/StudentAttendance.jsx': /fmtTime\(log\.clock_in_display/,
    'pages/supervisor/SupervisorAttendanceValidation.jsx': /fmtTime\(log\.clock_in_display/,
    'pages/faculty/FacultyAttendance.jsx': /formatClock12\(log\.clock_in_display\)/,
    'pages/faculty/FacultyAssignedStudents.jsx': /formatClock12\(log\.clock_in_display\)/,
  }
  for (const [file, pattern] of Object.entries(pages)) {
    const src = read(file)
    assert.match(src, pattern, file)
    // Corrections are stored in the app timezone; only the *_display fields are shown.
    assert.doesNotMatch(src, /(?:c|correction)\.(?:original|requested)_clock_(?:in|out)(?!_display)\b/, `${file} shows raw correction times`)
    assert.doesNotMatch(src, /String\(t\)\.slice\(0, 5\)/, `${file} still has a 24-hour slice formatter`)
  }
})

import { displayLabel } from './displayLabel'
import { formatStudentName } from './formatName'
import { formatYearSection } from './formatSection'
import { CURRENT_TERM } from '../config/term'

export const MISSING_IDENTITY = 'Not available — contact your coordinator/faculty or you are not yet assigned to an internship'

export const FORM_IDENTITY_FIELDS = {
  'FO-23': [
    { key: 'semester', label: 'Semester/Midyear' },
    { key: 'academicYear', label: 'Academic Year' },
    { key: 'studentName', label: 'Student Name' },
    { key: 'program', label: 'Program' },
    { key: 'facultyName', label: 'Internship Teaching Personnel' },
  ],
  'FO-22': [
    { key: 'semester', label: 'Semester/Midyear' },
    { key: 'academicYear', label: 'Academic Year' },
    { key: 'studentName', label: 'Student Name' },
    { key: 'program', label: 'Program' },
    { key: 'companyName', label: 'Host Training Establishment (HTE)' },
    { key: 'companyAddress', label: 'Company Address' },
    { key: 'supervisorName', label: 'Internship Company Supervisor' },
    { key: 'supervisorPosition', label: 'Position' },
  ],
  'FO-24': [
    { key: 'studentName', label: 'Name of Trainee' },
    { key: 'program', label: 'Program' },
    { key: 'trainingPeriod', label: 'Training Period' },
    { key: 'academicYear', label: 'Academic Year' },
    { key: 'semester', label: 'Semester/Midyear' },
  ],
  'FO-03': [
    { key: 'companyName', label: 'Company Name' },
    { key: 'companyDepartment', label: 'Department' },
    { key: 'studentName', label: "Intern's Name" },
    { key: 'programSection', label: 'Program/Section' },
    { key: 'trainingPeriod', label: 'Internship Period' },
  ],
}

function firstNonEmpty(...values) {
  for (const value of values) {
    if (value == null || value === false) continue
    const text = typeof value === 'string' ? value.trim() : value
    if (text === '' || text === '—') continue
    return text
  }
  return ''
}

function studentProfileOf(internship, extras = {}) {
  return (
    internship?.student?.student_profile
    || internship?.student?.studentProfile
    || extras.profile
    || extras.user?.student_profile
    || extras.user?.studentProfile
    || null
  )
}

function facultyProfileOf(internship) {
  return internship?.faculty?.faculty_profile
    || internship?.faculty?.facultyProfile
    || null
}

function supervisorProfileOf(internship) {
  return internship?.supervisor?.supervisor_profile
    || internship?.supervisor?.supervisorProfile
    || null
}

function personName(person, fallback = '') {
  if (!person) return fallback
  if (typeof person === 'string') return person.trim()
  const formatted = formatStudentName(person)
  if (formatted && formatted !== '—') return formatted
  return firstNonEmpty(person.name, person.full_name, person.username, fallback)
}

function parseSemester(raw, term) {
  const blob = [raw, term].filter(Boolean).join(' ').toLowerCase()
  if (!String(blob).trim()) return { label: '', period: '' }
  if (/\b(mid[\s-]?year|summer|3rd)\b/.test(blob) || String(raw) === '3' || Number(raw) === 3) {
    return { label: 'Midyear', period: 'midyear' }
  }
  if (/\b(2nd|second|sem(?:ester)?\s*2)\b/.test(blob) || String(raw) === '2' || Number(raw) === 2) {
    return { label: '2nd', period: '2nd' }
  }
  if (/\b(1st|first|sem(?:ester)?\s*1)\b/.test(blob) || String(raw) === '1' || Number(raw) === 1) {
    return { label: '1st', period: '1st' }
  }
  return { label: '', period: '' }
}

function parseAcademicYear(internship, user, term) {
  const direct = firstNonEmpty(
    internship?.academic_year,
    internship?.school_year,
    studentProfileOf(internship, { user })?.school_year,
  )
  if (direct) return String(direct).replace(/^AY\s*/i, '').replace(/\s+/g, ' ').trim()

  const haystack = firstNonEmpty(internship?.term, user?.term, term)
  const match = String(haystack).match(/([0-9]{4}\s*[-–]\s*[0-9]{4})/)
  return match ? match[1].replace(/\s+/g, '') : ''
}

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function trainingPeriod(internship) {
  const start = formatDate(internship?.start_date)
  const end = formatDate(internship?.end_date)
  if (start && end) return `${start} – ${end}`
  return start || end || ''
}

/**
 * Single source for official-form identity/reference values.
 * `internship` is the assignment record; `extras.user` fills student-owned forms.
 */
export function resolveFormIdentity(internship, extras = {}) {
  const user = extras.user || null
  const term = extras.term || user?.term || CURRENT_TERM
  const profile = studentProfileOf(internship, extras)
  const faculty = facultyProfileOf(internship)
  const supervisor = supervisorProfileOf(internship)
  const company = internship?.company || {}

  const program = displayLabel(
    profile?.program || profile?.course_name || internship?.program || user?.program || user?.program_code,
  )
  const section = formatYearSection(profile?.section || user?.section, profile?.year_level || user?.year_level) || ''
  const semester = parseSemester(
    firstNonEmpty(internship?.semester, profile?.semester, user?.semester),
    term,
  )

  const studentName = firstNonEmpty(
    personName(profile),
    user?.name,
    internship?.student?.name,
    internship?.student_name,
  )
  const facultyName = firstNonEmpty(
    personName(faculty),
    internship?.faculty?.name,
    typeof user?.faculty === 'string' ? user.faculty : '',
  )
  const supervisorName = firstNonEmpty(
    personName(supervisor),
    internship?.supervisor?.name,
    extras.evalData?.supervisor_name,
    extras.evalData?.evaluator_name,
  )

  const programSection = [program, section].filter(Boolean).join(' / ')

  return {
    studentName,
    program,
    section,
    programSection,
    facultyName,
    supervisorName,
    supervisorPosition: firstNonEmpty(supervisor?.position, supervisor?.designation),
    companyName: firstNonEmpty(company.company_name, company.name, internship?.company_name),
    companyAddress: firstNonEmpty(company.address),
    companyDepartment: firstNonEmpty(company.department),
    semester: semester.label,
    evaluationPeriod: semester.period,
    academicYear: parseAcademicYear(internship, user, term),
    term,
    trainingPeriod: trainingPeriod(internship),
  }
}

export function identityValue(identity, key) {
  if (!identity) return ''
  const value = identity[key]
  return value == null ? '' : String(value).trim()
}

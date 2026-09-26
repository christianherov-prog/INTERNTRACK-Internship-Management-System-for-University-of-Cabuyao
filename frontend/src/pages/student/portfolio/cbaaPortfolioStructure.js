/**
 * CBAA internship portfolio structure (official Table of Contents order) and the
 * status/completion rules shared by the Builder and the Preview.
 *
 * Content sources:
 *  - 'text'   : student-authored rich text (portfolio_sections table)
 *  - 'upload' : student-uploaded portfolio files (documents with a cbaa_* type)
 *  - 'record' : authoritative InternTrack records (requirements, placement, evaluations,
 *               attendance, journals). These are never duplicated by the portfolio.
 */

export const CBAA_COLLEGE = 'College of Business, Accountancy and Administration'
export const UC_ADDRESS = 'Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025'

export const STATUS = {
  complete: { label: 'Complete', icon: 'fa-circle-check', tone: 'success' },
  incomplete: { label: 'Incomplete', icon: 'fa-circle-half-stroke', tone: 'warning' },
  auto: { label: 'Automatically Generated', short: 'Auto', icon: 'fa-gears', tone: 'info' },
  awaiting: { label: 'Awaiting Approval', icon: 'fa-hourglass-half', tone: 'purple' },
  missing: { label: 'Missing Document', icon: 'fa-circle-exclamation', tone: 'danger' },
  unavailable: { label: 'Not Yet Available', icon: 'fa-clock', tone: 'secondary' },
}

/** Student-authored rich-text sections (keys match PortfolioSection::KEYS on the backend). */
export const TEXT_SECTIONS = {
  bio_sketch: { title: 'Biographical Sketch', placeholder: 'Introduce yourself: background, education, skills, organizations, and career goals…' },
  acknowledgment: { title: 'Acknowledgment', placeholder: 'Thank the people and institutions who supported your internship…' },
  company_profile: { title: 'Company Profile', placeholder: 'Describe the company: history, products/services, clients, and your department…' },
  narrative: { title: 'Narrative Insights of Internship Learning Experiences', placeholder: 'Describe your learning experiences, reflections, accomplishments, challenges, and insights…' },
  rec_students: { title: 'Students', placeholder: 'Recommendations for future student interns…' },
  rec_program: { title: 'Internship Program', placeholder: 'Recommendations for the university internship program…' },
  rec_hte: { title: 'Host Training Establishment', placeholder: 'Recommendations for your host training establishment…' },
}

/** Extra company fields stored in custom_fields.cbaa.company. */
export const COMPANY_EXTRA_FIELDS = [
  { key: 'industry', label: 'Industry / Line of Business', max: 255 },
  { key: 'year_established', label: 'Year Established', max: 20 },
  { key: 'contact_person', label: 'Contact Person / Supervisor', max: 255 },
  { key: 'contact_number', label: 'Contact Number', max: 60 },
  { key: 'email', label: 'Company Email', max: 255 },
  { key: 'website', label: 'Website', max: 255 },
]

/** Program-specific Training Plan form titles (keyed by program code). */
const TRAINING_PLAN_TITLES = {
  BSA: 'Accomplished Student Internship Training Plan — BS Accountancy',
  BSBAMM: 'Accomplished Student Internship Training Plan — BSBA Marketing Management',
  BSBAFM: 'Accomplished Student Internship Training Plan — BSBA Financial Management',
}

export function trainingPlanTitle(programCode, programName) {
  const code = String(programCode || '').toUpperCase()
  if (TRAINING_PLAN_TITLES[code]) return TRAINING_PLAN_TITLES[code]
  return programName
    ? `Accomplished Student Internship Training Plan — ${programName}`
    : 'Accomplished Student Internship Training Plan'
}

export const TRAINING_PLAN_ALIASES = ['cbaa_training_plan', 'training_plan', 'Training Plan', 'Internship Training Plan']
export const HTE_PHOTO_TYPE = 'cbaa_hte_photo'
export const COMPANY_LOGO_TYPE = 'company_logo'

/**
 * Appendix entries in the exact official order.
 *  upload: whether the student may provide the file when the system has none.
 *  evaluation: FO code for system-generated evaluation forms (never uploaded).
 *  aliases: document types / requirement names already used elsewhere in InternTrack.
 *  multiple: allows several files (e.g. work samples).
 */
export const APPENDICES = [
  { key: 'application_letter', title: 'Received Application Letter', upload: true, aliases: ['application_letter', 'Application Letter'] },
  { key: 'cv', title: 'Curriculum Vitae', upload: true, aliases: ['student_cv', 'Curriculum Vitae', 'Curriculum Vitae (PNC:AA-FO-27)'] },
  { key: 'recommendation_request', title: 'Internship Host Training Establishment Request for Recommendation', upload: true, aliases: ['recommendation_request', 'Recommendation Letter', 'Recommendation Letter Request'] },
  { key: 'endorsement_letter', title: 'Coded Endorsement Letter', upload: true, aliases: ['endorsement_letter', 'Endorsement Letter', 'Coded Endorsement Letter'] },
  { key: 'acceptance_form', title: 'Student Internship Acceptance Form', upload: true, aliases: ['acceptance_form', 'Acceptance Form', 'Internship Acceptance Form', 'Student Internship Acceptance Form (PNC:AA-FO-29)', 'Student Internship Acceptance Form (PNC: AA-FO-29)'] },
  { key: 'completion_certificate', title: 'Certificate of Completion of Training', upload: true, aliases: ['completion_certificate', 'Certificate of Completion', 'Certification of Completion'] },
  { key: 'moa', title: 'Memorandum of Agreement / Letter of Agreement / Terms of Reference', upload: false, unavailableHint: 'Retrieved from your approved placement record once the MOA is on file.', aliases: ['moa_document', 'Memorandum of Agreement', 'MOA / LOA / TOR'] },
  { key: 'consent_form', title: 'Notarized Student Internship Consent Form', upload: true, aliases: ['consent_form', 'Consent Form', 'Internship Consent Form', 'Notarized Student Internship Consent Form (PNC:AA-FO-28)', 'Notarized Student Internship Consent Form (PNC: AA-FO-28)'] },
  { key: 'medical_certificate', title: 'Medical Certificate', upload: true, aliases: ['medical_result', 'Medical Result', 'Medical Clearance', 'Medical Certificate'] },
  { key: 'psychological_certificate', title: 'Psychological Certificate', upload: true, aliases: ['psychological_result', 'Psychological Test Result', 'Psychological Assessment Certificate', 'Psychological Certificate'] },
  { key: 'work_samples', title: 'Work Samples / Outcome', upload: true, multiple: true, aliases: [] },
  { key: 'fo22', title: 'Internship Host Training Establishment Evaluation Form', evaluation: 'FO-22', unavailableHint: 'Generated from your online FO-22 evaluation once submitted.' },
  { key: 'fo23', title: 'Internship Program Evaluation Form', evaluation: 'FO-23', unavailableHint: 'Generated from your online FO-23 evaluation once submitted.' },
  { key: 'fo24', title: 'Student Intern Performance Evaluation Form', evaluation: 'FO-24', unavailableHint: 'Generated once your supervisor submits and releases the FO-24 evaluation.' },
  { key: 'fo03', title: 'HTE Evaluation to the University Program', evaluation: 'FO-03', unavailableHint: 'Generated once your HTE supervisor submits the FO-03 evaluation.' },
  { key: 'orientation_certificate', title: 'Internship Orientation Certificate', upload: true, aliases: ['orientation_certificate', 'Orientation Certificate', 'Internship Orientation Certificate'] },
  { key: 'ef_set', title: 'EF Set Result', upload: true, aliases: ['ef_set', 'EF Set Result', 'EF SET Result', 'EF Set Certificate'] },
  { key: 'progress_monitoring', title: 'Accomplished Progress Monitoring Form', upload: true, aliases: ['progress_monitoring', 'Progress Monitoring Form', 'visitation_form', 'Internship / OJT Visitation Form', 'Visitation Form'] },
]

export const appendixUploadType = (key) => `cbaa_app_${key}`

const norm = (v) => String(v || '').trim().toLowerCase()

/** Documents matching a list of type aliases (uploaded file or approved requirement). */
export function findDocs(photos, aliases) {
  const wanted = new Set(aliases.map(norm))
  return (photos || []).filter((d) => wanted.has(norm(d.type)) || wanted.has(norm(d.document_type)))
}

export function isStudentUpload(doc) {
  return String(doc?.document_type || '').startsWith('cbaa_')
}

function hasPending(recordStatus, aliases) {
  const wanted = new Set(aliases.map(norm))
  return (recordStatus?.documents || []).some((d) => wanted.has(norm(d.document_type))
    && !['rejected', 'returned'].includes(norm(d.status)))
}

export function pickEvaluation(evaluations, formType) {
  const list = (evaluations || []).filter((e) => e.form_type === formType)
  return list.find((e) => e.submitted_at) || list[0] || null
}

/** Visible text of sanitized HTML (used for completeness checks). */
export function htmlHasText(html) {
  if (!html) return false
  const text = String(html).replace(/<[^>]*>/g, ' ').replace(/&nbsp;|&#160;/g, ' ')
  return text.trim().length > 0
}

/** Resolve an appendix entry into { status, docs, source }. */
export function appendixState(entry, { photos, recordStatus, evaluations }) {
  if (entry.evaluation) {
    const ev = pickEvaluation(evaluations, entry.evaluation)
    if (ev?.submitted_at) return { status: 'auto', docs: [], evalData: ev, source: 'record' }
    if (ev) return { status: 'awaiting', docs: [], evalData: ev, source: 'record' }
    return { status: 'unavailable', docs: [], evalData: null, source: 'record' }
  }

  const aliases = [appendixUploadType(entry.key), ...(entry.aliases || [])]
  const docs = findDocs(photos, aliases)
  if (docs.length > 0) {
    const source = docs.every(isStudentUpload) ? 'upload' : 'record'
    return { status: source === 'record' ? 'auto' : 'complete', docs, source }
  }
  if (hasPending(recordStatus, entry.aliases || [])) return { status: 'awaiting', docs: [], source: 'record' }
  return { status: entry.upload ? 'missing' : 'unavailable', docs: [], source: null }
}

/** Validated/authoritative Form 30 rows only (pending or rejected logs are excluded). */
export function authoritativeAttendance(rows) {
  return (rows || []).filter((r) => r.validated || r.status === 'validated' || r.status === 'absent' || r.id == null)
}

const DONE = new Set(['complete', 'auto'])

/**
 * Build the navigation tree with a status for every node and an overall completion
 * percentage computed only from applicable required components.
 */
export function buildPortfolioState({ sections, company, photos, recordStatus, evaluations, journals, attendance, programCode, programName }) {
  const text = (key) => (htmlHasText(sections?.[key]) ? 'complete' : 'incomplete')

  const companyComplete = Boolean(String(company?.company_name || '').trim()) && Boolean(String(company?.company_address || '').trim()) && htmlHasText(sections?.company_profile)
  const htePhotos = findDocs(photos, [HTE_PHOTO_TYPE])

  const tpDocs = findDocs(photos, TRAINING_PLAN_ALIASES)
  let trainingPlan = 'missing'
  if (tpDocs.length) trainingPlan = tpDocs.every(isStudentUpload) ? 'complete' : 'auto'
  else if (hasPending(recordStatus, TRAINING_PLAN_ALIASES)) trainingPlan = 'awaiting'

  const validatedDays = authoritativeAttendance(attendance).filter((r) => r.validated || r.status === 'validated').length
  const pendingDays = (recordStatus?.attendance?.pending || 0)
  const dtr = validatedDays > 0 ? 'auto' : (pendingDays > 0 ? 'awaiting' : 'unavailable')

  const approvedJournals = (journals || []).length
  const pendingJournals = recordStatus?.journals?.submitted || 0
  const journal = approvedJournals > 0 ? 'auto' : (pendingJournals > 0 ? 'awaiting' : 'unavailable')

  const appendices = APPENDICES.map((entry) => ({
    ...entry,
    ...appendixState(entry, { photos, recordStatus, evaluations }),
  }))

  const recs = ['rec_students', 'rec_program', 'rec_hte'].map(text)

  const nodes = [
    { id: 'bio_sketch', num: 'I.', title: 'Biographical Sketch', status: text('bio_sketch'), required: true },
    { id: 'acknowledgment', num: 'II.', title: 'Acknowledgment', status: text('acknowledgment'), required: true },
    { id: 'toc', num: 'III.', title: 'Table of Contents', status: 'auto', required: false },
    {
      id: 'hte', num: 'IV.', title: 'Host Training Establishment', children: [
        { id: 'company_profile', num: '1.', title: 'Company Profile', status: companyComplete ? 'complete' : 'incomplete', required: true },
        { id: 'photos', num: '2.', title: 'Photos', status: htePhotos.length ? 'complete' : 'incomplete', required: true, count: htePhotos.length },
      ],
    },
    {
      id: 'proper', num: 'V.', title: 'Internship Proper', children: [
        { id: 'narrative', num: '1.', title: 'Narrative Insights', status: text('narrative'), required: true },
        {
          id: 'recommendations', num: '2.', title: 'Recommendations', children: [
            { id: 'rec_students', num: '2.1', title: 'Students', status: recs[0], required: true },
            { id: 'rec_program', num: '2.2', title: 'Internship Program', status: recs[1], required: true },
            { id: 'rec_hte', num: '2.3', title: 'Host Training Establishment', status: recs[2], required: true },
          ],
        },
        { id: 'training_plan', num: '3.', title: 'Training Plan', fullTitle: trainingPlanTitle(programCode, programName), status: trainingPlan, required: true },
        { id: 'dtr', num: '4.', title: 'Form 30 – DTR', status: dtr, required: true, meta: { validatedDays } },
        { id: 'journal', num: '5.', title: 'Form 31 – Weekly Journal', status: journal, required: true, meta: { approvedJournals, pendingJournals } },
      ],
    },
    { id: 'appendices', num: 'VI.', title: 'Appendices', appendices, required: false },
  ]

  const leaves = []
  const walk = (list) => list.forEach((n) => {
    if (n.children) walk(n.children)
    else if (n.required) leaves.push(n.status)
  })
  walk(nodes)
  appendices.forEach((a) => leaves.push(a.status))

  const done = leaves.filter((s) => DONE.has(s)).length
  const appendixDone = appendices.filter((a) => DONE.has(a.status)).length

  return {
    nodes,
    appendices,
    appendixDone,
    completion: leaves.length ? Math.round((done / leaves.length) * 100) : 0,
    doneCount: done,
    totalCount: leaves.length,
  }
}

export const appendixLetter = (index) => String.fromCharCode(65 + index)

/** Builder tabs — one per top-level Table of Contents entry. */
export const BUILDER_TABS = [
  { key: 'bio_sketch', num: 'I', label: 'Biographical Sketch', icon: 'fa-user' },
  { key: 'acknowledgment', num: 'II', label: 'Acknowledgment', icon: 'fa-hands-praying' },
  { key: 'toc', num: 'III', label: 'Table of Contents', icon: 'fa-list-ol' },
  { key: 'hte', num: 'IV', label: 'Host Training Establishment', icon: 'fa-building' },
  { key: 'proper', num: 'V', label: 'Internship Proper', icon: 'fa-briefcase' },
  { key: 'appendices', num: 'VI', label: 'Appendices', icon: 'fa-folder-open' },
]

/**
 * Single source of truth for the CBAA Table of Contents. The Preview prints these
 * rows (with page numbers resolved from each row's data-toc-id), and the Builder
 * uses the same ids, labels, and tab mapping so both always stay in sync.
 */
export function buildToc({ trainingPlanTitle: tpTitle, journals = [], appendices = APPENDICES }) {
  return [
    { id: 'bio_sketch', label: 'I. BIOGRAPHICAL SKETCH', bold: true, tab: 'bio_sketch' },
    { id: 'acknowledgment', label: 'II. ACKNOWLEDGMENT', bold: true, tab: 'acknowledgment' },
    { id: 'toc', label: 'III. TABLE OF CONTENTS', bold: true, tab: 'toc' },
    { id: 'hte', label: 'IV. HOST TRAINING ESTABLISHMENT', bold: true, tab: 'hte' },
    { id: 'company_profile', label: '1. Company Profile', level: 1, tab: 'hte' },
    { id: 'photos', label: '2. Photos', level: 1, tab: 'hte' },
    { id: 'proper', label: 'V. INTERNSHIP PROPER', bold: true, tab: 'proper' },
    { id: 'narrative', label: '1. Narrative Insights of Internship Learning Experiences', level: 1, tab: 'proper' },
    { id: 'recommendations', label: '2. Recommendations', level: 1, tab: 'proper' },
    { id: 'rec_students', label: '2.1 Students', level: 2, tab: 'proper' },
    { id: 'rec_program', label: '2.2 Internship Program', level: 2, tab: 'proper' },
    { id: 'rec_hte', label: '2.3 Host Training Establishment', level: 2, tab: 'proper' },
    { id: 'training_plan', label: `3. ${tpTitle || 'Accomplished Student Internship Training Plan'}`, level: 1, tab: 'proper' },
    { id: 'dtr', label: '4. Student Internship Daily Time Record – Form 30', level: 1, tab: 'proper' },
    { id: 'journal', label: '5. Weekly Student Internship Journal – Form 31', level: 1, tab: 'proper' },
    ...journals.map((j) => ({ id: `week-${j.id}`, label: `Week ${j.week_number || j.week}`, level: 2, tab: 'proper', week: true })),
    { id: 'appendices', label: 'VI. APPENDICES', bold: true, tab: 'appendices' },
    ...appendices.map((a, i) => ({ id: `app-${a.key}`, label: `Appendix ${appendixLetter(i)}. ${a.title}`, level: 1, tab: 'appendices', appendixKey: a.key })),
  ]
}

/** Aggregate status for a parent node from its children. */
export function groupStatus(children) {
  const flat = []
  const walk = (list) => list.forEach((c) => (c.children ? walk(c.children) : flat.push(c.status)))
  walk(children)
  if (flat.every((s) => DONE.has(s))) return 'complete'
  if (flat.some((s) => s === 'missing')) return 'missing'
  if (flat.some((s) => s === 'awaiting')) return 'awaiting'
  return 'incomplete'
}

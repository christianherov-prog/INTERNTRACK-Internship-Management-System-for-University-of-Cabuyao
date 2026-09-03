/** Official BS Psychology internship portfolio section list (names + order). */

export const PSY_COURSE = 'PSE 106 - Practicum in Psychology'

export const PSY_ROTATIONS = [
  { id: 1, key: 'r1', title: 'Rotation 1: Educational Setting' },
  { id: 2, key: 'r2', title: 'Rotation 2: Clinical Setting' },
  { id: 3, key: 'r3', title: 'Rotation 3: Industrial/Organizational Setting' },
]

export const emptyRotationFields = () => ({
  hte_name: '',
  hte_address: '',
  hte_profile: '',
  narrative: '',
  rec_students: '',
  rec_program: '',
  rec_curriculum: '',
  rec_hte: '',
})

export const emptyPsychologyFields = () => ({
  1: emptyRotationFields(),
  2: emptyRotationFields(),
  3: emptyRotationFields(),
})

const upload = (suffix, label, tip = '') => ({
  kind: 'upload',
  suffix,
  label,
  tip,
})

export const PRE_INTERNSHIP_UPLOADS = [
  upload('application', 'HTE Received Application Letter from Student Intern'),
  upload('fo29', 'Student Internship Acceptance Form (PNC:AA-FO-29)'),
  upload('recommendation', 'HTE Received Recommendation Letter'),
]

export const INTERNSHIP_UPLOADS = [
  upload('fo255', 'Accomplished Internship Plan (PNC:AA-FO-25.5)'),
  upload('fo31', 'Accomplished Weekly Student Internship Journal Entry (PNC:AA-FO-31)'),
  upload('fo30', 'Accomplished Student Internship Time Record (PNC:AA-FO-30)'),
  upload('dtr', 'Duly signed Daily Time Card / HTE system-generated Attendance Monitoring'),
]

export const POST_INTERNSHIP_UPLOADS = [
  upload('fo22', 'Internship Host Training Establishment Evaluation Form (PNC:AA-FO-22)'),
  upload('fo23', 'Internship Program Evaluation Form (PNC:AA-FO-23)'),
  upload('fo24', 'Student Intern Performance Evaluation Form (PNC:AA-FO-24)'),
  upload('completion', 'Certificate of Completion of Training from HTE (Scanned-Colored)'),
  upload('clearance', 'Student Internship Clearance'),
]

export const APPENDIX_UPLOADS = [
  upload('cv', 'Student Intern Curriculum Vitae (PNC:AA-FO-27)'),
  upload('fo26', 'Internship HTE Request for Recommendation Letter (PNC:AA-FO-26)'),
  upload('completion_copy', 'Certificate of Completion of Training from HTE (Scanned-Colored)'),
  upload('moa', 'Duly signed and notarized Memorandum of Agreement'),
  upload('fo28', 'Duly signed and notarized Student Internship Consent Form (PNC:AA-FO-28)'),
  upload('medical', 'Medical Certificate (PNC:AF-FO-02)'),
  upload('psych_fitness', 'Certificate of Psychological Fitness for Internship (PNC:SDAS-CE-32)'),
  upload('documentation', 'Documentation of Internship Rotation', 'Upload photos or scans from this rotation.'),
]

export const ROTATION_1_EXTRAS = [
  upload('insurance', 'Insurance Policy'),
  upload('pald_soft_skills', 'PALD 21st Century Soft Skills and Standard Labor Education Skills Certificate'),
  upload('chra', 'Certified Human Resource Associate (CHRA) Certificate'),
  upload('pfa', 'Psychological First Aid (PFA) Training Certificate'),
  upload('psych_society', 'Certificate of Membership to Psychology Society-PnC'),
  upload('saisip', 'Certificate of Membership to PnC-SAISIP'),
  upload('enrollment', 'Certificate of Enrollment/Registration Form'),
]

export const ROTATION_2_EXTRAS = [
  upload('case_report', 'Case Report/Case Study/Psychological Narrative Report (Applicable for Clinical Rotation)'),
]

export function docType(rotationId, suffix) {
  return `psy_r${rotationId}_${suffix}`
}

export function extrasForRotation(rotationId) {
  if (rotationId === 1) return ROTATION_1_EXTRAS
  if (rotationId === 2) return ROTATION_2_EXTRAS
  return []
}

export const PNC_FRONT_MATTER = {
  mission:
    'As an institution of higher learning, Pamantasan ng Cabuyao is committed to equip individuals with knowledge, skills, and values that will enable them to achieve their professional goals & provide leadership and service for national development.',
  vision:
    'Pamantasan ng Cabuyao envisions to be a premier institution of higher learning in Region IV, developing globally-competitive and value professionals and leaders instrumental to community development and nation-building.',
  qualityPolicy:
    'Pamantasan ng Cabuyao commits to adhering to statutory and regulatory requirements, promoting high levels of customer engagement, and maintaining an effective quality management system through periodic review and communication of quality objectives for continuous improvement of quality services in instruction, research, and extension.',
  coreValuesIntro:
    'As a God-fearing institution respecting multi-faith of people, PnC adheres to the following core values:',
  coreValues: ['Personal Dignity', 'Nurturing Community', 'Commitment to Excellence'],
  qualityObjectives: [
    'To promote analytical thinking among the faculty and students for continuing intellectual growth and advancement of learning and research.',
    'To develop the youth to become responsible leaders, as well as productive and actively involve citizens of the local and global community with good values and excellent character.',
    'To preserve, enrich, and transmit the historical, cultural heritage, and desirable Filipino values and character.',
    'To nurture an integrated multi-disciplinary university that promotes excellence in instruction, research, and extension.',
    'To be the research and development arm of the local government unit.',
    'To strengthen industry-academe-LGU linkage in order to realize the vision and mission of the University.',
  ],
}

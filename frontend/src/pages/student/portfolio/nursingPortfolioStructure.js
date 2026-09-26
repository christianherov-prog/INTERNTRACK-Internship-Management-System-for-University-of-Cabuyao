/** Official BS Nursing internship portfolio section list (names + order from CHAS template). */

export const NUR_COURSE = 'NCM 122 - Intensive Nursing Practicum'

export const NUR_ROTATIONS = [
  { id: 1, key: 'r1', title: 'Rotation 1: School Clinic' },
  { id: 2, key: 'r2', title: 'Rotation 2: Hospital (Operating Room)' },
  { id: 3, key: 'r3', title: 'Rotation 3: Diagnostic Clinic' },
  { id: 4, key: 'r4', title: 'Rotation 4: Hospital (OR / DR)' },
  { id: 5, key: 'r5', title: 'Rotation 5: Health Center' },
]

export const emptyRotationFields = () => ({
  hte_name: '',
  hte_address: '',
  hte_profile: '',
  hte_vision: '',
  hte_mission: '',
  hte_values: '',
})

export const emptyNursingFields = () => ({
  bio_sketch: '',
  acknowledgement: '',
  narrative: '',
  rec_students: '',
  rec_program: '',
  rec_curriculum: '',
  rec_hte: '',
  rotations: {
    1: emptyRotationFields(),
    2: emptyRotationFields(),
    3: emptyRotationFields(),
    4: emptyRotationFields(),
    5: emptyRotationFields(),
  },
})

const upload = (suffix, label, tip = '') => ({
  kind: 'upload',
  suffix,
  label,
  tip,
})

export const ROTATION_UPLOADS = [
  upload('hte_photos', 'HTE Profile Photos', 'Photos of the cooperating site for this rotation.'),
  upload('training_plan', 'Accomplished Internship Training Plan'),
  upload('journal', 'Weekly Student Internship Journal'),
  upload('application', 'Received Application Letter'),
  upload('recommendation', 'Received Recommendation Letter'),
  upload('acceptance', 'Student Internship Acceptance Form'),
  upload('evaluation', 'Student Evaluation Forms'),
  upload('completion', 'Certificate of Completion of Training'),
  upload('consent', 'Notarized Student Internship Consent Form'),
  upload('documentation', 'Photo Documentation of this Rotation'),
]

export const GLOBAL_UPLOADS = [
  upload('cv', 'Curriculum Vitae'),
  upload('endorsement_school', 'Received Endorsement Letter (School)'),
  upload('endorsement_company', 'Received Endorsement Letter (Company / Hospital)'),
  upload('endorsement_hc', 'Received Endorsement Letter (Health Center)'),
  upload('endorsement_peso', 'Received Endorsement Letter (PESO)'),
  upload('moa_school', 'Memorandum of Agreement (School)'),
  upload('moa_diagnostic', 'Memorandum of Agreement (Diagnostic / Clinic)'),
  upload('insurance', 'Insurance Policy'),
  upload('medical', 'Medical Certificate'),
  upload('psych_cert', 'Psychological Certificate'),
  upload('program_plan', 'Program Plan'),
  upload('after_activity', 'After-Activity Report'),
  upload('wadhwani', 'Certificates of Completion to Employability Training by Wadhwani'),
]

export function rotationDocType(rotationId, suffix) {
  return `nurs_r${rotationId}_${suffix}`
}

export function globalDocType(suffix) {
  return `nurs_${suffix}`
}

export const NUR_ADDRESS = 'Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna 4025'
export const NUR_COLLEGE = 'College of Health and Allied Sciences'

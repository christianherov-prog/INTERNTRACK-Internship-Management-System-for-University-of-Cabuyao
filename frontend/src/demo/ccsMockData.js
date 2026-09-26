/**
 * Shared CCS demo dataset. One connected chain of people and records.
 * Consumed only by /demo pages — never by the authenticated app.
 */

export const DEMO_TERM = 'AY 2025-2026, Sem 2'
export const DEMO_SCHOOL_YEAR = '2025-2026'
export const DEMO_SEMESTER = '2nd Semester'

export const demoDepartment = {
  id: 1,
  code: 'CCS',
  name: 'College of Computer Studies',
  is_active: true,
}

export const demoProgram = {
  id: 1,
  code: 'BSIT',
  name: 'Bachelor of Science in Information Technology',
  department_id: demoDepartment.id,
  department: demoDepartment,
  is_active: true,
}

export const demoCompany = {
  id: 1,
  company_name: 'Cabuyao Digital Solutions',
  address: 'Poblacion, City of Cabuyao, Laguna',
  industry: 'Information Technology',
  contact_person: 'Engr. Paolo Cruz',
  contact_email: 'paolo.cruz@cabuyaodigital.ph',
  contact_number: '049-123-4500',
  moa_status: 'active',
  moa_start_date: '2025-06-01',
  moa_expiry_date: '2027-05-31',
  slots_available: 8,
  is_active: true,
}

export const demoStudent = {
  id: 11,
  role: 'student',
  username: '2300100',
  student_number: '2300100',
  email: 'maria.santos@uc-cabuyao.edu.ph',
  is_active: true,
  student_profile: {
    user_id: 11,
    student_number: '2300100',
    first_name: 'Maria',
    last_name: 'Santos',
    middle_name: 'Reyes',
    email: 'maria.santos@uc-cabuyao.edu.ph',
    contact_number: '0917-555-0100',
    sex: 'F',
    department_id: demoDepartment.id,
    program_id: demoProgram.id,
    program: demoProgram,
    course_name: demoProgram.name,
    section: '4IT-D',
    year_level: 4,
    school_year: DEMO_SCHOOL_YEAR,
    semester: DEMO_SEMESTER,
  },
}

export const demoFaculty = {
  id: 12,
  role: 'faculty',
  username: 'FAC-CCS-101',
  faculty_number: 'FAC-CCS-101',
  email: 'ana.reyes@uc-cabuyao.edu.ph',
  is_active: true,
  faculty_profile: {
    user_id: 12,
    faculty_number: 'FAC-CCS-101',
    first_name: 'Ana',
    last_name: 'Reyes',
    email: 'ana.reyes@uc-cabuyao.edu.ph',
    department_id: demoDepartment.id,
    department: demoDepartment,
    position: 'Faculty Supervisor',
    employment_status: 'Regular',
  },
}

export const demoCoordinator = {
  id: 13,
  role: 'coordinator',
  username: 'COR-CCS-001',
  faculty_number: 'COR-CCS-001',
  email: 'luis.mercado@uc-cabuyao.edu.ph',
  is_active: true,
  faculty_profile: {
    user_id: 13,
    faculty_number: 'COR-CCS-001',
    first_name: 'Luis',
    last_name: 'Mercado',
    email: 'luis.mercado@uc-cabuyao.edu.ph',
    department_id: demoDepartment.id,
    department: demoDepartment,
    position: 'Internship Coordinator',
    employment_status: 'Regular',
  },
}

export const demoSupervisor = {
  id: 14,
  role: 'supervisor',
  username: 'SUP-CCS-01',
  faculty_number: 'SUP-CCS-01',
  email: 'paolo.cruz@cabuyaodigital.ph',
  is_active: true,
  supervisor_profile: {
    user_id: 14,
    first_name: 'Paolo',
    last_name: 'Cruz',
    position: 'IT Supervisor',
    email: 'paolo.cruz@cabuyaodigital.ph',
    contact_number: '0918-555-0140',
    company_id: demoCompany.id,
  },
}

export const demoDirector = {
  id: 15,
  role: 'director',
  username: 'DIR-UC-01',
  faculty_number: 'DIR-UC-01',
  email: 'elena.navarro@uc-cabuyao.edu.ph',
  is_active: true,
  faculty_profile: {
    user_id: 15,
    faculty_number: 'DIR-UC-01',
    first_name: 'Elena',
    last_name: 'Navarro',
    email: 'elena.navarro@uc-cabuyao.edu.ph',
    position: 'Internship Director',
    employment_status: 'Regular',
  },
}

export const demoInternship = {
  id: 101,
  student_id: demoStudent.id,
  faculty_id: demoFaculty.id,
  coordinator_id: demoCoordinator.id,
  supervisor_id: demoSupervisor.id,
  company_id: demoCompany.id,
  status: 'ongoing',
  school_year: DEMO_SCHOOL_YEAR,
  semester: DEMO_SEMESTER,
  term: DEMO_TERM,
  program: demoProgram.name,
  target_hours: 486,
  total_hours_rendered: 210,
  start_date: '2026-06-02',
  expected_end_date: '2026-09-30',
  evaluation_period_status: 'approved',
  student: demoStudent,
  faculty: demoFaculty,
  coordinator: demoCoordinator,
  supervisor: demoSupervisor,
  company: demoCompany,
}

export const demoAttendance = [
  {
    id: 201,
    internship_id: demoInternship.id,
    log_date: '2026-08-04',
    time_in: '08:00',
    time_out: '17:00',
    hours_rendered: 8,
    status: 'validated',
    remarks: 'Sprint planning and API integration',
  },
  {
    id: 202,
    internship_id: demoInternship.id,
    log_date: '2026-08-05',
    time_in: '08:05',
    time_out: '17:00',
    hours_rendered: 8,
    status: 'validated',
    remarks: 'Bug fixes on intern portal',
  },
  {
    id: 203,
    internship_id: demoInternship.id,
    log_date: '2026-08-06',
    time_in: '08:00',
    time_out: '12:00',
    hours_rendered: 4,
    status: 'pending',
    remarks: 'Half-day documentation',
  },
]

export const demoJournals = [
  {
    id: 301,
    internship_id: demoInternship.id,
    week_number: 8,
    entry_number: 8,
    date: '2026-08-04',
    end_date: '2026-08-08',
    activities_summary: 'Implemented intern attendance filters and reviewed pull requests with the IT supervisor.',
    learnings: 'Learned how production logging differs from local Vite debugging.',
    challenges: 'Timezone conversion between Asia/Manila and UTC timestamps.',
    status: 'approved',
    faculty_feedback: 'Clear reflection. Continue documenting blockers.',
  },
  {
    id: 302,
    internship_id: demoInternship.id,
    week_number: 9,
    entry_number: 9,
    date: '2026-08-11',
    end_date: '2026-08-15',
    activities_summary: 'Wrote weekly journal extracts and assisted in UAT of the placement hub.',
    learnings: 'UAT scripts need the same student identity across modules.',
    challenges: 'Incomplete test accounts caused false negatives.',
    status: 'submitted',
  },
]

export const demoDocuments = [
  {
    id: 401,
    internship_id: demoInternship.id,
    document_type: 'Curriculum Vitae',
    status: 'approved',
    current_stage: 'completed',
    submitted_at: '2026-05-20',
  },
  {
    id: 402,
    internship_id: demoInternship.id,
    document_type: 'Application Letter',
    status: 'approved',
    current_stage: 'completed',
    submitted_at: '2026-05-20',
  },
  {
    id: 403,
    internship_id: demoInternship.id,
    document_type: 'Daily Time Record',
    status: 'pending_review',
    current_stage: 'faculty',
    submitted_at: '2026-08-08',
  },
]

export const demoRequirements = [
  { id: 501, name: 'Curriculum Vitae', audience: 'student', is_active: true },
  { id: 502, name: 'Application Letter', audience: 'student', is_active: true },
  { id: 503, name: 'Daily Time Record', audience: 'student', is_active: true },
  { id: 504, name: 'Performance Evaluation', audience: 'student', is_active: true },
]

export const demoEvaluations = [
  {
    id: 601,
    internship_id: demoInternship.id,
    evaluator_type: 'student',
    evaluated_by: demoStudent.id,
    form_type: 'FO-22',
    evaluation_period: 'final',
    average_score: 4.6,
    rating: 'Excellent',
    status: 'completed',
    submitted_at: '2026-08-20',
  },
  {
    id: 602,
    internship_id: demoInternship.id,
    evaluator_type: 'student',
    evaluated_by: demoStudent.id,
    form_type: 'FO-23',
    evaluation_period: 'final',
    average_score: 4.4,
    rating: 'Very Good',
    status: 'completed',
    submitted_at: '2026-08-20',
  },
  {
    id: 603,
    internship_id: demoInternship.id,
    evaluator_type: 'supervisor',
    evaluated_by: demoSupervisor.id,
    form_type: 'FO-24',
    evaluation_period: 'final',
    average_score: 88,
    rating: 'Good',
    status: 'completed',
    submitted_at: '2026-08-22',
  },
  {
    id: 604,
    internship_id: demoInternship.id,
    evaluator_type: 'supervisor',
    evaluated_by: demoSupervisor.id,
    form_type: 'FO-03',
    evaluation_period: 'final',
    average_score: 4.5,
    rating: 'Excellent',
    status: 'completed',
    submitted_at: '2026-08-22',
  },
]

export const demoInvite = {
  status: 'assigned',
  has_supervisor: true,
  supervisor: demoSupervisor,
  company: demoCompany,
}

export const demoPeople = {
  student: demoStudent,
  faculty: demoFaculty,
  coordinator: demoCoordinator,
  supervisor: demoSupervisor,
  director: demoDirector,
}

export const CCS_DEMO = {
  term: DEMO_TERM,
  schoolYear: DEMO_SCHOOL_YEAR,
  semester: DEMO_SEMESTER,
  department: demoDepartment,
  program: demoProgram,
  company: demoCompany,
  student: demoStudent,
  faculty: demoFaculty,
  coordinator: demoCoordinator,
  supervisor: demoSupervisor,
  director: demoDirector,
  internship: demoInternship,
  attendance: demoAttendance,
  journals: demoJournals,
  documents: demoDocuments,
  requirements: demoRequirements,
  evaluations: demoEvaluations,
  invite: demoInvite,
}

export function displayPersonName(person) {
  const p = person?.student_profile || person?.faculty_profile || person?.supervisor_profile
  if (p?.last_name || p?.first_name) {
    return `${p.last_name || ''}, ${p.first_name || ''}`.trim()
  }
  return person?.username || '—'
}

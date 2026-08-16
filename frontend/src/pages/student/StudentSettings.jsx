import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'
import { formatYearSection } from '../../utils/formatSection'

/**
 * Student settings — thin RoleSettings wrapper (same component as other roles).
 * Identity fields are iEnroll-locked (read-only).
 */
function StudentSettings() {
  return (
    <div>
      <RoleSettings
        bodyClass="student-page"
        subtitleLabel="Student"
        notificationsIntro="Choose which internship alerts you want to receive."
        securityIntro="Update your password and strengthen account protection for your school credentials."
        metaFields={[
          { label: 'Student Number', key: 'student_number', fallback: '—' },
          {
            label: 'Year & Section',
            fallback: '—',
            value: (user) => formatYearSection(user?.section, user?.year_level),
          },
          { label: 'Department', key: 'department', fallback: 'College of Computing Studies' },
          { label: 'Program', key: 'program', fallback: 'Bachelor of Science in Information Technology' },
          { label: 'Course Description', key: 'course_description', fallback: 'IT Practicum (500 hours)' },
          { label: 'Assigned Company', key: 'company', fallback: 'None' },
          { label: 'Faculty Teacher', key: 'faculty', fallback: 'Not Assigned' },
          { label: 'Academic Term', key: 'term', fallback: 'AY 2025-2026, 2nd Semester' },
        ]}
        accountExtraFields={[
          {
            name: 'program',
            label: 'Program',
            readOnly: true,

          },
          {
            name: 'course_description',
            label: 'Course Description',
            readOnly: true,

          },
          {
            name: 'sex',
            label: 'Sex',
            type: 'select',
            options: ['Male', 'Female'],
            readOnly: true,

          },
        ]}
        defaultNotifications={{
          emailReminders: true,
          attendanceAlerts: true,
          evaluationReminders: false,
        }}
        notificationDefs={[
          {
            key: 'emailReminders',
            title: 'Email reminders',
            description: 'Receive email reminders for deadlines, document submissions, and evaluations.',
          },
          {
            key: 'attendanceAlerts',
            title: 'Attendance alerts',
            description: 'Get notified when attendance is validated, rejected, or needs attention.',
          },
          {
            key: 'evaluationReminders',
            title: 'Evaluation reminders',
            description: 'Reminders when midterm or final evaluations become available.',
          },
        ]}
      >
        {/* Signature upload — used for auto-stamping Form 30 & Form 31 PDFs */}
        <SignatureUpload />
      </RoleSettings>
    </div>
  )
}

export default StudentSettings

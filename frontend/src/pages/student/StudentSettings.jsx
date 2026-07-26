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
        summaryNote="Your official student identity is synced from iEnroll. Update password and avatar here; contact your registrar for identity corrections."
        accountIntro="Official profile details from iEnroll. These fields are display-only in INTERNTRACK."
        notificationsIntro="Choose which internship alerts you want to receive."
        securityIntro="Update your password and strengthen account protection for your school credentials."
        metaFields={[
          { label: 'Student Number', key: 'student_number', fallback: '—' },
          {
            label: 'Year & Section',
            fallback: '—',
            value: (user) => formatYearSection(user?.section, user?.year_level),
          },
          { label: 'College', key: 'college', fallback: '—' },
          { label: 'Assigned Company', key: 'company', fallback: 'None' },
          { label: 'Faculty Teacher', key: 'faculty', fallback: 'Not Assigned' },
          { label: 'Internship Term', key: 'term', fallback: 'AY 2025-2026, Sem 2' },
        ]}
        accountExtraFields={[
          {
            name: 'program',
            label: 'Program / Course',
            readOnly: true,
            helperText: 'Synced from iEnroll — read-only.',
          },
          {
            name: 'sex',
            label: 'Sex',
            type: 'select',
            options: ['Male', 'Female'],
            readOnly: true,
            helperText: 'Official record from iEnroll — cannot be edited here.',
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

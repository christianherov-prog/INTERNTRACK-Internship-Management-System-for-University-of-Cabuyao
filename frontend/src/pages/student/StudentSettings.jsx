import RoleSettings from '../../components/RoleSettings'

/**
 * Student settings — thin RoleSettings wrapper (same component as other roles).
 */
function StudentSettings() {
  return (
    <RoleSettings
      bodyClass="student-page"
      subtitleLabel="Student"
      summaryNote="Keep your contact details and internship information updated so coordinators can reach you quickly."
      accountIntro="Manage your primary profile details used across records, documents, and monitoring."
      notificationsIntro="Choose which internship alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Assigned Company', key: 'company', fallback: 'None' },
        { label: 'Coordinator', key: 'coordinator', fallback: 'N/A' },
        { label: 'Internship Term', key: 'term', fallback: 'AY 2024-2025, Sem 2' },
      ]}
      accountExtraFields={[
        { name: 'program', label: 'Program / Course' },
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
    />
  )
}

export default StudentSettings

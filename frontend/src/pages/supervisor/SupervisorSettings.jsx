import RoleSettings from '../../components/RoleSettings'

function SupervisorSettings() {
  return (
    <RoleSettings
      bodyClass="supervisor-page"
      subtitleLabel="Supervisor"
      summaryNote="Keep your company contact details updated so interns and the university coordinator can reach you for attendance and evaluations. Supervisors are not in iEnroll, so you manage your own profile here."
      accountIntro="Manage your HTE supervisor profile details used across intern monitoring and evaluations."
      notificationsIntro="Choose which intern-monitoring alerts you want to receive at your host training establishment."
      securityIntro="Update your password and strengthen account protection for your supervisor credentials."
      metaFields={[
        { label: 'Host Company', key: 'company', fallback: 'Not assigned' },
        { label: 'Position', key: 'position', fallback: 'Company Supervisor' },
        { label: 'Internship Term', key: 'term', fallback: 'AY 2025-2026, Sem 2' },
      ]}
      accountExtraFields={[
        { name: 'company', label: 'Host Company' },
        {
          name: 'sex',
          label: 'Sex',
          type: 'select',
          options: ['Male', 'Female'],
          readOnly: false,
          helperText: 'Required for industry supervisors (not in iEnroll).',
        },
      ]}
      defaultNotifications={{
        attendancePending: true,
        journalReviews: true,
        evaluationDue: true,
      }}
      notificationDefs={[
        {
          key: 'attendancePending',
          title: 'Attendance validation requests',
          description: 'Get notified when assigned interns submit DTR/attendance logs needing your validation.',
        },
        {
          key: 'journalReviews',
          title: 'Journal review reminders',
          description: 'Alerts when weekly journals from your interns are ready for supervisor review.',
        },
        {
          key: 'evaluationDue',
          title: 'Performance evaluation due dates',
          description: 'Reminders when midterm or final HTE evaluations are approaching.',
        },
      ]}
    />
  )
}

export default SupervisorSettings

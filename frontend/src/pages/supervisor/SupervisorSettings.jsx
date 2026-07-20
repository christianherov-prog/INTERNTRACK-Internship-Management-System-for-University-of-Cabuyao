import RoleSettings from '../../components/RoleSettings'
import { CURRENT_TERM } from '../../config/term'

function SupervisorSettings() {
  return (
    <RoleSettings
      bodyClass="supervisor-page"
      subtitleLabel="Supervisor"
      summaryNote="Keep your company contact details updated so interns and the university coordinator can reach you for attendance and evaluations."
      accountIntro="Manage your HTE supervisor profile details used across intern monitoring and evaluations."
      notificationsIntro="Choose which intern-monitoring alerts you want to receive at your host training establishment."
      securityIntro="Update your password and strengthen account protection for your supervisor credentials."
      metaFields={[
        { label: 'Host Company', key: 'company', fallback: 'Not assigned' },
        { label: 'Position', key: 'position', fallback: 'Company Supervisor' },
        { label: 'Internship Term', key: 'term', fallback: CURRENT_TERM },
      ]}
      accountExtraFields={[
        { name: 'company', label: 'Host Company' },
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

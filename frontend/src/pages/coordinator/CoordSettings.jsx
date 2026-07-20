import RoleSettings from '../../components/RoleSettings'
import { CURRENT_TERM } from '../../config/term'

function CoordSettings() {
  return (
    <RoleSettings
      bodyClass="coordinator-page"
      subtitleLabel="Coordinator"
      summaryNote="Keep your office and contact details updated so students, faculty, and supervisors can reach you for placement and compliance follow-ups."
      accountIntro="Manage your primary profile details used across monitoring, document review, and announcements."
      notificationsIntro="Choose which coordinator workflow alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Office / Department', key: 'program', fallback: 'CCS' },
        { label: 'Employee ID', key: 'username', fallback: 'N/A' },
        { label: 'Academic Term', key: 'term', fallback: CURRENT_TERM },
      ]}
      accountExtraFields={[
        { name: 'program', label: 'Office / Department' },
      ]}
      defaultNotifications={{
        pendingDocuments: true,
        placementUpdates: true,
        supervisorApprovals: true,
      }}
      notificationDefs={[
        {
          key: 'pendingDocuments',
          title: 'Pending document review alerts',
          description: 'Get notified when student requirement files are waiting for approval or rejection.',
        },
        {
          key: 'placementUpdates',
          title: 'Placement and deployment updates',
          description: 'Alerts when students need placement action or deployment status changes.',
        },
        {
          key: 'supervisorApprovals',
          title: 'Supervisor registration approvals',
          description: 'Reminders when HTE supervisors self-register and need coordinator approval.',
        },
      ]}
    />
  )
}

export default CoordSettings

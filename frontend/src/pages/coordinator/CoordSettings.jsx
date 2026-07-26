import RoleSettings from '../../components/RoleSettings'

function CoordSettings() {
  return (
    <RoleSettings
      bodyClass="coordinator-page"
      subtitleLabel="Coordinator"
      summaryNote="Your official staff identity is synced from iEnroll. Update password and avatar here; request MISD corrections for identity changes."
      accountIntro="Official coordinator profile from iEnroll. These fields are display-only in INTERNTRACK."
      notificationsIntro="Choose which coordinator workflow alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Employee Number', key: 'employee_number', fallback: '—' },
        { label: 'Office / Department', key: 'program', fallback: 'CCS' },
        { label: 'College', key: 'college', fallback: '—' },
        { label: 'Employment Status', key: 'employment_status', fallback: '—' },
        { label: 'Academic Term', key: 'term', fallback: 'AY 2025-2026, Sem 2' },
      ]}
      accountExtraFields={[
        {
          name: 'program',
          label: 'Office / Department',
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

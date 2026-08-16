import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'

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
        { label: 'Coordinator ID', key: 'faculty_number', fallback: '—' },
        { label: 'Department', key: 'department', fallback: 'College of Computing Studies' },
        { label: 'Designation', key: 'position', fallback: 'CCS Coordinator' },
        { label: 'Employment Status', key: 'employment_status', fallback: 'Regular Faculty' },
        {
          label: 'Program Oversight',
          fallback: 'College Practicum Programs',
          value: (user) => (typeof user?.department === 'object' ? user?.department?.name : user?.department) || 'College Practicum Programs',
        },
        { label: 'Official Email', key: 'email', fallback: '—' },
        { label: 'Contact Number', key: 'contact', fallback: '—' },
        { label: 'Academic Term', key: 'term', fallback: 'AY 2025-2026, 2nd Semester' },
      ]}
      accountExtraFields={[
        {
          name: 'department',
          label: 'Department',
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
    >
      <SignatureUpload />
    </RoleSettings>
  )
}

export default CoordSettings

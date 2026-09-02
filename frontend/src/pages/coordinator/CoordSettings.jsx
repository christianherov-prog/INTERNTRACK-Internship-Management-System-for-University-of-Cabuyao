import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'

function CoordSettings() {
  return (
    <RoleSettings
      bodyClass="coordinator-page"
      subtitleLabel="Coordinator & Faculty Supervisor"
      summaryNote="Your official staff identity is synced from iEnroll. Update password and avatar here; request MISD corrections for identity changes."
      accountIntro="Official coordinator profile from iEnroll. These fields are display-only in INTERNTRACK."
      notificationsIntro="Choose which alerts you want to receive. As Coordinator & Faculty Supervisor, you can manage both coordinator and faculty notification types."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Faculty Number', key: 'faculty_number', fallback: '—' },
        { label: 'Department', key: 'department', fallback: 'College of Computing Studies' },
        { label: 'Position', key: 'position', fallback: 'CCS Coordinator' },
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
        journalSubmissions: true,
        evaluationReminders: true,
        adviseeAlerts: false,
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
        {
          key: 'journalSubmissions',
          title: 'Journal submissions (Faculty)',
          description: 'Notified when your assigned students submit weekly journal entries for review.',
        },
        {
          key: 'evaluationReminders',
          title: 'Evaluation reminders (Faculty)',
          description: 'Reminders to submit midterm and final evaluations for your advisees.',
        },
        {
          key: 'adviseeAlerts',
          title: 'Advisee activity alerts (Faculty)',
          description: 'Alerts on document approval or rejection for students you directly advise.',
        },
      ]}
    >
      <SignatureUpload />
    </RoleSettings>
  )
}

export default CoordSettings

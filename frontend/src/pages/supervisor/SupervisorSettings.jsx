import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'
import SupervisorProfileEditor from '../../components/SupervisorProfileEditor'

function SupervisorSettings() {
  return (
    <RoleSettings
      bodyClass="supervisor-page"
      subtitleLabel="Supervisor"
      identityLocked={true}
      notificationsIntro="Choose which intern-monitoring alerts you want to receive at your host training establishment."
      securityIntro="Account protection and sign-in security for your InternTrack account."
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
        },
      ]}
      defaultNotifications={{
        attendancePending: true,
        evaluationDue: true,
      }}
      notificationDefs={[
        {
          key: 'attendancePending',
          title: 'Attendance validation requests',
          description: 'Get notified when assigned interns submit DTR/attendance logs needing your validation.',
        },
        {
          key: 'evaluationDue',
          title: 'Performance evaluation due dates',
          description: 'Reminders when midterm or final HTE evaluations are approaching.',
        },
      ]}
    >
      <SupervisorProfileEditor />
      <SignatureUpload />
    </RoleSettings>
  )
}

export default SupervisorSettings

import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'

function FacultySettings() {
  return (
    <RoleSettings
      bodyClass="faculty-page"
      subtitleLabel="Faculty"
      notificationsIntro="Choose which faculty advising alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Faculty Number', key: 'faculty_number' },
        { label: 'Department', key: 'department', fallback: 'College of Computing Studies' },
        { label: 'Position', key: 'position', fallback: 'CCS Faculty Supervisor' },
        { label: 'Academic Term', key: 'term', fallback: 'AY 2025-2026, 2nd Semester' },
      ]}
      accountExtraFields={[
        {
          name: 'department',
          label: 'Department',
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
        journalSubmissions: true,
        evaluationReminders: true,
        adviseeAlerts: false,
      }}
      notificationDefs={[
        {
          key: 'journalSubmissions',
          title: 'Advisee journal submissions',
          description: 'Get notified when assigned students submit weekly journals for faculty review.',
        },
        {
          key: 'evaluationReminders',
          title: 'Faculty evaluation reminders',
          description: 'Reminders when faculty evaluations or feedback forms are due for your advisees.',
        },
        {
          key: 'adviseeAlerts',
          title: 'Advisee progress alerts',
          description: 'Alerts when an advisee falls behind on hours, journals, or required submissions.',
        },
      ]}
    >
      <SignatureUpload />
    </RoleSettings>
  )
}

export default FacultySettings

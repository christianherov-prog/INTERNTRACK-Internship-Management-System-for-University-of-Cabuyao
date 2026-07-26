import RoleSettings from '../../components/RoleSettings'

function FacultySettings() {
  return (
    <RoleSettings
      bodyClass="faculty-page"
      subtitleLabel="Faculty"
      summaryNote="Your official faculty identity is synced from iEnroll. Update password and avatar here; request HR/MISD corrections for identity changes."
      accountIntro="Official faculty profile from iEnroll. These fields are display-only in INTERNTRACK."
      notificationsIntro="Choose which faculty advising alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Employee Number', key: 'employee_number', fallback: '—' },
        { label: 'Department', key: 'program', fallback: 'CCS' },
        { label: 'College', key: 'college', fallback: '—' },
        { label: 'Employment Status', key: 'employment_status', fallback: '—' },
        { label: 'Position', key: 'position', fallback: 'Faculty Adviser' },
        { label: 'Academic Term', key: 'term', fallback: 'AY 2025-2026, Sem 2' },
      ]}
      accountExtraFields={[
        {
          name: 'program',
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
    />
  )
}

export default FacultySettings

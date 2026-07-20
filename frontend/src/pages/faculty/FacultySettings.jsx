import RoleSettings from '../../components/RoleSettings'
import { CURRENT_TERM } from '../../config/term'

function FacultySettings() {
  return (
    <RoleSettings
      bodyClass="faculty-page"
      subtitleLabel="Faculty"
      summaryNote="Keep your department and contact details updated so advisees and the practicum coordinator can reach you for journal and evaluation follow-ups."
      accountIntro="Manage your faculty adviser profile details used across journal review and student evaluations."
      notificationsIntro="Choose which faculty advising alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your school credentials."
      metaFields={[
        { label: 'Department', key: 'program', fallback: 'CCS' },
        { label: 'Position', key: 'position', fallback: 'Faculty Adviser' },
        { label: 'Academic Term', key: 'term', fallback: CURRENT_TERM },
      ]}
      accountExtraFields={[
        { name: 'program', label: 'Department' },
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

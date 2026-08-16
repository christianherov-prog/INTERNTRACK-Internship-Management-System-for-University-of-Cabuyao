import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'

function MisdSettings() {
  return (
    <RoleSettings
      bodyClass="admin-page"
      subtitleLabel="MISD Admin"
      summaryNote="Your official MISD admin identity is synced from iEnroll. Update password and avatar here."
      accountIntro="Official MISD Administrator profile from iEnroll. These fields are display-only in INTERNTRACK."
      notificationsIntro="Choose which system-administration alerts you want to receive."
      securityIntro="Update your password and protect MISD admin credentials."
      metaFields={[
        { label: 'Administrator ID', key: 'faculty_number', fallback: '—' },
        { label: 'Department', key: 'department', fallback: 'Management Information Systems Department' },
        { label: 'Designation', key: 'position', fallback: 'MISD Administrator' },
        { label: 'Employment Status', key: 'employment_status', fallback: 'Regular' },
        { label: 'System Access Level', fallback: 'Superadmin (Full Control)', value: () => 'Superadmin (Full Control)' },
        { label: 'Official Email', key: 'email', fallback: '—' },
        { label: 'Contact Number', key: 'contact', fallback: '—' },
        { label: 'System Version', fallback: 'INTERNTRACK v1.0', value: () => 'INTERNTRACK v1.0' },
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
        syncFailures: true,
        staffChanges: true,
        mappingGaps: true,
      }}
      notificationDefs={[
        {
          key: 'syncFailures',
          title: 'MISD sync failures',
          description: 'Alerts when student/faculty sync or provisioning against iEnroll fails.',
        },
        {
          key: 'staffChanges',
          title: 'Staff assignment changes',
          description: 'Notifications when directors or coordinators are assigned, revoked, or deactivated.',
        },
        {
          key: 'mappingGaps',
          title: 'Unmapped section alerts',
          description: 'Warn when enrolled students have sections without faculty mappings.',
        },
      ]}
    >
      <SignatureUpload />
    </RoleSettings>
  )
}

export default MisdSettings

import RoleSettings from '../../components/RoleSettings'

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
        { label: 'Employee Number', key: 'employee_number', fallback: '—' },
        { label: 'Office / Unit', key: 'program', fallback: 'MISD' },
        { label: 'Employment Status', key: 'employment_status', fallback: '—' },
        { label: 'Position', key: 'position', fallback: 'MISD Administrator' },
        { label: 'Academic Term', key: 'term', fallback: 'AY 2025-2026, Sem 2' },
      ]}
      accountExtraFields={[
        {
          name: 'program',
          label: 'Office / Unit',
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
    />
  )
}

export default MisdSettings

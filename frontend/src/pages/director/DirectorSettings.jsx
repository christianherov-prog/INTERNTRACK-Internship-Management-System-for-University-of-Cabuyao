import RoleSettings from '../../components/RoleSettings'

function DirectorSettings() {
  return (
    <RoleSettings
      bodyClass="director-page"
      subtitleLabel="Director"
      summaryNote="Your official director identity is synced from iEnroll. Update password and avatar here; request MISD corrections for identity changes."
      accountIntro="Official PALD Director profile from iEnroll. These fields are display-only in INTERNTRACK."
      notificationsIntro="Choose which program-oversight alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your director credentials."
      metaFields={[
        { label: 'Employee Number', key: 'employee_number', fallback: '—' },
        { label: 'Office / Unit', key: 'program', fallback: 'PALD' },
        { label: 'Employment Status', key: 'employment_status', fallback: '—' },
        { label: 'Position', key: 'position', fallback: 'PALD Director' },
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
        moaExpiry: true,
        companyUpdates: true,
        programReports: false,
        absorptionUpdates: true,
      }}
      notificationDefs={[
        {
          key: 'moaExpiry',
          title: 'MOA expiry and renewal alerts',
          description: 'Get notified when host company MOAs are nearing expiry or need renewal action.',
        },
        {
          key: 'companyUpdates',
          title: 'Company / HTE status updates',
          description: 'Alerts when company records, slots, or MOA status change in the system.',
        },
        {
          key: 'programReports',
          title: 'Program report digests',
          description: 'Optional digests summarizing internship program analytics and compliance trends.',
        },
        {
          key: 'absorptionUpdates',
          title: 'Absorption / hire confirmations',
          description: 'Alerts when internships complete or students declare they were hired and need Director confirmation.',
        },
      ]}
    />
  )
}

export default DirectorSettings

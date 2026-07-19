import RoleSettings from '../../components/RoleSettings'

function DirectorSettings() {
  return (
    <RoleSettings
      bodyClass="director-page"
      subtitleLabel="Director"
      summaryNote="Keep your office contact details updated for MOA coordination and program-level reporting."
      accountIntro="Manage your PALD Director profile details used across company MOA monitoring and analytics."
      notificationsIntro="Choose which program-oversight alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your director credentials."
      metaFields={[
        { label: 'Office / Unit', key: 'program', fallback: 'PALD' },
        { label: 'Position', key: 'position', fallback: 'PALD Director' },
        { label: 'Academic Term', key: 'term', fallback: 'AY 2024-2025, Sem 2' },
      ]}
      accountExtraFields={[
        { name: 'program', label: 'Office / Unit' },
      ]}
      defaultNotifications={{
        moaExpiry: true,
        companyUpdates: true,
        programReports: false,
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
      ]}
    />
  )
}

export default DirectorSettings

import RoleSettings from '../../components/RoleSettings'
import SignatureUpload from '../../components/SignatureUpload'

function DirectorSettings() {
  return (
    <RoleSettings
      bodyClass="director-page"
      subtitleLabel="Director"
      notificationsIntro="Choose which program-oversight alerts you want to receive."
      securityIntro="Update your password and strengthen account protection for your director credentials."
      metaFields={[
        { label: 'Director ID', key: 'faculty_number', fallback: '—' },
        { label: 'Office / Department', key: 'department', fallback: 'Placement, Alumni, & Linkages Department' },
        { label: 'Designation', key: 'position', fallback: 'PALD Director' },
        { label: 'Employment Status', key: 'employment_status', fallback: 'Regular' },
        { label: 'Oversight Scope', fallback: 'University-Wide Linkages & OJT', value: () => 'University-Wide Linkages & OJT' },
        { label: 'Official Email', key: 'email', fallback: '—' },
        { label: 'Contact Number', key: 'contact', fallback: '—' },
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
    >
      <SignatureUpload />
    </RoleSettings>
  )
}

export default DirectorSettings

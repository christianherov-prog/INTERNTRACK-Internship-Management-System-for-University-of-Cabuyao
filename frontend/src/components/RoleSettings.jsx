import { useState, useRef, useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'
import Layout from './Layout'
import ImageCropModal from './ImageCropModal'
import api from '../services/api'
import { getAvatarSrc } from '../utils/avatar'

/**
 * Shared Settings UI for all roles (Student, Coordinator, Supervisor, Faculty, Director, Admin).
 * iEnroll roles: identity fields are display-only; password/avatar/notifications remain editable.
 * Supervisors: full profile edit (not in iEnroll).
 */
function RoleSettings({
  bodyClass,
  subtitleLabel,
  identityLocked = true,
  notificationsIntro,
  securityIntro,
  metaFields = [],
  accountExtraFields = [],
  notificationDefs = [],
  defaultNotifications = {},
  children,
}) {
  const { user, updateUserLocal, refreshUser } = useAuth()
  const toast = useToast()
  const fileInputRef = useRef(null)
  const storageKey = user?.id
    ? `interntrack_notifications_${user.id}`
    : 'interntrack_notifications_guest'


  const [avatarBroken, setAvatarBroken] = useState(false)

  useEffect(() => {
    setAvatarBroken(false)
  }, [user?.avatarUrl, user?.avatarVersion])

  useEffect(() => {
    if (!user?.id) return undefined
    refreshUser()
  }, [user?.id]) // eslint-disable-line react-hooks/exhaustive-deps

  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    program: user?.program || '',
    course_description: user?.course_description || '',
    contact: user?.contact || '',
    company: user?.company || '',
    position: user?.position || '',
    sex: user?.sex || '',
  })

  const [passwords, setPasswords] = useState({
    current_password: '',
    new_password: '',
  })
  const [passwordLoading, setPasswordLoading] = useState(false)
  const [passwordMessage, setPasswordMessage] = useState({ type: '', text: '' })
  const [profileSaving, setProfileSaving] = useState(false)

  const [cropSrc, setCropSrc] = useState(null)
  const [avatarUploading, setAvatarUploading] = useState(false)
  const [avatarError, setAvatarError] = useState(null)

  const [notifications, setNotifications] = useState(() => {
    if (user?.notificationPreferences && typeof user.notificationPreferences === 'object') {
      return { ...defaultNotifications, ...user.notificationPreferences }
    }
    try {
      const saved = localStorage.getItem(storageKey)
      if (saved) return { ...defaultNotifications, ...JSON.parse(saved) }
    } catch { /* ignore */ }
    return { ...defaultNotifications }
  })
  const [notifSaving, setNotifSaving] = useState(false)
  const [notifMessage, setNotifMessage] = useState({ type: '', text: '' })

  useEffect(() => {
    setFormData({
      name: user?.name || '',
      email: user?.email || '',
      program: user?.program || '',
      course_description: user?.course_description || '',
      department: user?.department || '',
      contact: user?.contact || '',
      company: user?.company || '',
      position: user?.position || '',
      sex: user?.sex || '',
      faculty_number: user?.faculty_number || '',
      student_number: user?.student_number || '',
    })
  }, [
    user?.id,
    user?.name,
    user?.email,
    user?.program,
    user?.course_description,
    user?.contact,
    user?.company,
    user?.position,
    user?.sex,
    user?.department,
    user?.faculty_number,
    user?.student_number,
  ])

  useEffect(() => {
    if (user?.notificationPreferences && typeof user.notificationPreferences === 'object') {
      const merged = { ...defaultNotifications, ...user.notificationPreferences }
      setNotifications(merged)
      try {
        localStorage.setItem(storageKey, JSON.stringify(merged))
      } catch { /* ignore */ }
    }
  }, [user?.id, user?.notificationPreferences]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(notifications))
    } catch { /* ignore */ }
  }, [notifications, storageKey])

  const securityStatus = user?.must_change_password
    ? 'Password change required'
    : 'Password protected'

  const filledAccountFields = [
    formData.name,
    formData.email,
    formData.contact,
    ...accountExtraFields.map((f) => formData[f.name]),
  ].filter((v) => String(v || '').trim().length > 0).length
  const totalAccountFields = 2 + accountExtraFields.length + 1
  const profileCompletion = Math.round((filledAccountFields / Math.max(totalAccountFields, 1)) * 100)
  const notifEnabledCount = Object.values(notifications).filter(Boolean).length

  const [directPasswordLoading, setDirectPasswordLoading] = useState(false)

  const handleFormChange = (e) => {
    if (identityLocked && e.target.name !== 'sex') return
    setFormData({ ...formData, [e.target.name]: e.target.value })
  }

  const handlePasswordChange = (e) => {
    setPasswords({ ...passwords, [e.target.name]: e.target.value })
  }

  const handleDirectPasswordSubmit = async (e) => {
    e.preventDefault()
    setDirectPasswordLoading(true)
    setPasswordMessage({ type: '', text: '' })
    try {
      const { data } = await api.post('/auth/change-password', passwords)
      setPasswordMessage({ type: 'success', text: data.message || 'Password updated successfully.' })
      setPasswords({ current_password: '', new_password: '' })
      updateUserLocal(data.user)
    } catch (err) {
      setPasswordMessage({ type: 'error', text: err.response?.data?.message || 'Failed to update password.' })
    } finally {
      setDirectPasswordLoading(false)
    }
  }

  const handleNotificationChange = async (key) => {
    const previous = notifications
    const next = { ...notifications, [key]: !notifications[key] }
    setNotifications(next)
    setNotifSaving(true)
    setNotifMessage({ type: '', text: '' })
    try {
      const res = await api.put('/auth/notification-preferences', { preferences: next })
      const prefs = res.data?.preferences || next
      setNotifications({ ...defaultNotifications, ...prefs })
      if (res.data?.user) {
        updateUserLocal(res.data.user)
      } else {
        updateUserLocal({ notificationPreferences: prefs })
      }
      try {
        localStorage.setItem(storageKey, JSON.stringify({ ...defaultNotifications, ...prefs }))
      } catch { /* ignore */ }
      setNotifMessage({ type: 'success', text: 'Notification preferences saved.' })
    } catch (err) {
      setNotifications(previous)
      setNotifMessage({
        type: 'error',
        text: err.response?.data?.message || 'Failed to save notification preferences.',
      })
    } finally {
      setNotifSaving(false)
    }
  }

  const handleSaveProfile = async () => {
    const updates = {
      name: formData.name,
      email: formData.email,
      contact: formData.contact,
      position: formData.position || undefined,
      sex: formData.sex || undefined,
    }

    setProfileSaving(true)
    try {
      const res = await api.put('/auth/profile', updates)
      if (res.data?.user) {
        updateUserLocal(res.data.user)
      }
      await refreshUser()
      toast.success('Profile saved successfully')
    } catch (err) {
      toast.error(
        err.response?.data?.message || 'Failed to save profile. Changes were not persisted.',
      )
    } finally {
      setProfileSaving(false)
    }
  }

  const handleCancelProfile = () => {
    setFormData({
      name: user?.name || '',
      email: user?.email || '',
      program: user?.program || '',
      course_description: user?.course_description || '',
      department: user?.department || '',
      contact: user?.contact || '',
      company: user?.company || '',
      position: user?.position || '',
      sex: user?.sex || '',
      faculty_number: user?.faculty_number || '',
      student_number: user?.student_number || '',
    })
  }

  const handleRequestPasswordChange = async () => {
    setPasswordLoading(true)
    setPasswordMessage({ type: '', text: '' })
    try {
      const { data } = await api.post('/auth/request-password-change')
      setPasswordMessage({ type: 'success', text: data.message || 'Password confirmation email sent.' })
    } catch (err) {
      setPasswordMessage({ type: 'error', text: err.response?.data?.message || 'Failed to send password change email.' })
    } finally {
      setPasswordLoading(false)
    }
  }

  const handleAvatarClick = () => fileInputRef.current?.click()

  const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (file) {
      if (!file.type.startsWith('image/')) {
        toast.error('Please select a valid image file.')
        e.target.value = ''
        return
      }
      const reader = new FileReader()
      reader.onloadend = () => setCropSrc(reader.result)
      reader.readAsDataURL(file)
    }
    e.target.value = ''
  }

  const handleCropSave = async (croppedDataUrl) => {
    setCropSrc(null)
    setAvatarUploading(true)
    setAvatarError(null)
    try {
      const blob = await (await fetch(croppedDataUrl)).blob()
      const file = new File([blob], 'avatar.png', { type: blob.type || 'image/png' })
      const fd = new FormData()
      fd.append('avatar', file)
      const { data } = await api.post('/auth/avatar', fd)
      updateUserLocal({
        ...data.user,
        avatarVersion: Date.now(),
      })
      setAvatarBroken(false)
    } catch (err) {
      const fieldErr = err.response?.data?.errors?.avatar?.[0]
      setAvatarError(fieldErr || err.response?.data?.message || 'Failed to upload profile photo. Please try again.')
    } finally {
      setAvatarUploading(false)
    }
  }

  const resolveMetaValue = (field) => {
    if (typeof field.value === 'function') {
      const computed = field.value(user)
      if (computed != null && String(computed).trim() !== '' && String(computed) !== 'N/A') {
        return computed
      }
      return field.fallback || 'N/A'
    }
    if (field.key) {
      const raw = user?.[field.key]
      if (raw != null) {
        if (typeof raw === 'object') {
          return raw.name || raw.code || field.fallback || 'N/A'
        }
        if (String(raw).trim() !== '' && String(raw) !== 'N/A') {
          return raw
        }
      }
      return field.fallback || 'N/A'
    }
    return field.fallback || 'N/A'
  }

  const formatLastUpdate = (iso) => {
    if (!iso) return '—'
    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return '—'
    const diffMs = Date.now() - date.getTime()
    const mins = Math.floor(diffMs / 60000)
    if (mins < 1) return 'Just now'
    if (mins < 60) return `${mins}m ago`
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d ago`
    return date.toLocaleDateString()
  }

  const resolveRoleBadgeText = (u) => {
    switch (u?.role) {
      case 'student': return 'Active Student'
      case 'faculty': return 'Active Faculty'
      case 'coordinator': return 'Active Coordinator'
      case 'supervisor': return 'Active Supervisor'
      case 'director': return 'Active Director'
      case 'admin': return 'Active Administrator'
      default: return `Active ${u?.roleLabel || 'User'}`
    }
  }

  const scrollToNotifications = () => {
    const el = document.getElementById('settings-notifications-section')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }

  const scrollToSecurity = () => {
    const el = document.getElementById('settings-security-section')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }

  return (
    <Layout title="Settings" subtitle={subtitleLabel} icon="fa-cog" bodyClass={bodyClass}>
      {user?.must_change_password && (
        <div className="alert alert-warning border mb-3" role="alert">
          <i className="fa fa-key me-2"></i>
          <strong>Password change required.</strong> An administrator reset your password to the default.
          Update it in the Security section below before continuing.
        </div>
      )}

      <div className="row g-4 settings-layout pt-1 mb-2">

        {/* Left Column: Profile Summary */}
        <div className="col-xl-4 col-lg-5 d-flex">
          <div className="card profile-summary-card w-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white d-flex flex-column h-100">

            {/* Accent Header Banner */}
            <div
              className="profile-header-banner w-100"
              style={{ height: '95px', borderBottom: '1px solid rgba(0,0,0,0.05)', flexShrink: 0 }}
            ></div>

            {/* Content Body */}
            <div className="card-body d-flex flex-column align-items-center justify-content-between flex-grow-1 px-4 pt-0 pb-4 position-relative text-center">
              <div className="d-flex flex-column align-items-center w-100" style={{ marginTop: '-62px' }}>

                {/* Profile Photo Overlapping Boundary */}
                <button
                  type="button"
                  className={`settings-avatar-upload position-relative p-0 bg-white rounded-circle mb-3 ${avatarUploading ? 'is-uploading' : ''}`}
                  style={{
                    width: '124px',
                    height: '124px',
                    border: '4px solid #fff',
                    boxShadow: '0 8px 24px rgba(10, 92, 46, 0.12)',
                    flexShrink: 0,
                  }}
                  onClick={handleAvatarClick}
                  title="Click to change photo"
                  disabled={avatarUploading}
                >
                  {getAvatarSrc(user) && !avatarBroken ? (
                    <img
                      src={getAvatarSrc(user)}
                      alt={user?.name || 'Profile photo'}
                      className="settings-avatar-img"
                      onError={() => setAvatarBroken(true)}
                    />
                  ) : (
                    <div
                      className="settings-avatar-initials d-flex flex-column align-items-center justify-content-center w-100 h-100"
                      style={{
                        background: 'linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%)',
                        color: '#64748b',
                      }}
                    >
                      <i className="fa-solid fa-user opacity-75" style={{ fontSize: '3rem' }}></i>
                    </div>
                  )}

                  {/* Upload Overlay */}
                  <div className="settings-avatar-overlay" aria-hidden="true">
                    <i className={`fa ${avatarUploading ? 'fa-spinner fa-spin' : 'fa-camera'} text-white mb-1`} style={{ fontSize: '1.25rem' }}></i>
                    <span className="text-white fw-bold" style={{ fontSize: '0.7rem', letterSpacing: '0.4px' }}>
                      {avatarUploading ? 'UPLOADING…' : 'CHANGE PHOTO'}
                    </span>
                  </div>
                </button>

                {/* User Name */}
                <h3 className="fw-bold text-dark mb-1.5 text-truncate w-100" style={{ fontSize: '1.35rem', lineHeight: '1.25' }}>
                  {user?.name || 'User'}
                </h3>

                {/* Subtle Status Text with clear dot spacing */}
                <div className="d-inline-flex align-items-center text-success fw-semibold mb-4" style={{ fontSize: '0.82rem', gap: '6px' }}>
                  <span className="rounded-circle bg-success flex-shrink-0" style={{ width: '6px', height: '6px' }}></span>
                  <span>{resolveRoleBadgeText(user)}</span>
                </div>

                {/* Divider + Unique Stat Block (No duplicate fields from Profile Details) */}
                <div className="w-100 pt-4 border-top d-flex flex-column justify-content-center flex-grow-1" style={{ borderColor: '#f1f5f9' }}>

                  {/* Real Practicum Progress Indicator for Students */}
                  {user?.role === 'student' && (
                    <div className="profile-overview-box text-start">
                      <div className="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span className="text-muted fw-bold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                          Practicum Progress
                        </span>
                        <span className="text-success fw-bold flex-shrink-0" style={{ fontSize: '0.85rem' }}>
                          {user?.hours_progress ?? 0}% Complete
                        </span>
                      </div>
                      <div className="progress mb-3" style={{ height: '8px', backgroundColor: '#e2e8f0', borderRadius: '999px' }}>
                        <div
                          className="progress-bar bg-success rounded-pill"
                          role="progressbar"
                          style={{
                            width: `${Math.min(100, Math.max(0, user?.hours_progress ?? 0))}%`,
                            transition: 'width 0.4s ease',
                          }}
                          aria-valuenow={user?.hours_progress ?? 0}
                          aria-valuemin="0"
                          aria-valuemax="100"
                        ></div>
                      </div>
                      <div className="profile-overview-footer d-flex justify-content-between align-items-center flex-wrap gap-2" style={{ borderTop: '1px solid #eef2f6', paddingTop: '15px', marginTop: '4px', fontSize: '0.78rem' }}>
                        <span className="text-muted text-truncate">
                          Rendered: <strong className="text-dark">{user?.hours_rendered ?? 0} hrs</strong>
                        </span>
                        <span className="text-muted text-truncate">
                          Target: <strong className="text-dark">{user?.target_hours ?? 500} hrs</strong>
                        </span>
                      </div>
                    </div>
                  )}

                  {/* Role Overview Box for Non-Student Roles */}
                  {user?.role !== 'student' && (
                    <div className="profile-overview-box text-start">
                      <div className="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span className="text-muted fw-bold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                          {user?.role === 'faculty' ? 'Advising Scope'
                            : user?.role === 'coordinator' ? 'Program Oversight'
                              : user?.role === 'supervisor' ? 'Supervision Site'
                                : user?.role === 'director' ? 'Oversight Scope'
                                  : 'System Authority'}
                        </span>
                        <span className="badge bg-success-subtle text-success fw-bold px-2.5 py-1 rounded-pill flex-shrink-0" style={{ fontSize: '0.72rem' }}>
                          {user?.role === 'admin' ? 'Superadmin'
                            : user?.role === 'director' ? 'University-Wide'
                              : 'Active Term'}
                        </span>
                      </div>
                      <div className="text-dark fw-bold mb-3" style={{ fontSize: '0.98rem', lineHeight: 1.35, wordBreak: 'break-word' }}>
                        {user?.role === 'faculty' ? (user?.term || 'AY 2025-2026, 2nd Semester')
                          : user?.role === 'coordinator' ? (typeof user?.department === 'object' ? user?.department?.name : (user?.department || 'College of Computing Studies'))
                            : user?.role === 'supervisor' ? (user?.company || 'Host Training Establishment')
                              : user?.role === 'director' ? 'Placement, Alumni, & Linkages'
                                : 'Management Information Systems'}
                      </div>
                      <div className="profile-overview-footer text-muted d-flex justify-content-between align-items-center flex-wrap gap-2" style={{ borderTop: '1px solid #eef2f6', paddingTop: '15px', marginTop: '4px', fontSize: '0.78rem' }}>
                        <span className="text-truncate">Status: <strong className="text-dark">{user?.employment_status || 'Verified Account'}</strong></span>
                        <span className="text-truncate">{user?.term || 'AY 2025-2026, Sem 2'}</span>
                      </div>
                    </div>
                  )}
                </div>

                <input
                  type="file"
                  ref={fileInputRef}
                  onChange={handleFileChange}
                  accept="image/*"
                  className="d-none"
                />

                {avatarError && (
                  <div className="alert alert-danger mt-2.5 mb-0 py-1.5 px-3 w-100 text-center rounded-3 border-0 shadow-sm" style={{ fontSize: '0.82rem' }}>
                    <i className="fa fa-circle-exclamation me-1"></i> {avatarError}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Right Column: Profile Details */}
        <div className="col-xl-8 col-lg-7 d-flex">
          <div className="card w-100 border-0 shadow-sm rounded-4 h-100 d-flex flex-column bg-white">

            {/* Clean Header: Icon + Title Only */}
            <div className="card-header bg-transparent border-0 px-4 pt-4 pb-2">
              <h5 className="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style={{ fontSize: '1.15rem' }}>
                <i className="fa-regular fa-address-card text-success"></i> Profile Details
              </h5>
            </div>

            {/* Content Grid with Generous Row & Column Spacing */}
            <div className="card-body p-4 pt-2 d-flex flex-column justify-content-between flex-grow-1">
              <div className="row profile-details-grid mb-3">

                {/* 2-Column Detail Boxes (No icons per box) */}
                {metaFields.map((field) => (
                  <div key={field.label} className="col-md-6">
                    <div className="profile-detail-box h-100 d-flex flex-column justify-content-center">
                      <span className="d-block text-muted fw-semibold text-uppercase" style={{ fontSize: '0.72rem', letterSpacing: '0.06em', marginBottom: '4px' }}>
                        {field.label}
                      </span>
                      <strong className="d-block text-dark text-truncate" style={{ fontSize: '0.98rem', fontWeight: '700', lineHeight: 1.3 }}>
                        {resolveMetaValue(field)}
                      </strong>
                    </div>
                  </div>
                ))}

              </div>

              {/* Bottom 3 Simplified Status Cards with Generous Spacing below divider */}
              <div className="pt-4 mt-3 border-top" style={{ borderColor: '#f1f5f9' }}>
                <div className="row g-3">

                  {/* Notifications Stat Card */}
                  <div className="col-sm-4">
                    <div
                      className="profile-stat-card clickable-stat h-100 d-flex align-items-center gap-3"
                      onClick={scrollToNotifications}
                      role="button"
                      tabIndex={0}
                      title="Click to manage notifications"
                    >
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                        style={{ width: '42px', height: '42px', background: '#eff6ff', color: '#2563eb' }}
                      >
                        <i className="fa-regular fa-bell" style={{ fontSize: '1.15rem' }}></i>
                      </div>
                      <div className="min-w-0">
                        <span className="fw-bolder text-dark d-block" style={{ fontSize: '1.35rem', lineHeight: 1 }}>
                          {notifEnabledCount}
                        </span>
                        <span className="text-muted fw-medium d-block text-truncate" style={{ fontSize: '0.75rem', marginTop: '3px' }}>
                          Notifications
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Security Stat Card */}
                  <div className="col-sm-4">
                    <div
                      className="profile-stat-card clickable-stat h-100 d-flex align-items-center gap-3"
                      onClick={scrollToSecurity}
                      role="button"
                      tabIndex={0}
                      title="Click to manage security settings"
                    >
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                        style={{
                          width: '42px',
                          height: '42px',
                          background: user?.must_change_password ? '#fef3c7' : '#ecfdf5',
                          color: user?.must_change_password ? '#d97706' : '#157938',
                        }}
                      >
                        <i className={`fa-solid ${user?.must_change_password ? 'fa-triangle-exclamation' : 'fa-shield-halved'}`} style={{ fontSize: '1.15rem' }}></i>
                      </div>
                      <div className="min-w-0">
                        <span className="fw-bolder text-dark d-block text-truncate" style={{ fontSize: '1.05rem', lineHeight: 1.1 }}>
                          {user?.must_change_password ? 'Action Needed' : 'Protected'}
                        </span>
                        <span className="text-muted fw-medium d-block text-truncate" style={{ fontSize: '0.75rem', marginTop: '3px' }}>
                          Security
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Last Update Stat Card */}
                  <div className="col-sm-4">
                    <div
                      className="profile-stat-card h-100 d-flex align-items-center gap-3"
                      title={user?.lastLoginAt ? `Exact time: ${new Date(user.lastLoginAt).toLocaleString()}` : 'Recent Activity'}
                    >
                      <div
                        className="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                        style={{ width: '42px', height: '42px', background: '#f1f5f9', color: '#64748b' }}
                      >
                        <i className="fa-regular fa-clock" style={{ fontSize: '1.15rem' }}></i>
                      </div>
                      <div className="min-w-0">
                        <span className="fw-bolder text-dark d-block text-truncate" style={{ fontSize: '1.05rem', lineHeight: 1.1 }}>
                          {formatLastUpdate(user?.lastLoginAt)}
                        </span>
                        <span className="text-muted fw-medium d-block text-truncate" style={{ fontSize: '0.75rem', marginTop: '3px' }}>
                          Last Update
                        </span>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

            </div>
          </div>
        </div>

        {/* --- NOTIFICATIONS & SECURITY SECTIONS BELOW --- */}

        <div id="settings-notifications-section" className="col-xl-7 col-lg-6 d-flex">
          <div className="content-card settings-section-card settings-notification-card w-100">
            <div className="content-card-header">
              <i className="fa fa-bell"></i>
              <h6>Notifications</h6>
            </div>
            <div className="settings-section-intro">{notificationsIntro}</div>
            {notifMessage.text && (
              <div className={`alert alert-${notifMessage.type === 'error' ? 'danger' : 'success'} py-2 px-3 mb-3`} style={{ fontSize: '0.85rem' }}>
                {notifMessage.text}
              </div>
            )}
            <div className="settings-toggle-list">
              {notificationDefs.map((item) => (
                <div className="settings-toggle-row settings-toggle-card" key={item.key}>
                  <div>
                    <strong>{item.title}</strong>
                    <p className="mb-0 text-muted" style={{ fontSize: '0.8rem' }}>{item.description}</p>
                  </div>
                  <div className="form-check form-switch">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      checked={Boolean(notifications[item.key])}
                      disabled={notifSaving}
                      onChange={() => handleNotificationChange(item.key)}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div id="settings-security-section" className="col-xl-5 col-lg-6 d-flex">
          <div className="content-card settings-section-card settings-security-card w-100">
            <div className="content-card-header">
              <i className="fa fa-shield-halved"></i>
              <h6>Security</h6>
            </div>
            <div className="settings-section-intro">{securityIntro}</div>

            {passwordMessage.text && (
              <div className={`alert alert-${passwordMessage.type === 'error' ? 'danger' : 'success'} py-2 px-3 mb-3`} style={{ fontSize: '0.85rem' }}>
                {passwordMessage.text}
              </div>
            )}

            {user?.must_change_password ? (
              <form onSubmit={handleDirectPasswordSubmit} className="p-3 bg-light rounded border mb-4">
                <div className="d-flex align-items-center gap-2 mb-3">
                  <i className="fa fa-lock text-warning"></i>
                  <span className="fw-semibold text-dark" style={{ fontSize: '0.88rem' }}>Set New Password</span>
                </div>
                <div className="mb-2">
                  <label className="form-label" style={{ fontSize: '0.82rem' }}>Current Password (Default)</label>
                  <input type="password" name="current_password" value={passwords.current_password} onChange={handlePasswordChange} className="form-control form-control-sm" required />
                </div>
                <div className="mb-3">
                  <label className="form-label" style={{ fontSize: '0.82rem' }}>New Password</label>
                  <input type="password" name="new_password" value={passwords.new_password} onChange={handlePasswordChange} className="form-control form-control-sm" minLength={6} required />
                </div>
                <button type="submit" className="btn btn-primary btn-sm w-100" disabled={directPasswordLoading}>
                  {directPasswordLoading ? 'Updating...' : 'Update Password'}
                </button>
              </form>
            ) : (
              <>
                <div className="p-3 bg-light rounded border mb-4">
                  <div className="d-flex align-items-center gap-2 mb-2">
                    <i className="fa fa-envelope text-success"></i>
                    <span className="fw-semibold text-dark" style={{ fontSize: '0.88rem' }}>Email Confirmation Flow</span>
                  </div>
                  <p className="text-muted mb-0" style={{ fontSize: '0.82rem', lineHeight: 1.5 }}>
                    To keep your account secure, password changes require email verification. Click the button below to receive a confirmation link sent to your registered email address (valid for 60 minutes).
                  </p>
                </div>

                <div className="settings-actions-row">
                  <button type="button" className="btn-green d-inline-flex align-items-center gap-2" onClick={handleRequestPasswordChange} disabled={passwordLoading}>
                    <i className="fa fa-paper-plane"></i>
                    {passwordLoading ? 'Sending Email...' : 'Send Password Change Email'}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>

        {/* Children (e.g., SignatureUpload for StudentSettings) */}
        {children}
      </div >

      {
        cropSrc && (
          <ImageCropModal
            imageSrc={cropSrc}
            onCancel={() => setCropSrc(null)}
            onSave={handleCropSave}
          />
        )
      }
    </Layout >
  )
}

export default RoleSettings
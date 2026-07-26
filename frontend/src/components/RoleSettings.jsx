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
  summaryNote,
  accountIntro,
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

  const profileEditable = Boolean(user?.profile_editable)
  const identityLocked = !profileEditable

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
  const [profileSaving, setProfileSaving] = useState(false)

  const [cropSrc, setCropSrc] = useState(null)
  const [avatarUploading, setAvatarUploading] = useState(false)
  const [avatarError, setAvatarError] = useState(null)
  const [avatarBroken, setAvatarBroken] = useState(false)

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

  useEffect(() => {
    setFormData({
      name: user?.name || '',
      email: user?.email || '',
      program: user?.program || '',
      contact: user?.contact || '',
      company: user?.company || '',
      position: user?.position || '',
      sex: user?.sex || '',
    })
  }, [
    user?.id,
    user?.name,
    user?.email,
    user?.program,
    user?.contact,
    user?.company,
    user?.position,
    user?.sex,
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

  const handleFormChange = (e) => {
    if (identityLocked && e.target.name !== 'sex') return
    setFormData({ ...formData, [e.target.name]: e.target.value })
  }

  const handlePasswordChange = (e) => {
    setPasswords({ ...passwords, [e.target.name]: e.target.value })
  }

  const handleNotificationChange = async (key) => {
    const previous = notifications
    const next = { ...notifications, [key]: !notifications[key] }
    setNotifications(next)
    setNotifSaving(true)
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
      toast.success('Notification preferences saved.')
    } catch (err) {
      setNotifications(previous)
      toast.error(err.response?.data?.message || 'Failed to save notification preferences.')
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
      contact: user?.contact || '',
      company: user?.company || '',
      position: user?.position || '',
      sex: user?.sex || '',
    })
  }

  const handleRequestPasswordChange = async () => {
    setPasswordLoading(true)
    try {
      const { data } = await api.post('/auth/request-password-change')
      toast.success(data.message || 'Password confirmation email sent.')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to send password change email.')
    } finally {
      setPasswordLoading(false)
    }
  }

  const handleAvatarClick = () => fileInputRef.current?.click()

  const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (file) {
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
      // Explicit File+MIME so Laravel's image/mimes rules always see a PNG upload.
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
      if (raw != null && String(raw).trim() !== '' && String(raw) !== 'N/A') {
        return raw
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

  const fieldReadOnly = (field) => identityLocked || Boolean(field.readOnly)

  return (
    <Layout title="Settings" subtitle={subtitleLabel} icon="fa-cog" bodyClass={bodyClass}>
      {user?.must_change_password && (
        <div className="alert alert-warning border mb-3" role="alert">
          <i className="fa fa-key me-2"></i>
          <strong>Password change required.</strong> An administrator reset your password to the default.
          Update it in the Security section below before continuing.
        </div>
      )}
      <div className="row g-4 settings-layout">

        <div className="col-xl-4 col-lg-5 d-flex">
          <div className="content-card settings-summary-card w-100">
            <div className="content-card-header">
              <i className="fa fa-user-gear"></i>
              <h6>Profile Summary</h6>
            </div>
            <div className="settings-profile-top">
              <button
                type="button"
                className="settings-avatar settings-avatar-lg settings-avatar-upload"
                onClick={handleAvatarClick}
                title="Click to change photo"
                disabled={avatarUploading}
              >
                {getAvatarSrc(user) && !avatarBroken ? (
                  <img
                    src={getAvatarSrc(user)}
                    alt=""
                    className="settings-avatar-img"
                    onError={() => setAvatarBroken(true)}
                  />
                ) : (
                  <span className="settings-avatar-initials">{user?.avatar || 'U'}</span>
                )}
                <span className="settings-avatar-overlay">
                  <i className={`fa ${avatarUploading ? 'fa-spinner fa-spin' : 'fa-camera'}`}></i>
                  <span>{avatarUploading ? 'Uploading…' : 'Change photo'}</span>
                </span>
              </button>
              <input
                type="file"
                ref={fileInputRef}
                onChange={handleFileChange}
                accept="image/*"
                style={{ display: 'none' }}
              />
              <div className="settings-identity">
                <h5>{user?.name || 'User'}</h5>
                <p>{user?.subtitle || user?.username || ''}</p>
                <span className="settings-role-pill">{user?.roleLabel || 'Account'}</span>
                {avatarError && (
                  <p className="text-danger mt-2 mb-0" style={{ fontSize: '0.78rem' }}>{avatarError}</p>
                )}
              </div>
            </div>

            <div className="settings-meta-list">
              {metaFields.map((field) => (
                <div className="settings-meta-item" key={field.label}>
                  <span className="settings-meta-label">{field.label}</span>
                  <strong>{resolveMetaValue(field)}</strong>
                </div>
              ))}
            </div>

            <div className="settings-stats-grid">
              <div className="settings-stat-card">
                <span>Profile Completion</span>
                <strong>{profileCompletion}%</strong>
              </div>
              <div className="settings-stat-card">
                <span>Security Status</span>
                <strong>{securityStatus}</strong>
              </div>
              <div className="settings-stat-card">
                <span>Notifications</span>
                <strong>{notifEnabledCount} Enabled</strong>
              </div>
              <div className="settings-stat-card">
                <span>Last Update</span>
                <strong>{formatLastUpdate(user?.lastLoginAt)}</strong>
              </div>
            </div>

            <div className="settings-summary-note mt-4">
              <i className="fa fa-circle-info"></i>
              <span>{summaryNote}</span>
            </div>
          </div>
        </div>

        <div className="col-xl-8 col-lg-7 d-flex">
          <div className="content-card settings-section-card account-settings-panel w-100">
            <div className="content-card-header">
              <i className="fa fa-id-card"></i>
              <h6>Profile Information</h6>
            </div>
            <div className="settings-section-intro">{accountIntro}</div>
            {identityLocked && (
              <div className="alert alert-info py-2 px-3 mb-3" style={{ fontSize: '0.85rem' }}>
                Identity fields are synced from iEnroll and are read-only. You can still update your
                contact number, password, photo, and notification preferences.
              </div>
            )}
            <div className="row g-3 g-lg-4">
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Full Name</label>
                {identityLocked ? (
                  <div className="form-control-plaintext bg-light px-3 py-2 rounded text-dark fw-medium border">
                    {formData.name || '—'}
                  </div>
                ) : (
                  <input
                    type="text"
                    className="form-control"
                    name="name"
                    value={formData.name}
                    onChange={handleFormChange}
                  />
                )}
              </div>
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Email Address</label>
                {identityLocked ? (
                  <div className="form-control-plaintext bg-light px-3 py-2 rounded text-dark fw-medium border">
                    {formData.email || '—'}
                  </div>
                ) : (
                  <input
                    type="email"
                    className="form-control"
                    name="email"
                    value={formData.email}
                    onChange={handleFormChange}
                  />
                )}
              </div>
              {accountExtraFields.map((field) => (
                <div className="col-md-6" key={field.name}>
                  <label className="form-label form-label-subtle">{field.label}</label>
                  {fieldReadOnly(field) ? (
                    <div className="form-control-plaintext bg-light px-3 py-2 rounded text-dark fw-medium border">
                      {formData[field.name] || '—'}
                    </div>
                  ) : field.type === 'select' ? (
                    <select
                      className="form-select"
                      name={field.name}
                      value={formData[field.name] || ''}
                      onChange={handleFormChange}
                    >
                      <option value="">Select…</option>
                      {(field.options || []).map((opt) => (
                        <option key={opt} value={opt}>{opt}</option>
                      ))}
                    </select>
                  ) : (
                    <input
                      type="text"
                      className="form-control"
                      name={field.name}
                      value={formData[field.name] || ''}
                      onChange={handleFormChange}
                    />
                  )}
                  {field.helperText && (
                    <div className="form-text" style={{ fontSize: '0.75rem' }}>{field.helperText}</div>
                  )}
                </div>
              ))}
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Contact Number</label>
                <input
                  type="text"
                  className="form-control"
                  name="contact"
                  value={formData.contact}
                  onChange={handleFormChange}
                  placeholder="e.g. 09123456789"
                />
              </div>
            </div>
            <div className="settings-actions-row mt-4">
              <button type="button" className="btn-green" onClick={handleSaveProfile} disabled={profileSaving}>
                {profileSaving ? 'Saving…' : 'Save Changes'}
              </button>
              <button type="button" className="btn-outline-green" onClick={handleCancelProfile}>Cancel</button>
            </div>
            <p className="text-muted mt-2 mb-0" style={{ fontSize: '0.78rem' }}>
              Profile completion: {profileCompletion}%
            </p>
          </div>
        </div>

        <div className="col-xl-7 col-lg-6 d-flex">
          <div className="content-card settings-section-card settings-notification-card w-100">
            <div className="content-card-header">
              <i className="fa fa-bell"></i>
              <h6>Notifications</h6>
            </div>
            <div className="settings-section-intro">{notificationsIntro}</div>
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

        <div className="col-xl-5 col-lg-6 d-flex">
          <div className="content-card settings-section-card settings-security-card w-100">
            <div className="content-card-header">
              <i className="fa fa-shield-halved"></i>
              <h6>Security</h6>
            </div>
            <div className="settings-section-intro">{securityIntro}</div>

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
          </div>
        </div>

        {/* Children (e.g., SignatureUpload for StudentSettings) */}
        {children}
      </div>

      {cropSrc && (
        <ImageCropModal
          imageSrc={cropSrc}
          onCancel={() => setCropSrc(null)}
          onSave={handleCropSave}
        />
      )}
    </Layout>
  )
}

export default RoleSettings

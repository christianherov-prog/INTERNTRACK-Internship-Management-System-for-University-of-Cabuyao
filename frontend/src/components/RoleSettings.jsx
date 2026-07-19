import { useState, useRef, useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import Layout from './Layout'
import ImageCropModal from './ImageCropModal'
import api from '../services/api'
import { getAvatarSrc } from '../utils/avatar'

/**
 * Shared Settings UI for all roles (Student, Coordinator, Supervisor, Faculty, Director).
 * Role-specific copy/fields are passed as props from thin page wrappers.
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
}) {
  const { user, updateUserLocal, refreshUser } = useAuth()
  const fileInputRef = useRef(null)
  const storageKey = user?.id
    ? `interntrack_notifications_${user.id}`
    : 'interntrack_notifications_guest'

  // Pull fresh /auth/user so Profile Summary meta (company, coordinator, term)
  // reflects the database — not a stale sessionStorage snapshot from login.
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
  })

  const [passwords, setPasswords] = useState({
    current_password: '',
    new_password: '',
  })
  const [passwordLoading, setPasswordLoading] = useState(false)
  const [passwordMessage, setPasswordMessage] = useState({ type: '', text: '' })
  const [profileMessage, setProfileMessage] = useState({ type: '', text: '' })
  const [profileSaving, setProfileSaving] = useState(false)
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(() => {
    if (!user?.id) return false
    return localStorage.getItem(`interntrack_2fa_${user.id}`) === '1'
  })

  const [cropSrc, setCropSrc] = useState(null)
  const [avatarUploading, setAvatarUploading] = useState(false)
  const [avatarError, setAvatarError] = useState(null)

  const [notifications, setNotifications] = useState(() => {
    try {
      const saved = localStorage.getItem(storageKey)
      if (saved) return { ...defaultNotifications, ...JSON.parse(saved) }
    } catch { /* ignore */ }
    return { ...defaultNotifications }
  })

  useEffect(() => {
    setFormData({
      name: user?.name || '',
      email: user?.email || '',
      program: user?.program || '',
      contact: user?.contact || '',
      company: user?.company || '',
      position: user?.position || '',
    })
  }, [user?.id, user?.name, user?.email, user?.program, user?.contact, user?.company, user?.position])

  useEffect(() => {
    localStorage.setItem(storageKey, JSON.stringify(notifications))
  }, [notifications, storageKey])

  useEffect(() => {
    if (!user?.id) return
    localStorage.setItem(`interntrack_2fa_${user.id}`, twoFactorEnabled ? '1' : '0')
  }, [twoFactorEnabled, user?.id])

  const filledAccountFields = [
    formData.name,
    formData.email,
    formData.contact,
    ...accountExtraFields.map(f => formData[f.name]),
  ].filter(v => String(v || '').trim().length > 0).length
  const totalAccountFields = 2 + accountExtraFields.length + 1 // name, email, contact, extras
  const profileCompletion = Math.round((filledAccountFields / Math.max(totalAccountFields, 1)) * 100)
  const notifEnabledCount = Object.values(notifications).filter(Boolean).length

  const handleFormChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value })
  }

  const handlePasswordChange = (e) => {
    setPasswords({ ...passwords, [e.target.name]: e.target.value })
  }

  const handleNotificationChange = (key) => {
    setNotifications(prev => ({ ...prev, [key]: !prev[key] }))
  }

  const handleSaveProfile = async () => {
    const updates = {
      name: formData.name,
      email: formData.email,
      program: formData.program,
      contact: formData.contact,
    }
    if (accountExtraFields.some(f => f.name === 'company')) updates.company = formData.company
    if (accountExtraFields.some(f => f.name === 'position')) updates.position = formData.position

    setProfileSaving(true)
    setProfileMessage({ type: '', text: '' })
    try {
      const res = await api.put('/auth/profile', updates)
      if (res.data?.user) {
        updateUserLocal(res.data.user)
      } else {
        updateUserLocal(updates)
      }
      await refreshUser()
      setProfileMessage({ type: 'success', text: 'Profile saved to your account.' })
    } catch (err) {
      setProfileMessage({
        type: 'error',
        text: err.response?.data?.message || 'Failed to save profile. Changes were not persisted.',
      })
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
    })
    setProfileMessage({ type: '', text: '' })
  }

  const handleUpdatePassword = async () => {
    if (!passwords.current_password || !passwords.new_password) {
      setPasswordMessage({ type: 'error', text: 'Please fill in all password fields.' })
      return
    }
    if (passwords.new_password.length < 8) {
      setPasswordMessage({ type: 'error', text: 'New password must be at least 8 characters.' })
      return
    }

    setPasswordLoading(true)
    setPasswordMessage({ type: '', text: '' })

    try {
      await api.post('/auth/change-password', {
        current_password: passwords.current_password,
        new_password: passwords.new_password,
        new_password_confirmation: passwords.new_password,
      })
      setPasswordMessage({ type: 'success', text: 'Password updated successfully.' })
      setPasswords({ current_password: '', new_password: '' })
    } catch (err) {
      const msg =
        err.response?.data?.errors?.current_password?.[0] ||
        err.response?.data?.message ||
        'Failed to update password.'
      setPasswordMessage({ type: 'error', text: msg })
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
      const fd = new FormData()
      fd.append('avatar', blob, 'avatar.png')
      // Do not set Content-Type manually — axios/browser must add the multipart boundary.
      const { data } = await api.post('/auth/avatar', fd)
      // Push into AuthContext so Topbar (and every other consumer) re-renders immediately.
      updateUserLocal({
        ...data.user,
        avatarVersion: Date.now(),
      })
    } catch (err) {
      setAvatarError(err.response?.data?.message || 'Failed to upload profile photo. Please try again.')
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

  return (
    <Layout title="Settings" subtitle={subtitleLabel} icon="fa-cog" bodyClass={bodyClass}>
      <div className="row g-4 settings-layout">

        {/* Profile Summary */}
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
                {getAvatarSrc(user) ? (
                  <img src={getAvatarSrc(user)} alt="Avatar" className="settings-avatar-img" />
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
              {metaFields.map(field => (
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
                <strong>{twoFactorEnabled ? 'High' : 'Standard'}</strong>
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

        {/* Account Settings */}
        <div className="col-xl-8 col-lg-7 d-flex">
          <div className="content-card settings-section-card account-settings-panel w-100">
            <div className="content-card-header">
              <i className="fa fa-id-card"></i>
              <h6>Account Settings</h6>
            </div>
            <div className="settings-section-intro">{accountIntro}</div>
            {profileMessage.text && (
              <div className={`alert alert-${profileMessage.type === 'error' ? 'danger' : 'success'} py-2 px-3 mb-3`} style={{ fontSize: '0.85rem' }}>
                {profileMessage.text}
              </div>
            )}
            <div className="row g-3 g-lg-4">
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Full Name</label>
                <input type="text" className="form-control" name="name" value={formData.name} onChange={handleFormChange} />
              </div>
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Email Address</label>
                <input type="email" className="form-control" name="email" value={formData.email} onChange={handleFormChange} />
              </div>
              {accountExtraFields.map(field => (
                <div className="col-md-6" key={field.name}>
                  <label className="form-label form-label-subtle">{field.label}</label>
                  <input
                    type="text"
                    className="form-control"
                    name={field.name}
                    value={formData[field.name] || ''}
                    onChange={handleFormChange}
                    readOnly={field.readOnly}
                  />
                </div>
              ))}
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Contact Number</label>
                <input type="text" className="form-control" name="contact" value={formData.contact} onChange={handleFormChange} />
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

        {/* Notifications */}
        <div className="col-xl-7 col-lg-6 d-flex">
          <div className="content-card settings-section-card settings-notification-card w-100">
            <div className="content-card-header">
              <i className="fa fa-bell"></i>
              <h6>Notifications</h6>
            </div>
            <div className="settings-section-intro">{notificationsIntro}</div>
            <div className="settings-toggle-list">
              {notificationDefs.map(item => (
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
                      onChange={() => handleNotificationChange(item.key)}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Security */}
        <div className="col-xl-5 col-lg-6 d-flex">
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

            <div className="row g-3">
              <div className="col-12">
                <label className="form-label form-label-subtle">Current Password</label>
                <input
                  type="password"
                  className="form-control"
                  name="current_password"
                  value={passwords.current_password}
                  onChange={handlePasswordChange}
                  placeholder="••••••••"
                />
              </div>
              <div className="col-12">
                <label className="form-label form-label-subtle">New Password</label>
                <input
                  type="password"
                  className="form-control"
                  name="new_password"
                  value={passwords.new_password}
                  onChange={handlePasswordChange}
                  placeholder="••••••••"
                />
              </div>
            </div>
            <div className="settings-actions-row mt-4">
              <button type="button" className="btn-green" onClick={handleUpdatePassword} disabled={passwordLoading}>
                {passwordLoading ? 'Updating...' : 'Update Password'}
              </button>
              <button type="button" className="btn-outline-green" onClick={() => setTwoFactorEnabled(!twoFactorEnabled)}>
                {twoFactorEnabled ? 'Disable 2-Step Verification' : 'Enable 2-Step Verification'}
              </button>
            </div>
          </div>
        </div>

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

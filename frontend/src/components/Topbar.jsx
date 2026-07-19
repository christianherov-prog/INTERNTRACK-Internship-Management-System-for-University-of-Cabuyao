import { useAuth } from '../contexts/AuthContext'
import { useEffect, useState, useRef } from 'react'
import api from '../services/api'
import { getAvatarSrc } from '../utils/avatar'
import { unwrapList } from '../utils/apiList'

function NotificationBell() {
  const [notifications, setNotifications] = useState([])
  const [unread, setUnread]               = useState(0)
  const [open, setOpen]                   = useState(false)
  const dropRef                           = useRef(null)

  const fetchNotifs = () => {
    api.get('/notifications')
      .then(res => {
        setNotifications(unwrapList(res.data).items)
        setUnread(res.data.unread_count ?? 0)
      })
      .catch(() => {}) // silent fail
  }

  useEffect(() => {
    fetchNotifs()
    // Poll every 60 seconds
    const interval = setInterval(fetchNotifs, 60000)
    return () => clearInterval(interval)
  }, [])

  // Close dropdown on outside click
  useEffect(() => {
    const handleOutside = (e) => {
      if (dropRef.current && !dropRef.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', handleOutside)
    return () => document.removeEventListener('mousedown', handleOutside)
  }, [])

  const markAllRead = async () => {
    await api.post('/notifications/mark-read').catch(() => {})
    setUnread(0)
    setNotifications(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })))
  }

  const getIcon = (type) => {
    const map = {
      document_approved: 'fa-circle-check text-success',
      document_rejected: 'fa-triangle-exclamation text-danger',
      journal_reviewed:  'fa-book text-primary',
    }
    return map[type] ?? 'fa-bell text-secondary'
  }

  const timeAgo = (iso) => {
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
    if (diff < 60)   return `${diff}s ago`
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400)return `${Math.floor(diff / 3600)}h ago`
    return `${Math.floor(diff / 86400)}d ago`
  }

  return (
    <div className="position-relative" ref={dropRef}>
      <button
        className="btn btn-link p-0 position-relative"
        style={{ fontSize: '1.1rem', color: '#64748b', textDecoration: 'none' }}
        onClick={() => setOpen(prev => !prev)}
        aria-label="Notifications"
      >
        <i className="fa fa-bell"></i>
        {unread > 0 && (
          <span
            className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            style={{ fontSize: '0.6rem', padding: '2px 5px' }}
          >
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div
          className="dropdown-menu show shadow"
          style={{ right: 0, left: 'auto', minWidth: '320px', maxHeight: '380px', overflowY: 'auto', top: '32px', position: 'absolute', zIndex: 9999 }}
        >
          <div className="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <span className="fw-semibold">Notifications</span>
            {unread > 0 && (
              <button className="btn btn-link btn-sm p-0" style={{ fontSize: '0.78rem' }} onClick={markAllRead}>
                Mark all read
              </button>
            )}
          </div>

          {notifications.length === 0 ? (
            <div className="text-center text-muted py-4" style={{ fontSize: '0.85rem' }}>
              <i className="fa fa-check-circle d-block fa-2x mb-2 text-success"></i>
              All caught up!
            </div>
          ) : notifications.map(n => (
            <div
              key={n.id}
              className="px-3 py-2 border-bottom"
              style={{ background: n.read_at ? 'white' : '#f0f9ff', cursor: 'default' }}
            >
              <div className="d-flex align-items-start gap-2">
                <i className={`fa ${getIcon(n.type)} mt-1`} style={{ fontSize: '0.9rem', minWidth: '16px' }}></i>
                <div className="flex-grow-1">
                  <div className="fw-semibold" style={{ fontSize: '0.82rem' }}>{n.title}</div>
                  <div className="text-muted" style={{ fontSize: '0.78rem', lineHeight: 1.4 }}>{n.message}</div>
                  <div className="text-muted" style={{ fontSize: '0.72rem', marginTop: '2px' }}>{timeAgo(n.created_at)}</div>
                </div>
                {!n.read_at && <span className="badge bg-primary" style={{ fontSize: '0.6rem', padding: '2px 5px' }}>New</span>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function Topbar({ title, subtitle, icon }) {
  const { user } = useAuth()

  useEffect(() => {
    const toggle  = document.getElementById('sidebarToggle')
    const overlay = document.querySelector('.sidebar-overlay')

    const handleToggle = () => {
      if (window.innerWidth <= 991) {
        document.body.classList.toggle('sidebar-open')
      }
    }

    const closeSidebar = () => {
      document.body.classList.remove('sidebar-open')
    }

    if (toggle)  toggle.addEventListener('click', handleToggle)
    if (overlay) overlay.addEventListener('click', closeSidebar)

    const handleEscape = (e) => { if (e.key === 'Escape') closeSidebar() }
    const handleResize = () => { if (window.innerWidth > 991) closeSidebar() }

    document.addEventListener('keydown', handleEscape)
    window.addEventListener('resize', handleResize)

    return () => {
      if (toggle)  toggle.removeEventListener('click', handleToggle)
      if (overlay) overlay.removeEventListener('click', closeSidebar)
      document.removeEventListener('keydown', handleEscape)
      window.removeEventListener('resize', handleResize)
    }
  }, [])

  const roleLabel = user?.roleLabel || 'User'
  const avatar    = user?.avatar    || 'U'

  return (
    <header className="topbar">
      <div className="topbar-left">
        <button type="button" className="btn-hamburger" id="sidebarToggle" aria-label="Open sidebar">
          <i className="fa fa-bars"></i>
        </button>
        {icon && <div className="topbar-page-icon"><i className={`fa ${icon}`}></i></div>}
        <div className="topbar-title-group">
          <div className="topbar-title">{title}</div>
          {subtitle && <div className="topbar-subtitle">{subtitle}</div>}
        </div>
      </div>
      <div className="topbar-right" style={{ gap: '12px' }}>
        <NotificationBell />
        <span className="role-badge">
          <i className="fa fa-user-shield"></i>
          <span className="role-badge-text">{user?.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User'}</span>
        </span>
        <div className="topbar-avatar" title={`${roleLabel} profile`}>
          {getAvatarSrc(user) ? (
            <img src={getAvatarSrc(user)} alt="Avatar" style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '50%' }} />
          ) : (
            avatar
          )}
        </div>
      </div>
    </header>
  )
}

export default Topbar

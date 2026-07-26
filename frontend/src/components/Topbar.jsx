import { useAuth } from '../contexts/AuthContext'
import { useEffect, useState, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'
import { getAvatarSrc } from '../utils/avatar'
import { unwrapList } from '../utils/apiList'
import {
  initEcho,
  subscribeUserNotifications,
  subscribeLiveStatus,
  notificationPollMs,
} from '../services/echo'

function NotificationBell() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [notifications, setNotifications] = useState([])
  const [unread, setUnread]               = useState(0)
  const [open, setOpen]                   = useState(false)
  const [liveStatus, setLiveStatus]       = useState('polling')
  const dropRef                           = useRef(null)
  const pollRef                           = useRef(null)

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
    initEcho()
    const unsubStatus = subscribeLiveStatus(setLiveStatus)
    const unsub = subscribeUserNotifications(user?.id, (payload) => {
      setNotifications((prev) => {
        if (prev.some((n) => n.id === payload.id)) return prev
        return [payload, ...prev].slice(0, 30)
      })
      setUnread((u) => u + 1)
    })

    const startPoll = () => {
      if (pollRef.current) clearInterval(pollRef.current)
      pollRef.current = setInterval(fetchNotifs, notificationPollMs())
    }
    startPoll()
    const unsubReschedule = subscribeLiveStatus(() => startPoll())

    return () => {
      if (pollRef.current) clearInterval(pollRef.current)
      unsub()
      unsubStatus()
      unsubReschedule()
    }
  }, [user?.id])

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

  const handleNotifClick = (n) => {
    if (!n.read_at) {
      api.post(`/notifications/${n.id}/read`).catch(() => {})
      setNotifications(prev => prev.map(item => item.id === n.id ? { ...item, read_at: new Date().toISOString() } : item))
      setUnread(u => Math.max(0, u - 1))
    }
    setOpen(false)
    if (n.link) {
      if (n.link.startsWith('http://') || n.link.startsWith('https://')) {
        window.location.href = n.link
      } else {
        navigate(n.link)
      }
    }
  }

  const getIcon = (type) => {
    const map = {
      document_approved: 'fa-circle-check text-success',
      document_coordinator_approved: 'fa-circle-check text-success',
      document_rejected: 'fa-circle-xmark text-danger',
      document_pending_faculty: 'fa-file-signature text-warning',
      journal_reviewed:  'fa-book text-primary',
      supervisor_feedback: 'fa-comment-dots text-info',
      supervisor_feedback_submitted: 'fa-comment-dots text-info',
      supervisor_evaluation_submitted: 'fa-clipboard-check text-success',
      meeting_invite: 'fa-calendar-plus text-primary',
      meeting_updated: 'fa-calendar-check text-info',
      placement_assigned: 'fa-briefcase text-success',
      new_message:       'fa-comments text-primary',
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

  const liveHint = liveStatus === 'live'
    ? 'Live updates on (WebSocket)'
    : 'Polling mode — Reverb offline or not configured'

  return (
    <div className="position-relative" ref={dropRef}>
      <button
        className="btn btn-link p-0 position-relative"
        style={{ fontSize: '1.1rem', color: '#64748b', textDecoration: 'none' }}
        onClick={() => setOpen(prev => !prev)}
        aria-label="Notifications"
        title={liveHint}
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
          style={{ right: 0, left: 'auto', minWidth: 'min(340px, calc(100vw - 1.5rem))', maxWidth: 'calc(100vw - 1.5rem)', maxHeight: '400px', overflowY: 'auto', top: '32px', position: 'absolute', zIndex: 9999 }}
        >
          <div className="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <span className="fw-semibold">Notifications</span>
            {unread > 0 && (
              <button className="btn btn-link btn-sm p-0" style={{ fontSize: '0.78rem' }} onClick={markAllRead}>
                Mark all read
              </button>
            )}
          </div>
          <div
            className="px-3 py-1 border-bottom"
            style={{ fontSize: '0.72rem', color: liveStatus === 'live' ? '#15803d' : '#b45309', background: liveStatus === 'live' ? '#f0fdf4' : '#fffbeb' }}
          >
            <i className={`fa ${liveStatus === 'live' ? 'fa-bolt' : 'fa-rotate'} me-1`}></i>
            {liveStatus === 'live'
              ? 'Live (Reverb). Backup refresh every 60s.'
              : 'Reverb unavailable — refreshing every 15s.'}
          </div>

          {notifications.length === 0 ? (
            <div className="text-center text-muted py-5 px-3" style={{ fontSize: '0.88rem' }}>
              <i className="fa fa-bell-slash d-block fa-2x mb-2 text-secondary opacity-50"></i>
              <span className="fw-medium text-dark d-block mb-1">No notifications</span>
              <span className="text-muted" style={{ fontSize: '0.78rem' }}>You're all caught up with your updates!</span>
            </div>
          ) : [...notifications].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).map(n => (
            <div
              key={n.id}
              onClick={() => handleNotifClick(n)}
              className="px-3 py-2 border-bottom notif-item transition-all"
              style={{
                background: n.read_at ? '#ffffff' : '#f0fdf4',
                borderLeft: n.read_at ? '3px solid transparent' : '3px solid #16a34a',
                cursor: 'pointer'
              }}
            >
              <div className="d-flex align-items-start gap-2">
                <i className={`fa ${getIcon(n.type)} mt-1`} style={{ fontSize: '1rem', minWidth: '18px' }}></i>
                <div className="flex-grow-1">
                  <div className={n.read_at ? "fw-medium text-secondary" : "fw-bold text-dark"} style={{ fontSize: '0.83rem' }}>{n.title}</div>
                  <div className="text-muted" style={{ fontSize: '0.78rem', lineHeight: 1.4 }}>{n.message}</div>
                  <div className="text-muted" style={{ fontSize: '0.72rem', marginTop: '2px' }}>{timeAgo(n.created_at)}</div>
                </div>
                {!n.read_at && <span className="badge bg-success" style={{ fontSize: '0.62rem', padding: '3px 6px' }}>New</span>}
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
  const [avatarBroken, setAvatarBroken] = useState(false)

  useEffect(() => {
    setAvatarBroken(false)
  }, [user?.avatarUrl, user?.avatarVersion])

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
  const avatarSrc = getAvatarSrc(user)

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
          {avatarSrc && !avatarBroken ? (
            <img
              src={avatarSrc}
              alt=""
              style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '50%' }}
              onError={() => setAvatarBroken(true)}
            />
          ) : (
            avatar
          )}
        </div>
      </div>
    </header>
  )
}

export default Topbar

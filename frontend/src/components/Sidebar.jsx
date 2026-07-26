import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useState } from 'react'
import ConfirmLogoutModal from './modals/ConfirmLogoutModal'

const ROLE_NAV = {
  student: [
    { section: 'MAIN', to: '/student/dashboard', icon: 'fa-tachometer-alt', text: 'Dashboard' },
    { section: 'TOOLS', to: '/student/attendance', icon: 'fa-calendar-check', text: 'Attendance' },
    { section: 'TOOLS', to: '/student/logbook', icon: 'fa-book', text: 'Logbook' },
    { section: 'TOOLS', to: '/student/documents', icon: 'fa-file-alt', text: 'Documents' },
    { section: 'TOOLS', to: '/student/evaluations', icon: 'fa-star', text: 'Evaluations' },
    { section: 'TOOLS', to: '/student/supervisor-invite', icon: 'fa-qrcode', text: 'Invite Supervisor' },
    { section: 'TOOLS', to: '/student/portfolio', icon: 'fa-briefcase', text: 'Portfolio' },
    { section: 'TOOLS', to: '/student/records', icon: 'fa-folder-open', text: 'My Records' },
    { section: 'TOOLS', to: '/student/messages', icon: 'fa-comments', text: 'Messages' },
    { section: 'TOOLS', to: '/student/meetings', icon: 'fa-calendar', text: 'Meetings' },
    { section: 'ACCOUNT', to: '/student/settings', icon: 'fa-cog', text: 'Settings' },
    { section: 'SESSION', to: '/', icon: 'fa-sign-out-alt', text: 'Logout', isLogout: true }
  ],
  director: [
    { section: 'MAIN', to: '/director/dashboard', icon: 'fa-chart-pie', text: 'Dashboard' },
    { section: 'MAIN', to: '/director/companies', icon: 'fa-building', text: 'Companies' },
    { section: 'MAIN', to: '/director/moa-monitoring', icon: 'fa-file-signature', text: 'MOA Monitoring' },
    { section: 'MAIN', to: '/director/reports', icon: 'fa-chart-bar', text: 'Reports' },
    { section: 'MAIN', to: '/director/internships', icon: 'fa-users', text: 'Student Roster & Placements' },
    { section: 'MAIN', to: '/director/absorption', icon: 'fa-user-check', text: 'Absorption' },
    { section: 'MAIN', to: '/director/announcements', icon: 'fa-bullhorn', text: 'Announcements' },
    { section: 'MAIN', to: '/director/messages', icon: 'fa-comments', text: 'Messages' },
    { section: 'MAIN', to: '/director/meetings', icon: 'fa-calendar', text: 'Meetings' },
    { section: 'ACCOUNT', to: '/director/settings', icon: 'fa-cog', text: 'Settings' },
    { section: 'SESSION', to: '/', icon: 'fa-sign-out-alt', text: 'Logout', isLogout: true }
  ],
  supervisor: [
    { section: 'MAIN', to: '/supervisor/dashboard', icon: 'fa-chart-line', text: 'Dashboard' },
    { section: 'MAIN', to: '/supervisor/assigned-interns', icon: 'fa-users', text: 'Assigned Students' },
    { section: 'MAIN', to: '/supervisor/attendance-validation', icon: 'fa-calendar-check', text: 'Attendance Validation' },
    { section: 'MAIN', to: '/supervisor/journal-validation', icon: 'fa-book', text: 'Journal Review' },
    { section: 'MAIN', to: '/supervisor/feedback', icon: 'fa-comment-dots', text: 'Feedback' },
    { section: 'MAIN', to: '/supervisor/performance-evaluation', icon: 'fa-star', text: 'Evaluations' },
    { section: 'MAIN', to: '/supervisor/absorption', icon: 'fa-user-check', text: 'Absorption' },
    { section: 'MAIN', to: '/supervisor/messages', icon: 'fa-comments', text: 'Messages' },
    { section: 'MAIN', to: '/supervisor/meetings', icon: 'fa-calendar', text: 'Meetings' },
    { section: 'MAIN', to: '/supervisor/notifications', icon: 'fa-bell', text: 'Notifications' },
    { section: 'ACCOUNT', to: '/supervisor/settings', icon: 'fa-cog', text: 'Settings' },
    { section: 'SESSION', to: '/', icon: 'fa-sign-out-alt', text: 'Logout', isLogout: true }
  ],
  faculty: [
    { section: 'MAIN', to: '/faculty/dashboard', icon: 'fa-chart-line', text: 'Dashboard' },
    { section: 'MAIN', to: '/faculty/assigned-students', icon: 'fa-users', text: 'Assigned Students' },
    { section: 'TOOLS', to: '/faculty/attendance', icon: 'fa-calendar-check', text: 'Attendance' },
    { section: 'TOOLS', to: '/faculty/journals', icon: 'fa-book', text: 'Journals' },
    { section: 'TOOLS', to: '/faculty/evaluations', icon: 'fa-star', text: 'Evaluations' },
    { section: 'TOOLS', to: '/faculty/feedback', icon: 'fa-comment-dots', text: 'Feedback' },
    { section: 'TOOLS', to: '/faculty/documents', icon: 'fa-file-circle-check', text: 'Document Verification' },
    { section: 'TOOLS', to: '/faculty/supervisor-approvals', icon: 'fa-user-check', text: 'Supervisor Approvals' },
    { section: 'TOOLS', to: '/faculty/reports', icon: 'fa-chart-bar', text: 'Reports' },
    { section: 'TOOLS', to: '/faculty/messages', icon: 'fa-comments', text: 'Messages' },
    { section: 'TOOLS', to: '/faculty/meetings', icon: 'fa-calendar', text: 'Meetings' },
    { section: 'ACCOUNT', to: '/faculty/settings', icon: 'fa-cog', text: 'Settings' },
    { section: 'SESSION', to: '/', icon: 'fa-sign-out-alt', text: 'Logout', isLogout: true }
  ],
  coordinator: [
    { section: 'MAIN', to: '/coordinator/monitoring', icon: 'fa-chart-line', text: 'Dashboard' },
    { section: 'MAIN', to: '/coordinator/announcements', icon: 'fa-bullhorn', text: 'Announcements' },
    { section: 'MAIN', to: '/coordinator/records', icon: 'fa-folder-open', text: 'Records' },
    { section: 'MAIN', to: '/coordinator/absorption', icon: 'fa-user-check', text: 'Absorption' },
    { section: 'MAIN', to: '/coordinator/reports', icon: 'fa-chart-bar', text: 'Reports' },
    { section: 'MAIN', to: '/coordinator/evaluations', icon: 'fa-star', text: 'Evaluations' },
    { section: 'MAIN', to: '/coordinator/messages', icon: 'fa-comments', text: 'Messages' },
    { section: 'MAIN', to: '/coordinator/meetings', icon: 'fa-calendar', text: 'Meetings' },
    { section: 'ACCOUNT', to: '/coordinator/settings', icon: 'fa-cog', text: 'Settings' },
    { section: 'SESSION', to: '/', icon: 'fa-sign-out-alt', text: 'Logout', isLogout: true }
  ],
  admin: [
    { section: 'MAIN', to: '/admin/dashboard', icon: 'fa-server', text: 'Dashboard' },
    { section: 'MAIN', to: '/admin/directors', icon: 'fa-user-tie', text: 'Directors' },
    { section: 'MAIN', to: '/admin/coordinators', icon: 'fa-user-check', text: 'Coordinators' },
    { section: 'MAIN', to: '/admin/section-mappings', icon: 'fa-sitemap', text: 'Section Mappings' },
    { section: 'MAIN', to: '/admin/users', icon: 'fa-users', text: 'Users' },
    { section: 'MAIN', to: '/admin/sync', icon: 'fa-sync', text: 'MISD Sync' },
    { section: 'ACCOUNT', to: '/admin/settings', icon: 'fa-cog', text: 'Settings' },
    { section: 'SESSION', to: '/', icon: 'fa-sign-out-alt', text: 'Logout', isLogout: true }
  ]
}

function Sidebar() {
  const { user, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  const [isLogoutModalOpen, setIsLogoutModalOpen] = useState(false)
  const [logoutLoading, setLogoutLoading] = useState(false)
  const [logoutError, setLogoutError] = useState(null)

  const openLogoutModal = () => {
    setLogoutError(null)
    setIsLogoutModalOpen(true)
  }

  const closeLogoutModal = () => {
    if (logoutLoading) return
    setIsLogoutModalOpen(false)
    setLogoutError(null)
  }

  const handleConfirmLogout = async () => {
    setLogoutLoading(true)
    setLogoutError(null)

    const result = await logout()

    if (result?.success) {
      setIsLogoutModalOpen(false)
      navigate('/', { replace: true })
    } else {
      setLogoutError(result?.error || 'Logout failed. Please try again.')
    }

    setLogoutLoading(false)
  }

  const handleNavClick = (e, item) => {
    if (item.isLogout) {
      e.preventDefault()
      openLogoutModal()
      return
    }

    if (window.innerWidth <= 991) {
      document.body.classList.remove('sidebar-open')
    }
  }

  const navItems = user ? ROLE_NAV[user.role] || [] : []
  let lastSection = ''

  return (
    <>
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="app-logo">
            <img src="/logo.jpg" alt="Logo" className="app-logo-img" />
            <div className="app-logo-text">
              <div className="app-logo-main">INTERNTRACK</div>
              <div className="app-logo-sub">INTERNSHIP SYSTEM</div>
            </div>
          </div>
        </div>
        <nav className="sidebar-nav">
          {navItems.map((item, index) => {
            const showSection = item.section !== lastSection
            lastSection = item.section
            const isActive = !item.isLogout && location.pathname === item.to
            const logoutClass = item.isLogout ? ' logout-link' : ''

            return (
              <div key={index}>
                {showSection && <div className="nav-section-label">{item.section}</div>}
                <Link
                  to={item.isLogout ? '#' : item.to}
                  className={`sidebar-link${isActive ? ' active' : ''}${logoutClass}${isLogoutModalOpen && item.isLogout ? ' active' : ''}`}
                  onClick={(e) => handleNavClick(e, item)}
                >
                  <i className={`fa ${item.icon}`}></i>
                  <span>{item.text}</span>
                </Link>
              </div>
            )
          })}
        </nav>
      </aside>

      <ConfirmLogoutModal
        open={isLogoutModalOpen}
        loading={logoutLoading}
        error={logoutError}
        onCancel={closeLogoutModal}
        onConfirm={handleConfirmLogout}
      />
    </>
  )
}

export default Sidebar

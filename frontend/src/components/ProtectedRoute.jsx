import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

const SETTINGS_PATH = {
  student: '/student/settings',
  director: '/director/settings',
  supervisor: '/supervisor/settings',
  faculty: '/faculty/settings',
  coordinator: '/coordinator/settings',
  admin: '/admin/settings',
}

function ProtectedRoute({ children, role, allowedRoles }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <i className="fa fa-spinner fa-spin fa-2x mb-3" aria-hidden="true" />
        <div className="small">Checking your session…</div>
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/" replace />
  }

  const roles = allowedRoles || (role ? [role] : null)
  if (roles && !roles.includes(user.role)) {
    const roleRoutes = {
      student: '/student/dashboard',
      director: '/director/dashboard',
      supervisor: '/supervisor/dashboard',
      faculty: '/faculty/dashboard',
      coordinator: '/coordinator/monitoring',
      admin: '/admin/dashboard',
    }
    return <Navigate to={roleRoutes[user.role] || '/'} replace />
  }

  const settingsPath = SETTINGS_PATH[user.role]
  if (user.must_change_password && settingsPath && !location.pathname.endsWith('/settings')) {
    return <Navigate to={settingsPath} replace state={{ forcePasswordChange: true }} />
  }

  return children
}

export default ProtectedRoute

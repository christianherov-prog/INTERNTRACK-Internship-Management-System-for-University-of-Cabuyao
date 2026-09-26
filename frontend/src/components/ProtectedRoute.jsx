import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import InternTrackLoader from './InternTrackLoader'

function ProtectedRoute({ children, role, allowedRoles }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center min-vh-100 text-muted">
        <InternTrackLoader />
        <div className="small">Checking your session…</div>
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/" replace />
  }

  const roles = allowedRoles || (role ? [role] : null)
  const userPassesRole = !roles || roles.includes(user.role)
  if (!userPassesRole) {
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

  return children
}

export default ProtectedRoute

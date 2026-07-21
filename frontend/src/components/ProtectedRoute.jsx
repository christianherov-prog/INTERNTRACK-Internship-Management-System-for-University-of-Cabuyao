import { Navigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

const ROLE_HOME = {
  student: '/student/dashboard',
  director: '/director/dashboard',
  supervisor: '/supervisor/dashboard',
  faculty: '/faculty/dashboard',
  coordinator: '/coordinator/monitoring',
}

/**
 * Route guard for authenticated, role-scoped pages.
 *
 * @param {string} [role] - Single required role (preferred; matches most App.jsx routes).
 * @param {string[]} [allowedRoles] - Alternate multi-role allow-list (same redirect behavior).
 */
function ProtectedRoute({ children, role, allowedRoles }) {
  const { user, loading } = useAuth()

  if (loading) {
    return null
  }

  if (!user) {
    return <Navigate to="/" replace />
  }

  const permitted = allowedRoles?.length
    ? allowedRoles
    : role
      ? [role]
      : null

  if (permitted && !permitted.includes(user.role)) {
    return <Navigate to={ROLE_HOME[user.role] || '/'} replace />
  }

  return children
}

export default ProtectedRoute

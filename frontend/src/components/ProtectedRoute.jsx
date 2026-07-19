import { Navigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

function ProtectedRoute({ children, role }) {
  const { user, loading } = useAuth()

  if (loading) {
    return null
  }

  if (!user) {
    return <Navigate to="/" replace />
  }

  if (role && user.role !== role) {
    const roleRoutes = {
      student: '/student/dashboard',
      director: '/director/dashboard',
      supervisor: '/supervisor/dashboard',
      faculty: '/faculty/dashboard',
      coordinator: '/coordinator/monitoring'
    }
    return <Navigate to={roleRoutes[user.role]} replace />
  }

  return children
}

export default ProtectedRoute

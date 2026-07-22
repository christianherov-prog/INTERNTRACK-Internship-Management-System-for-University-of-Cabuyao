import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

function LoginPage() {
  const [studentNumber, setStudentNumber] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const { login, user } = useAuth()
  const navigate = useNavigate()

  useEffect(() => {
    if (user) {
      const roleRoutes = {
        student: '/student/dashboard',
        director: '/director/dashboard',
        supervisor: '/supervisor/dashboard',
        faculty: '/faculty/dashboard',
        coordinator: '/coordinator/monitoring'
      }
      navigate(roleRoutes[user.role])
    }
  }, [user, navigate])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    const result = await login(studentNumber, password)

    if (result.success) {
      navigate(result.user.dashRoute || '/')
    } else {
      setError(result.error)
      setLoading(false)
    }
  }

  const handleForgotPassword = (e) => {
    e.preventDefault()
    alert('Forgot password? Please contact your coordinator or system administrator to reset your account.')
  }

  return (
    <div className="login-page-redesign">
      <div className="login-split-left">
        <div className="login-bg-overlay" aria-hidden="true" />
        <div className="login-hero-watermark" aria-hidden="true">
          <img
            src="/logo.jpg"
            alt=""
            className="login-hero-watermark-img"
            onError={(e) => {
              e.currentTarget.style.display = 'none'
              e.currentTarget.parentElement?.classList.add('is-missing')
            }}
          />
        </div>
        <div className="login-left-content">
          <h1 className="login-left-title">
            <span className="brand-intern">INTERN</span>
            <span className="brand-track">TRACK</span>
          </h1>
          <p className="login-left-subtitle">Internship Management System</p>
          <p className="login-left-university">UNIVERSITY OF CABUYAO</p>
        </div>
      </div>
      
      <div className="login-split-right">
        <div className="login-right-card">
          <div className="login-header">
            <div className="login-header-brand">
              <img src="/logo.jpg" alt="University Seal" className="login-seal" />
              <div className="login-header-text">
                <h2 className="university-title">University of Cabuyao</h2>
                <p className="university-subtitle">(Pamantasan ng Cabuyao)</p>
              </div>
            </div>
            
            <div className="login-logo-section">
              <h1 className="login-app-title">
                <span className="brand-intern">INTERN</span>
                <span className="brand-track">TRACK</span>
              </h1>
              <p className="login-app-subtitle">INTERNSHIP MANAGEMENT SYSTEM</p>
            </div>
            
            <div className="campus-access-pill">
              <i className="fa fa-id-card"></i>
              CAMPUS ACCESS PORTAL
            </div>
          </div>

          <div className="smart-detection-box">
            <div className="smart-detection-header">
              <i className="fa fa-check-circle"></i>
              <strong>Smart account detection</strong>
            </div>
            <p className="smart-detection-text">
              Use your assigned student or employee credentials to enter the correct workspace.
            </p>
          </div>

          <form className="login-form-redesign" id="loginForm" onSubmit={handleSubmit}>
            <div className="login-input-wrapper">
              <i className="fa fa-user input-icon"></i>
              <input
                type="text"
                className="login-input-field"
                id="studentNumber"
                value={studentNumber}
                onChange={(e) => setStudentNumber(e.target.value)}
                placeholder="Student Number or Employee ID"
                required
              />
            </div>

            <div className="login-input-wrapper">
              <i className="fa fa-lock input-icon"></i>
              <input
                type={showPassword ? 'text' : 'password'}
                className="login-input-field"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Password"
                required
              />
              <button
                type="button"
                className="password-toggle-btn"
                onClick={() => setShowPassword(!showPassword)}
                aria-label="Toggle password visibility"
              >
                <i className={`fa ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i>
              </button>
            </div>

            <div className="login-meta-row">
              <span className="authorized-text">Authorized users only.</span>
              <a href="#" className="forgot-password-link" onClick={handleForgotPassword}>
                Forgot Password?
              </a>
            </div>

            <div className="login-error-slot" role="alert" aria-live="polite">
              {error ? (
                <div className="login-error-box" id="loginError">
                  <i className="fa fa-exclamation-circle" aria-hidden="true" />
                  {error}
                </div>
              ) : null}
            </div>

            <button type="submit" className="btn-signin" disabled={loading}>
              {loading ? (
                <>
                  <i className="fa fa-spinner fa-spin"></i>
                  Signing in...
                </>
              ) : (
                <>
                  <i className="fa fa-arrow-right"></i>
                  SIGN IN
                </>
              )}
            </button>
          </form>

          <div className="login-features">
            <div className="feature-badge">
              <i className="fa fa-shield-alt"></i>
              <span>Secure access</span>
            </div>
            <div className="feature-badge">
              <i className="fa fa-sitemap"></i>
              <span>Role-based workspace</span>
            </div>
            <div className="feature-badge">
              <i className="fa fa-tasks"></i>
              <span>Internship tracking</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default LoginPage

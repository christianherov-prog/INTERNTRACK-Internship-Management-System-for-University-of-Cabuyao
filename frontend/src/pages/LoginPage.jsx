import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import api from '../services/api'
import { InternTrackMark, LOGO_SUBTITLE } from '../components/InternTrackLogo'

function LoginPage() {
  const [studentNumber, setStudentNumber] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [redirecting, setRedirecting] = useState(false)
  const { login, user } = useAuth()
  const navigate = useNavigate()

  // Forgot password flow states
  const [isForgotPassword, setIsForgotPassword] = useState(false)
  const [forgotIdentifier, setForgotIdentifier] = useState('')
  const [forgotLoading, setForgotLoading] = useState(false)
  const [forgotError, setForgotError] = useState('')
  const [forgotSuccess, setForgotSuccess] = useState(false)
  const [forgotSuccessMsg, setForgotSuccessMsg] = useState('')
  const [debugResetUrl, setDebugResetUrl] = useState('')

  useEffect(() => {
    if (user) {
      if (user.must_change_password) {
        const settingsRoutes = {
          student: '/student/settings',
          director: '/director/settings',
          supervisor: '/supervisor/settings',
          faculty: '/faculty/settings',
          coordinator: '/coordinator/settings',
          admin: '/admin/settings',
        }
        navigate(settingsRoutes[user.role] || '/')
        return
      }
      const roleRoutes = {
        student: '/student/dashboard',
        director: '/director/dashboard',
        supervisor: '/supervisor/dashboard',
        faculty: '/faculty/dashboard',
        coordinator: '/coordinator/monitoring',
        admin: '/admin/dashboard',
      }
      navigate(roleRoutes[user.role])
    }
  }, [user, navigate])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    // Client-side validations
    if (!studentNumber.trim()) {
      setError('Student Number or Employee ID is required.')
      return
    }
    if (!password.trim()) {
      setError('Password is required.')
      return
    }

    setLoading(true)

    const result = await login(studentNumber, password)

    if (result.success) {
      setRedirecting(true)
      setTimeout(() => {
        navigate(result.user.dashRoute || '/')
      }, 800)
    } else {
      setError('Unable to sign in ? the Student Number/Employee ID or password is incorrect. Please check your credentials and try again.')
      setLoading(false)
    }
  }

  const handleForgotSubmit = async (e) => {
    e.preventDefault()
    setForgotError('')

    if (!forgotIdentifier.trim()) {
      setForgotError('Please enter your Student Number, Employee ID, or Email.')
      return
    }

    setForgotLoading(true)

    try {
      const res = await api.post('/auth/forgot-password', {
        identifier: forgotIdentifier.trim(),
      })
      setForgotSuccess(true)
      setForgotSuccessMsg(
        res.data?.message ||
          'If an account exists with the provided ID or email, password reset instructions have been sent to the registered email address.'
      )
      if (res.data?.debug_reset_url) {
        setDebugResetUrl(res.data.debug_reset_url)
      }
    } catch (err) {
      setForgotError(
        err.response?.data?.message ||
          'Unable to process password reset request. Please check your input or contact your coordinator.'
      )
    } finally {
      setForgotLoading(false)
    }
  }

  const resetForgotState = () => {
    setIsForgotPassword(false)
    setForgotIdentifier('')
    setForgotError('')
    setForgotSuccess(false)
    setForgotSuccessMsg('')
    setDebugResetUrl('')
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
            <InternTrackMark variant="dark" className="login-hero-mark" />
          </h1>
          <p className="login-left-subtitle">{LOGO_SUBTITLE}</p>
          <p className="login-left-university">UNIVERSITY OF CABUYAO</p>
        </div>
      </div>
      
      <div className="login-split-right">
        <div className="login-right-card">
          <div className="login-header">
            {/* INTERNTRACK Branding is clearly dominant at the top */}
            <div className="login-logo-section mb-3">
              <h1 className="login-app-title">
                <InternTrackMark variant="light" className="login-app-mark" />
              </h1>
              <p className="login-app-subtitle">{LOGO_SUBTITLE}</p>
            </div>

            {/* University context is smaller and subdued below the main brand */}
            <div className="login-header-brand d-flex align-items-center justify-content-center gap-2 mb-3">
              <img src="/logo.jpg" alt="University Seal" className="login-seal" style={{ width: '24px', height: '24px' }} />
              <span className="text-muted fw-semibold" style={{ fontSize: '0.84rem', letterSpacing: '0.04em', textTransform: 'uppercase' }}>
                University of Cabuyao
              </span>
            </div>
            
            <div className="campus-access-pill">
              <i className={`fa ${isForgotPassword ? 'fa-key' : 'fa-id-card'}`}></i>
              {isForgotPassword ? 'PASSWORD RESET ASSISTANCE' : 'CAMPUS ACCESS PORTAL'}
            </div>
          </div>

          {isForgotPassword ? (
            <div className="forgot-view-container">
              {forgotSuccess ? (
                <div className="forgot-success-box">
                  <div className="forgot-success-icon">
                    <i className="fa fa-paper-plane" aria-hidden="true"></i>
                  </div>
                  <h3 className="forgot-success-title">Reset Instructions Sent</h3>
                  <p className="forgot-success-text">{forgotSuccessMsg}</p>
                  
                  <div className="forgot-policy-notice">
                    <p className="mb-1"><strong>Institutional Account Notice:</strong></p>
                    <span>If you do not receive an email or no longer have access to your registered email address, please contact your Department Internship Coordinator or System Administrator for assistance.</span>
                  </div>

                  {debugResetUrl ? (
                    <a href={debugResetUrl} className="btn-dev-reset-link">
                      <i className="fa fa-external-link-alt"></i>
                      Open Reset Link (Dev Link)
                    </a>
                  ) : null}

                  <button
                    type="button"
                    className="btn-forgot-back"
                    style={{ marginTop: '1rem' }}
                    onClick={resetForgotState}
                  >
                    <i className="fa fa-arrow-left"></i>
                    Return to Sign In
                  </button>
                </div>
              ) : (
                <>
                  <div className="smart-detection-box">
                    <div className="smart-detection-header">
                      <i className="fa fa-shield-alt"></i>
                      <strong>Self-Service Account Recovery</strong>
                    </div>
                    <p className="smart-detection-text">
                      Enter your assigned Student Number, Employee ID, or registered email to receive password reset instructions.
                    </p>
                  </div>

                  <form className="login-form-redesign" id="forgotPasswordForm" onSubmit={handleForgotSubmit} aria-busy={forgotLoading}>
                    <div className="login-input-wrapper">
                      <i className="fa fa-user input-icon"></i>
                      <input
                        type="text"
                        className="login-input-field"
                        id="forgotIdentifier"
                        value={forgotIdentifier}
                        onChange={(e) => setForgotIdentifier(e.target.value)}
                        placeholder="Student Number, Employee ID, or Email"
                        required
                        autoFocus
                      />
                    </div>

                    <div className="login-error-slot" role="alert" aria-live="polite">
                      {forgotError ? (
                        <div className="login-error-box" id="forgotError">
                          <i className="fa fa-exclamation-circle" aria-hidden="true" />
                          {forgotError}
                        </div>
                      ) : null}
                    </div>

                    <button type="submit" className="btn-signin" disabled={forgotLoading} aria-busy={forgotLoading}>
                      {forgotLoading ? (
                        <>
                          <i className="fa fa-spinner fa-spin"></i>
                          Sending Request...
                        </>
                      ) : (
                        <>
                          <i className="fa fa-paper-plane"></i>
                          SEND RESET LINK
                        </>
                      )}
                    </button>

                    <button
                      type="button"
                      className="btn-forgot-back"
                      onClick={resetForgotState}
                      disabled={forgotLoading}
                    >
                      <i className="fa fa-arrow-left"></i>
                      Cancel &amp; Return to Sign In
                    </button>
                  </form>
                </>
              )}
            </div>
          ) : (
            <>
              <div className="smart-detection-box">
                <div className="smart-detection-header">
                  <i className="fa fa-check-circle"></i>
                  <strong>Smart account detection</strong>
                </div>
                <p className="smart-detection-text">
                  Use your assigned student or employee credentials to enter the correct workspace.
                </p>
              </div>

              <form className="login-form-redesign" id="loginForm" onSubmit={handleSubmit} aria-busy={loading || redirecting}>
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
                  <button
                    type="button"
                    className="forgot-password-link"
                    style={{ background: 'none', border: 'none', padding: 0, cursor: 'pointer' }}
                    onClick={() => {
                      setError('')
                      setIsForgotPassword(true)
                    }}
                  >
                    Forgot Password?
                  </button>
                </div>

                <div className="login-error-slot" role="alert" aria-live="polite">
                  {error ? (
                    <div className="login-error-box" id="loginError">
                      <i className="fa fa-exclamation-circle" aria-hidden="true" />
                      {error}
                    </div>
                  ) : null}
                </div>

                <button type="submit" className="btn-signin" disabled={loading || redirecting} aria-busy={loading || redirecting}>
                  {redirecting ? (
                    <>
                      <i className="fa fa-circle-notch fa-spin"></i>
                      Redirecting...
                    </>
                  ) : loading ? (
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
            </>
          )}

          <div className="login-features">
            <div className="feature-badge" title="Protected by 256-bit encrypted authentication">
              <i className="fa fa-shield-alt"></i>
              <span>Secure Access</span>
            </div>
            <div className="feature-badge" title="Tailored portals for Students, Faculty, Supervisors, and Admin">
              <i className="fa fa-sitemap"></i>
              <span>Role-Based Access</span>
            </div>
            <div className="feature-badge" title="Real-time DTR, Journal & Milestone Monitoring">
              <i className="fa fa-tasks"></i>
              <span>Internship Tracking</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default LoginPage

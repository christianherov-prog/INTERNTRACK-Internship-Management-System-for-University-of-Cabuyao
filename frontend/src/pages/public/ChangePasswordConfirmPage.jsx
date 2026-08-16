import { useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import api from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'

function ChangePasswordConfirmPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') || ''
  const email = searchParams.get('email') || ''
  const navigate = useNavigate()
  const { updateUserLocal, user } = useAuth()

  const [form, setForm] = useState({
    new_password: '',
    new_password_confirmation: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [errorMsg, setErrorMsg] = useState('')
  const [errors, setErrors] = useState({})
  const [successMsg, setSuccessMsg] = useState('')

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
    setErrors({ ...errors, [e.target.name]: null })
    setErrorMsg('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!token || !email) {
      setErrorMsg('Invalid or missing confirmation parameters in the URL.')
      return
    }

    setSubmitting(true)
    setErrors({})
    setErrorMsg('')

    try {
      const res = await api.post('/auth/confirm-password-change', {
        email,
        token,
        new_password: form.new_password,
        new_password_confirmation: form.new_password_confirmation,
      })
      setSuccessMsg(res.data.message || 'Password changed successfully!')
      if (res.data.user && user) {
        updateUserLocal({ must_change_password: false, ...res.data.user })
      }
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setErrors(err.response.data.errors)
        const firstErr = Object.values(err.response.data.errors)[0]?.[0]
        if (firstErr) setErrorMsg(firstErr)
      } else {
        setErrorMsg(err.response?.data?.message || 'Failed to confirm password change. The link may have expired.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, #0d1b40 0%, #1a3a6b 100%)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }}>
      <div style={{ width: '100%', maxWidth: '480px' }}>
        <div className="text-center mb-4">
          <h1 style={{ color: '#fff', fontWeight: 700, fontSize: '1.6rem' }}>
            <span style={{ color: '#4fc3f7' }}>INTERN</span>TRACK
          </h1>
          <p style={{ color: 'rgba(255,255,255,0.7)', fontSize: '0.85rem' }}>Secure Password Confirmation</p>
        </div>

        <div className="card shadow-lg border-0" style={{ borderRadius: '12px' }}>
          <div className="card-body p-4">
            {!token || !email ? (
              <div className="text-center py-4">
                <i className="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h5 className="fw-bold">Invalid Link</h5>
                <p className="text-muted mb-4">This password confirmation link is missing required security tokens.</p>
                <Link to="/" className="btn btn-primary w-100 py-2">Return to Login</Link>
              </div>
            ) : successMsg ? (
              <div className="text-center py-4">
                <i className="fa fa-check-circle fa-4x text-success mb-3"></i>
                <h5 className="fw-bold">Password Updated!</h5>
                <p className="text-muted mb-4">{successMsg}</p>
                <button
                  type="button"
                  onClick={() => navigate('/')}
                  className="btn btn-success w-100 py-2 fw-semibold"
                >
                  Proceed to Login / Dashboard
                </button>
              </div>
            ) : (
              <>
                <div className="text-center mb-4">
                  <i className="fa fa-shield-halved fa-2x text-success mb-2"></i>
                  <h5 className="fw-bold mb-1">Set Your New Password</h5>
                  <p className="text-muted small mb-0">Confirm your password change for <strong>{email}</strong></p>
                </div>

                {errorMsg && (
                  <div className="alert alert-danger py-2 px-3 small mb-3">
                    <i className="fa fa-exclamation-circle me-2"></i>
                    {errorMsg}
                  </div>
                )}

                <form onSubmit={handleSubmit}>
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">New Password <span className="text-danger">*</span></label>
                    <input
                      type="password"
                      name="new_password"
                      className={`form-control ${errors.new_password ? 'is-invalid' : ''}`}
                      value={form.new_password}
                      onChange={handleChange}
                      placeholder="At least 8 characters"
                      required
                    />
                    {errors.new_password && <div className="invalid-feedback">{errors.new_password[0]}</div>}
                  </div>

                  <div className="mb-4">
                    <label className="form-label small fw-semibold">Confirm New Password <span className="text-danger">*</span></label>
                    <input
                      type="password"
                      name="new_password_confirmation"
                      className={`form-control ${errors.new_password_confirmation ? 'is-invalid' : ''}`}
                      value={form.new_password_confirmation}
                      onChange={handleChange}
                      placeholder="Re-type new password"
                      required
                    />
                    {errors.new_password_confirmation && <div className="invalid-feedback">{errors.new_password_confirmation[0]}</div>}
                  </div>

                  <button type="submit" className="btn btn-success w-100 py-2 fw-semibold" disabled={submitting}>
                    {submitting ? (
                      <><i className="fa fa-spinner fa-spin me-2"></i>Updating Password...</>
                    ) : (
                      <><i className="fa fa-lock me-2"></i>Confirm and Save Password</>
                    )}
                  </button>
                </form>
              </>
            )}
          </div>
        </div>

        <p className="text-center mt-3" style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.75rem' }}>
          &copy; {new Date().getFullYear()} INTERNTRACK &middot; University of Cabuyao
        </p>
      </div>
    </div>
  )
}

export default ChangePasswordConfirmPage

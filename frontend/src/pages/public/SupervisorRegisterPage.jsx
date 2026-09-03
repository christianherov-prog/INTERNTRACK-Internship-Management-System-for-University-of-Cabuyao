import { useState, useEffect, useRef } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import api from '../../services/api'
import { SUFFIX_OPTIONS, suffixToApi } from '../../utils/nameSuffix'
import { InternTrackMark } from '../../components/InternTrackLogo'
import { useAuth } from '../../contexts/AuthContext'

function SupervisorRegisterPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token')
  const navigate = useNavigate()
  const { login, user } = useAuth()

  const [view, setView] = useState('login')
  const [validating, setValidating] = useState(true)
  const [valid, setValid] = useState(false)
  const [errorMsg, setErrorMsg] = useState('')
  const [context, setContext] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [success, setSuccess] = useState(null)
  const [loginId, setLoginId] = useState('')
  const [loginPassword, setLoginPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [loginError, setLoginError] = useState('')
  const [binding, setBinding] = useState(false)

  const [form, setForm] = useState({
    last_name: '',
    first_name: '',
    middle_name: '',
    suffix: '',
    email: '',
    contact_number: '',
    position: '',
    sex: '',
    company_id: '',
    password: '',
    password_confirmation: '',
  })
  const [acceptanceForms, setAcceptanceForms] = useState([])
  const [errors, setErrors] = useState({})
  const bindAttempted = useRef(false)

  useEffect(() => {
    if (!token) {
      setValidating(false)
      setErrorMsg('No invite token provided. Please scan a valid QR code from a student.')
      return
    }
    api.post('/supervisor-register/validate', { token })
      .then(res => {
        setValid(true)
        setContext(res.data)
        if (res.data.prefill_company_id) {
          setForm(f => ({ ...f, company_id: String(res.data.prefill_company_id) }))
        }
      })
      .catch(err => {
        setErrorMsg(err.response?.data?.message || 'Invalid or expired invite link.')
      })
      .finally(() => setValidating(false))
  }, [token])

  useEffect(() => {
    if (!valid || !token || !user || user.role !== 'supervisor' || bindAttempted.current) return
    bindAttempted.current = true
    setBinding(true)
    api.post('/supervisor/invites/bind', { token })
      .then(() => navigate('/supervisor/dashboard', { replace: true }))
      .catch((err) => {
        setLoginError(err.response?.data?.message || 'Could not link this invite to your account.')
        setBinding(false)
      })
  }, [valid, token, user, navigate])

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
    setErrors({ ...errors, [e.target.name]: null })
  }

  const handleLogin = async (e) => {
    e.preventDefault()
    setLoginError('')
    setSubmitting(true)
    const result = await login(loginId, loginPassword)
    if (!result.success) {
      setLoginError(result.error || 'Unable to sign in. Check your Supervisor ID and password.')
      setSubmitting(false)
      return
    }
    if (result.user?.role !== 'supervisor') {
      setLoginError('This invite is for industry supervisors. You signed in with a different role.')
      setSubmitting(false)
      return
    }
    try {
      await api.post('/supervisor/invites/bind', { token })
      navigate('/supervisor/dashboard', { replace: true })
    } catch (err) {
      setLoginError(err.response?.data?.message || 'Signed in, but this invite could not be linked.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    setErrorMsg('')
    try {
      const formData = new FormData()
      Object.keys(form).forEach(key => {
        let val = form[key]
        if (key === 'suffix') {
           val = suffixToApi ? suffixToApi(form.suffix) : form.suffix
        } else if (key === 'middle_name') {
           val = form.middle_name?.trim()
        }
        if (val != null) {
           formData.append(key, val)
        }
      })
      formData.append('token', token)

      acceptanceForms.forEach((file) => {
        formData.append('acceptance_forms[]', file)
      })

      const res = await api.post('/supervisor-register', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      setSuccess(res.data)
    } catch (err) {
      if (err.response?.status === 409 && err.response?.data?.code === 'existing_account') {
        setView('login')
        setLoginError(err.response.data.message)
      } else if (err.response?.status === 422 && err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else {
        setErrorMsg(err.response?.data?.message || 'Registration failed. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const isRegisterView = valid && !success && !validating && !binding && view === 'register'

  const inviteBanner = context && (
    <div className="invite-context alert alert-light border mb-4 py-2 px-3">
      <div className="d-flex align-items-center">
        <i className="fa fa-user-graduate text-success me-3 fa-lg"></i>
        <div>
          <small className="text-muted d-block">You are being invited by:</small>
          <strong>{context.student_name}</strong>
          {context.term && <div className="text-muted small">Term: {context.term}</div>}
          {context.company_name && <div className="text-muted small">Company: {context.company_name}</div>}
        </div>
      </div>
    </div>
  )

  return (
    <div className="public-portal-page">
      <div className={`public-portal-inner${isRegisterView ? ' is-wide' : ''}`}>
        <div className="text-center mb-4">
          <h1 className="text-center mb-0">
            <InternTrackMark variant="dark" className="public-wordmark" />
          </h1>
          <p className="public-portal-kicker">
            {view === 'register' ? 'Supervisor Self-Registration' : 'Supervisor Invite'}
          </p>
        </div>

        <div className="card public-portal-card border-0">
          <div className="card-body">
            {validating || binding ? (
              <div className="text-center py-5">
                <i className="fa fa-spinner fa-spin fa-2x text-success mb-3"></i>
                <p className="text-muted mb-0">{binding ? 'Linking invitation…' : 'Validating your invite link...'}</p>
              </div>
            ) : success ? (
              <div className="text-center py-4">
                <i className="fa fa-check-circle fa-4x text-success mb-3"></i>
                <h5 className="fw-bold">Registration Submitted!</h5>
                <p className="text-muted mb-2">Your account has been created with ID:</p>
                <div className="alert alert-success d-inline-block px-4 py-2 fw-bold" style={{ fontSize: '1.2rem', letterSpacing: '1px' }}>
                  {success.username}
                </div>
                <p className="text-muted small mt-3 mb-0">
                  Your account is pending approval by the Faculty Supervisor.
                  You will be able to log in once it is approved.
                </p>
              </div>
            ) : !valid ? (
              <div className="text-center py-4">
                <i className="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h5 className="fw-bold">Unable to Continue</h5>
                <p className="text-muted">{errorMsg}</p>
                <Link to="/" className="btn-green d-inline-flex align-items-center gap-2 mt-2">Return to Login</Link>
              </div>
            ) : view === 'login' ? (
              <>
                {inviteBanner}
                <h6 className="fw-bold mb-2"><i className="fa fa-sign-in-alt me-2 text-success"></i>Sign in to accept this invite</h6>
                
                {loginError && <div className="alert alert-danger py-2 small">{loginError}</div>}
                <form onSubmit={handleLogin}>
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Supervisor ID</label>
                    <input
                      type="text"
                      className="form-control"
                      value={loginId}
                      onChange={(e) => setLoginId(e.target.value)}
                      placeholder="e.g. SUP-0001"
                      required
                      autoFocus
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Password</label>
                    <div className="position-relative">
                      <input
                        type={showPassword ? 'text' : 'password'}
                        className="form-control"
                        value={loginPassword}
                        onChange={(e) => setLoginPassword(e.target.value)}
                        required
                      />
                      <button
                        type="button"
                        className="password-toggle-btn"
                        style={{ position: 'absolute', right: 8, top: 23 }}
                        onClick={() => setShowPassword((v) => !v)}
                        aria-label="Toggle password visibility"
                      >
                        <i className={`fa ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i>
                      </button>
                    </div>
                  </div>
                  <button type="submit" className="btn-green w-100 py-2" disabled={submitting}>
                    {submitting
                      ? <><i className="fa fa-spinner fa-spin me-2"></i>Signing in...</>
                      : <><i className="fa fa-arrow-right me-2"></i>Sign in</>}
                  </button>
                </form>
                <p className="text-center mt-3 mb-0" style={{ fontSize: '0.9rem' }}>
                  Don&apos;t have an account?{' '}
                  <button type="button" className="btn btn-link public-portal-switch p-0 align-baseline" onClick={() => { setView('register'); setLoginError(''); }}>
                    Register as a supervisor
                  </button>
                </p>
              </>
            ) : (
              <>
                {inviteBanner}
                {errorMsg && <div className="alert alert-danger py-2 small">{errorMsg}</div>}

                <h6 className="registration-section-title"><i className="fa fa-user-tie me-2 text-success"></i>Personal and placement details</h6>

                <form className="public-register-form" onSubmit={handleSubmit}>
                  <div className="row mb-1">
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Last Name <span className="text-danger">*</span></label>
                      <input type="text" name="last_name" className={`form-control ${errors.last_name ? 'is-invalid' : ''}`} value={form.last_name} onChange={handleChange} required />
                      {errors.last_name && <div className="invalid-feedback">{errors.last_name[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">First Name <span className="text-danger">*</span></label>
                      <input type="text" name="first_name" className={`form-control ${errors.first_name ? 'is-invalid' : ''}`} value={form.first_name} onChange={handleChange} required />
                      {errors.first_name && <div className="invalid-feedback">{errors.first_name[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Middle Name</label>
                      <input type="text" name="middle_name" className={`form-control ${errors.middle_name ? 'is-invalid' : ''}`} value={form.middle_name} onChange={handleChange} placeholder="Optional" />
                      {errors.middle_name && <div className="invalid-feedback">{errors.middle_name[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Suffix</label>
                      <select
                        name="suffix"
                        className={`form-select ${errors.suffix ? 'is-invalid' : ''}`}
                        value={form.suffix}
                        onChange={handleChange}
                      >
                        <option value="">N/A</option>
                        {SUFFIX_OPTIONS?.map(opt => (
                          <option key={opt} value={opt}>{opt}</option>
                        ))}
                      </select>
                      {errors.suffix && <div className="invalid-feedback">{errors.suffix[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Email Address <span className="text-danger">*</span></label>
                      <input type="email" name="email" className={`form-control ${errors.email ? 'is-invalid' : ''}`} value={form.email} onChange={handleChange} required />
                      {errors.email && <div className="invalid-feedback">{errors.email[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Contact Number <span className="text-danger">*</span></label>
                      <input type="text" name="contact_number" className={`form-control ${errors.contact_number ? 'is-invalid' : ''}`} value={form.contact_number} onChange={handleChange} placeholder="09XX-XXX-XXXX" required />
                      {errors.contact_number && <div className="invalid-feedback">{errors.contact_number[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Sex <span className="text-danger">*</span></label>
                      <select name="sex" className={`form-select ${errors.sex ? 'is-invalid' : ''}`} value={form.sex} onChange={handleChange} required>
                        <option value="">Select…</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                      {errors.sex && <div className="invalid-feedback">{errors.sex[0]}</div>}
                    </div>
                    <div className="col-md-6 col-lg-3">
                      <label className="form-label small fw-semibold">Position / Designation <span className="text-danger">*</span></label>
                      <input type="text" name="position" className={`form-control ${errors.position ? 'is-invalid' : ''}`} value={form.position} onChange={handleChange} placeholder="e.g. IT Manager" required />
                      {errors.position && <div className="invalid-feedback">{errors.position[0]}</div>}
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Company (Host Training Establishment) <span className="text-danger">*</span></label>
                      <select
                        name="company_id"
                        className={`form-select ${errors.company_id ? 'is-invalid' : ''}`}
                        value={form.company_id}
                        onChange={handleChange}
                        required
                        disabled={!!context?.company_locked}
                      >
                        <option value="">Select your company...</option>
                        {context?.companies?.map(c => (
                          <option key={c.id} value={c.id}>{c.company_name}</option>
                        ))}
                      </select>
                      {context?.company_locked && (
                        <div className="form-text">Pre-filled from the student&apos;s placement — locked to that HTE.</div>
                      )}
                      {errors.company_id && <div className="invalid-feedback">{errors.company_id[0]}</div>}
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Acceptance Form (Proof of Placement) <span className="text-danger">*</span></label>
                      <input
                        type="file"
                        className={`form-control ${errors.acceptance_forms ? 'is-invalid' : ''}`}
                        onChange={(e) => setAcceptanceForms(Array.from(e.target.files))}
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                      />
                      <div className="form-text">You can upload multiple files (PDF or images) as proof of the student&apos;s placement in your company.</div>
                      {errors.acceptance_forms && <div className="invalid-feedback">{errors.acceptance_forms[0]}</div>}
                    </div>
                  </div>

                  <hr className="registration-divider" />
                  <h6 className="registration-section-title"><i className="fa fa-lock me-2 text-success"></i>Account security</h6>

                  <div className="row">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Password <span className="text-danger">*</span></label>
                      <input type="password" name="password" className={`form-control ${errors.password ? 'is-invalid' : ''}`} value={form.password} onChange={handleChange} minLength={8} required />
                      {errors.password && <div className="invalid-feedback">{errors.password[0]}</div>}
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">Confirm Password <span className="text-danger">*</span></label>
                      <input type="password" name="password_confirmation" className="form-control" value={form.password_confirmation} onChange={handleChange} minLength={8} required />
                    </div>
                  </div>

                  <div className="registration-actions">
                    <span className="text-muted small">
                      Already have an account?{' '}
                      <button type="button" className="btn btn-link public-portal-switch p-0 align-baseline" onClick={() => setView('login')}>
                        Sign in instead
                      </button>
                    </span>
                    <button type="submit" className="btn-green py-2 px-4" disabled={submitting}>
                      {submitting
                        ? <><i className="fa fa-spinner fa-spin me-2"></i>Submitting...</>
                        : <><i className="fa fa-paper-plane me-2"></i>Submit Registration</>
                      }
                    </button>
                  </div>
                </form>
              </>
            )}
          </div>
        </div>

        <p className="text-center mt-3 public-portal-footer">
          &copy; {new Date().getFullYear()} INTERNTRACK &middot; University of Cabuyao
        </p>
      </div>
    </div>
  )
}

export default SupervisorRegisterPage

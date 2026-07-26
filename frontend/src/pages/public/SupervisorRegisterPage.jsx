import { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../../services/api'
import { SUFFIX_OPTIONS, suffixToApi } from '../../utils/nameSuffix'

function SupervisorRegisterPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token')

  const [validating, setValidating] = useState(true)
  const [valid, setValid] = useState(false)
  const [errorMsg, setErrorMsg] = useState('')
  const [context, setContext] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [success, setSuccess] = useState(null)

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
  const [errors, setErrors] = useState({})

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

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
    setErrors({ ...errors, [e.target.name]: null })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      const res = await api.post('/supervisor-register', {
        ...form,
        suffix: suffixToApi(form.suffix),
        middle_name: form.middle_name?.trim() || null,
        token,
      })
      setSuccess(res.data)
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else {
        setErrorMsg(err.response?.data?.message || 'Registration failed. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, #0d1b40 0%, #1a3a6b 100%)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }}>
      <div style={{ width: '100%', maxWidth: '540px' }}>
        <div className="text-center mb-4">
          <h1 style={{ color: '#fff', fontWeight: 700, fontSize: '1.6rem' }}>
            <span style={{ color: '#4fc3f7' }}>INTERN</span>TRACK
          </h1>
          <p style={{ color: 'rgba(255,255,255,0.7)', fontSize: '0.85rem' }}>Supervisor Self-Registration Portal</p>
        </div>

        <div className="card shadow-lg border-0" style={{ borderRadius: '12px' }}>
          <div className="card-body p-4">
            {validating ? (
              <div className="text-center py-5">
                <i className="fa fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                <p className="text-muted">Validating your invite link...</p>
              </div>
            ) : success ? (
              <div className="text-center py-4">
                <i className="fa fa-check-circle fa-4x text-success mb-3"></i>
                <h5 className="fw-bold">Registration Submitted!</h5>
                <p className="text-muted mb-2">Your account has been created with ID:</p>
                <div className="alert alert-info d-inline-block px-4 py-2 fw-bold" style={{ fontSize: '1.2rem', letterSpacing: '1px' }}>
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
                <h5 className="fw-bold">Unable to Register</h5>
                <p className="text-muted">{errorMsg}</p>
              </div>
            ) : (
              <>
                <div className="alert alert-light border mb-4 py-2 px-3">
                  <div className="d-flex align-items-center">
                    <i className="fa fa-user-graduate text-primary me-3 fa-lg"></i>
                    <div>
                      <small className="text-muted d-block">You are being invited by:</small>
                      <strong>{context.student_name}</strong>
                      <small className="text-muted ms-2">{context.program}</small>
                      {context.term && <div className="text-muted small">Term: {context.term}</div>}
                      {context.company_name && <div className="text-muted small">Company: {context.company_name}</div>}
                    </div>
                  </div>
                </div>

                {errorMsg && <div className="alert alert-danger py-2 small">{errorMsg}</div>}

                <h6 className="fw-bold mb-3"><i className="fa fa-user-tie me-2"></i>Your Information</h6>

                <form onSubmit={handleSubmit}>
                  <div className="row g-3 mb-3">
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Last Name <span className="text-danger">*</span></label>
                      <input type="text" name="last_name" className={`form-control ${errors.last_name ? 'is-invalid' : ''}`} value={form.last_name} onChange={handleChange} required />
                      {errors.last_name && <div className="invalid-feedback">{errors.last_name[0]}</div>}
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">First Name <span className="text-danger">*</span></label>
                      <input type="text" name="first_name" className={`form-control ${errors.first_name ? 'is-invalid' : ''}`} value={form.first_name} onChange={handleChange} required />
                      {errors.first_name && <div className="invalid-feedback">{errors.first_name[0]}</div>}
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Middle Name</label>
                      <input type="text" name="middle_name" className={`form-control ${errors.middle_name ? 'is-invalid' : ''}`} value={form.middle_name} onChange={handleChange} placeholder="Optional" />
                      {errors.middle_name && <div className="invalid-feedback">{errors.middle_name[0]}</div>}
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Suffix</label>
                      <select
                        name="suffix"
                        className={`form-select ${errors.suffix ? 'is-invalid' : ''}`}
                        value={form.suffix}
                        onChange={handleChange}
                      >
                        <option value="">N/A</option>
                        {SUFFIX_OPTIONS.map(opt => (
                          <option key={opt} value={opt}>{opt}</option>
                        ))}
                      </select>
                      {errors.suffix && <div className="invalid-feedback">{errors.suffix[0]}</div>}
                    </div>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Email Address <span className="text-danger">*</span></label>
                    <input type="email" name="email" className={`form-control ${errors.email ? 'is-invalid' : ''}`} value={form.email} onChange={handleChange} required />
                    {errors.email && <div className="invalid-feedback">{errors.email[0]}</div>}
                  </div>

                  <div className="row g-3 mb-3">
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Contact Number <span className="text-danger">*</span></label>
                      <input type="text" name="contact_number" className={`form-control ${errors.contact_number ? 'is-invalid' : ''}`} value={form.contact_number} onChange={handleChange} placeholder="09XX-XXX-XXXX" required />
                      {errors.contact_number && <div className="invalid-feedback">{errors.contact_number[0]}</div>}
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Sex <span className="text-danger">*</span></label>
                      <select name="sex" className={`form-select ${errors.sex ? 'is-invalid' : ''}`} value={form.sex} onChange={handleChange} required>
                        <option value="">Select…</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                      {errors.sex && <div className="invalid-feedback">{errors.sex[0]}</div>}
                    </div>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Position / Designation <span className="text-danger">*</span></label>
                    <input type="text" name="position" className={`form-control ${errors.position ? 'is-invalid' : ''}`} value={form.position} onChange={handleChange} placeholder="e.g. IT Manager" required />
                    {errors.position && <div className="invalid-feedback">{errors.position[0]}</div>}
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Company (Host Training Establishment) <span className="text-danger">*</span></label>
                    <select
                      name="company_id"
                      className={`form-select ${errors.company_id ? 'is-invalid' : ''}`}
                      value={form.company_id}
                      onChange={handleChange}
                      required
                      disabled={!!context.company_locked}
                    >
                      <option value="">Select your company...</option>
                      {context.companies?.map(c => (
                        <option key={c.id} value={c.id}>{c.company_name}</option>
                      ))}
                    </select>
                    {context.company_locked && (
                      <div className="form-text">Pre-filled from the student&apos;s placement — locked to that HTE.</div>
                    )}
                    {errors.company_id && <div className="invalid-feedback">{errors.company_id[0]}</div>}
                  </div>

                  <hr className="my-3" />
                  <h6 className="fw-bold mb-3"><i className="fa fa-lock me-2"></i>Create Your Password</h6>

                  <div className="row g-3 mb-4">
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Password <span className="text-danger">*</span></label>
                      <input type="password" name="password" className={`form-control ${errors.password ? 'is-invalid' : ''}`} value={form.password} onChange={handleChange} minLength={8} required />
                      {errors.password && <div className="invalid-feedback">{errors.password[0]}</div>}
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">Confirm Password <span className="text-danger">*</span></label>
                      <input type="password" name="password_confirmation" className="form-control" value={form.password_confirmation} onChange={handleChange} minLength={8} required />
                    </div>
                  </div>

                  <button type="submit" className="btn btn-green w-100 py-2" disabled={submitting}>
                    {submitting
                      ? <><i className="fa fa-spinner fa-spin me-2"></i>Submitting...</>
                      : <><i className="fa fa-paper-plane me-2"></i>Submit Registration</>
                    }
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

export default SupervisorRegisterPage

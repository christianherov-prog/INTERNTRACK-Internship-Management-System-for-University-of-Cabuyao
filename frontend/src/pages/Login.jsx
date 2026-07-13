import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

export default function Login() {
  const { login, register } = useAuth();
  const navigate = useNavigate();

  // The login-body class lives on the wrapper div (not <body>): body is
  // display:flex in that rule, which would shrink React's #root to content
  // width and leave a gap on the right.
  useEffect(() => {
    document.body.style.margin = '0';
    return () => {
      document.body.style.margin = '';
    };
  }, []);

  const [mode, setMode] = useState('login');
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const [form, setForm] = useState({
    student_number: '',
    password: '',
    password_confirmation: '',
    full_name: '',
    course_year_section: '',
    company_name: '',
  });

  const setField = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  const firstApiError = (err) => {
    const errors = err.response?.data?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      if (Array.isArray(first) && first.length) return first[0];
    }
    return err.response?.data?.message || 'Something went wrong. Please try again.';
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (mode === 'login' && (!form.student_number.trim() || !form.password)) {
      setError('Enter both your ID and password to continue.');
      return;
    }

    setBusy(true);
    try {
      if (mode === 'login') {
        await login(form.student_number.trim(), form.password);
      } else {
        await register({
          student_number: form.student_number.trim(),
          password: form.password,
          password_confirmation: form.password_confirmation,
          full_name: form.full_name.trim(),
          course_year_section: form.course_year_section.trim(),
          company_name: form.company_name.trim(),
        });
      }
      navigate('/');
    } catch (err) {
      setError(firstApiError(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login-body">
      <div className="login-left">
        <div className="login-left-overlay"></div>
        <img src="/pnc.jpg" alt="PNC Building" className="login-bg-img" />
        <div className="login-left-content">
          <div className="watermark-seal">
            <img src="/logo.png" alt="UC Seal" />
          </div>
          <div className="left-text">
            <h1>INTERNTRACK</h1>
            <p>Internship Management System</p>
            <p className="tagline">University of Cabuyao</p>
          </div>
        </div>
      </div>

      <div className="login-right">
        <div className="login-card login-card-refined">
          <div className="login-logo centered-logo login-logo-balanced">
            <img src="/logo.png" alt="University of Cabuyao Logo" />
            <div className="login-school-name centered-school-name">
              <span className="school-main">University of Cabuyao</span>
              <span className="school-sub">(Pamantasan ng Cabuyao)</span>
            </div>
          </div>

          <div className="system-brand login-brand-refined">
            <span className="brand-intern">INTERN</span><span className="brand-track">TRACK</span>
            <div className="brand-subtitle">Internship Management System</div>
          </div>

          <div className="campus-access-pill">
            <i className="fa fa-id-card me-2"></i> Campus Access Portal
          </div>

          <div className="info-card smart-card smart-card-refined compact-smart-card">
            <div className="info-card-title"><i className="fa fa-circle-check me-2"></i>Student access</div>
            <div className="info-card-text">
              {mode === 'login'
                ? 'Sign in with your student number and password.'
                : 'Create your student account to start tracking your internship.'}
            </div>
          </div>

          <form className="login-form-stack" onSubmit={handleSubmit} noValidate>
            {mode === 'register' && (
              <>
                <div className="form-field form-field-lg">
                  <i className="fa fa-id-badge field-icon"></i>
                  <input
                    type="text"
                    className="form-control custom-input custom-input-lg"
                    placeholder="Full Name"
                    value={form.full_name}
                    onChange={setField('full_name')}
                    required
                  />
                </div>
                <div className="form-field form-field-lg">
                  <i className="fa fa-graduation-cap field-icon"></i>
                  <input
                    type="text"
                    className="form-control custom-input custom-input-lg"
                    placeholder="Course / Year / Section (e.g. BSIT 4-D)"
                    value={form.course_year_section}
                    onChange={setField('course_year_section')}
                    required
                  />
                </div>
                <div className="form-field form-field-lg">
                  <i className="fa fa-building field-icon"></i>
                  <input
                    type="text"
                    className="form-control custom-input custom-input-lg"
                    placeholder="Company Name"
                    value={form.company_name}
                    onChange={setField('company_name')}
                    required
                  />
                </div>
              </>
            )}

            <div className="form-field form-field-lg">
              <i className="fa fa-user field-icon"></i>
              <input
                type="text"
                className="form-control custom-input custom-input-lg"
                placeholder="Student Number"
                autoComplete="username"
                value={form.student_number}
                onChange={setField('student_number')}
                required
              />
            </div>

            <div className="form-field form-field-lg">
              <i className="fa fa-lock field-icon"></i>
              <input
                type={showPassword ? 'text' : 'password'}
                className="form-control custom-input custom-input-lg"
                placeholder="Password"
                autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
                value={form.password}
                onChange={setField('password')}
                required
              />
              <button
                type="button"
                className="toggle-pw"
                aria-label="Toggle password visibility"
                onClick={() => setShowPassword((v) => !v)}
              >
                <i className={`fa ${showPassword ? 'fa-eye' : 'fa-eye-slash'}`}></i>
              </button>
            </div>

            {mode === 'register' && (
              <div className="form-field form-field-lg">
                <i className="fa fa-lock field-icon"></i>
                <input
                  type={showPassword ? 'text' : 'password'}
                  className="form-control custom-input custom-input-lg"
                  placeholder="Confirm Password"
                  autoComplete="new-password"
                  value={form.password_confirmation}
                  onChange={setField('password_confirmation')}
                  required
                />
              </div>
            )}

            <div className="login-meta-row login-meta-row-refined d-flex justify-content-between align-items-start mb-3">
              <span className="login-helper-text">Authorized users only.</span>
              <a
                href="#"
                className="forgot-link"
                onClick={(e) => {
                  e.preventDefault();
                  setError('');
                  setMode(mode === 'login' ? 'register' : 'login');
                }}
              >
                {mode === 'login' ? 'Create an account' : 'Back to sign in'}
              </a>
            </div>

            <button type="submit" className="btn-login btn-login-lg w-100" disabled={busy}>
              {busy ? (
                <><i className="fa fa-spinner fa-spin me-2"></i>{mode === 'login' ? 'Signing in...' : 'Creating account...'}</>
              ) : (
                <><i className="fa fa-right-to-bracket me-2"></i>{mode === 'login' ? 'SIGN IN' : 'SIGN UP'}</>
              )}
            </button>
          </form>

          {error && (
            <div className="login-error">
              <i className="fa fa-exclamation-circle"></i> {error}
            </div>
          )}

          <div className="login-footer login-footer-grid">
            <div className="login-trust-item"><i className="fa fa-shield-halved"></i><span>Secure access</span></div>
            <div className="login-trust-item"><i className="fa fa-diagram-project"></i><span>Role-based workspace</span></div>
            <div className="login-trust-item"><i className="fa fa-clipboard-check"></i><span>Internship tracking</span></div>
          </div>
        </div>
      </div>
    </div>
  );
}

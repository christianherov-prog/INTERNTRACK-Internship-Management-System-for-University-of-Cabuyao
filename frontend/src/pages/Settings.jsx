import { useState } from 'react';
import client from '../api/client';
import { useAuth } from '../context/AuthContext.jsx';

function initialsOf(name) {
  return (name || '')
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase() || 'ST';
}

export default function Settings() {
  const { student, updateStudent } = useAuth();

  const [form, setForm] = useState({
    full_name: student?.full_name || '',
    email: student?.email || '',
    course_year_section: student?.course_year_section || '',
    contact_number: student?.contact_number || '',
    company_name: student?.company_name || '',
  });
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const setField = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  const handleSave = async () => {
    setBusy(true);
    setNotice('');
    setError('');
    try {
      const { data } = await client.put('/auth/profile', {
        full_name: form.full_name,
        email: form.email || null,
        course_year_section: form.course_year_section,
        contact_number: form.contact_number || null,
        company_name: form.company_name,
      });
      updateStudent(data.student);
      setNotice('Profile saved successfully.');
    } catch (err) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors)[0][0] : 'Unable to save profile.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="main-content">
      <div className="row g-4">

        <div className="col-xl-4 col-lg-5 d-flex">
          <div className="content-card w-100">
            <div className="settings-profile-top">
              <div className="settings-avatar-lg">{initialsOf(student?.full_name)}</div>
              <div className="settings-identity">
                <h5>{student?.full_name}</h5>
                <p>{student?.course_year_section} - {student?.student_number}</p>
                <span className="settings-role-pill">Student Account</span>
              </div>
            </div>

            <div className="settings-meta-list">
              <div className="settings-meta-item">
                <span className="settings-meta-label">Assigned Company</span>
                <strong>{student?.company_name}</strong>
              </div>
              <div className="settings-meta-item">
                <span className="settings-meta-label">Internship Term</span>
                <strong>AY 2024-2025, Sem 2</strong>
              </div>
              <div className="settings-meta-item">
                <span className="settings-meta-label">Required Hours</span>
                <strong>{student?.required_hours} hrs</strong>
              </div>
            </div>

            <div className="settings-summary-note">
              <i className="fa fa-circle-info"></i>
              <span>Keep your contact details and internship information updated so coordinators can reach you quickly.</span>
            </div>
          </div>
        </div>

        <div className="col-xl-8 col-lg-7 d-flex">
          <div className="content-card settings-section-card account-settings-panel w-100">
            <div className="content-card-header">
              <i className="fa fa-id-card"></i>
              <h6>Account Settings</h6>
            </div>
            <p className="settings-section-intro">Manage your primary profile details used across records, documents, and monitoring.</p>
            <div className="row g-3 g-lg-4">
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Full Name</label>
                <input className="form-control" value={form.full_name} onChange={setField('full_name')} />
              </div>
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Email Address</label>
                <input className="form-control" value={form.email} onChange={setField('email')} />
              </div>
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Course / Year / Section</label>
                <input className="form-control" value={form.course_year_section} onChange={setField('course_year_section')} />
              </div>
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Contact Number</label>
                <input className="form-control" value={form.contact_number} onChange={setField('contact_number')} />
              </div>
              <div className="col-md-6">
                <label className="form-label form-label-subtle">Company Name</label>
                <input className="form-control" value={form.company_name} onChange={setField('company_name')} />
              </div>
            </div>
            <div className="settings-actions-row">
              <button className="btn-green" type="button" onClick={handleSave} disabled={busy}>
                {busy ? 'Saving...' : 'Save Changes'}
              </button>
            </div>
            {notice && <div className="alert-interntrack mt-3"><i className="fa fa-circle-check me-2"></i>{notice}</div>}
            {error && <div className="alert-interntrack mt-3"><i className="fa fa-circle-info me-2"></i>{error}</div>}
          </div>
        </div>

      </div>

      <footer className="app-footer">&copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span></footer>
    </main>
  );
}

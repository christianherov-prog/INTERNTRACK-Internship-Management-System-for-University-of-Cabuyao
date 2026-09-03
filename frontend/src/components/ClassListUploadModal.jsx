import { useState, useRef, useEffect } from 'react'
import api from '../services/api'

/**
 * ClassListUploadModal
 * Allows coordinators/faculty to upload an Excel class list.
 * The backend batch-creates student accounts and assigns them to a section.
 *
 * Required Excel columns: student_id, first_name, last_name, middle_name (opt), email
 */
function ClassListUploadModal({ onClose, onSuccess }) {
  const [form, setForm] = useState({
    section: '',
    program: '',
    academic_year: '',
    semester: '1',
    faculty_user_id: '',
  })
  const [programs, setPrograms] = useState([])
  const [file, setFile]         = useState(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError]       = useState(null)
  const fileRef = useRef(null)

  useEffect(() => {
    api.get('/academic/programs')
      .then((res) => setPrograms(Array.isArray(res.data) ? res.data : []))
      .catch(() => setPrograms([]))
  }, [])

  const handleChange = e =>
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!file) { setError('Please select an Excel file (.xlsx, .xls, or .csv).'); return }
    if (!form.faculty_user_id) { setError('Please enter the Faculty User ID.'); return }

    setUploading(true)
    setError(null)
    try {
      const fd = new FormData()
      fd.append('file', file)
      Object.entries(form).forEach(([k, v]) => fd.append(k, v))

      await api.post('/coordinator/class-list/upload', fd)
      onSuccess?.('Class list uploaded! Students have been created and assigned to the section.')
      onClose()
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.message || 'Upload failed.')
    } finally {
      setUploading(false)
    }
  }

  return (
    <div
      className="modal fade show d-block"
      tabIndex="-1"
      style={{ background: 'rgba(0,0,0,0.45)' }}
      onClick={e => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="modal-dialog modal-lg modal-dialog-centered">
        <div className="modal-content shadow-lg">
          <div className="modal-header">
            <h5 className="modal-title">
              <i className="fa fa-file-excel me-2 text-success"></i>
              Upload Class List (Excel)
            </h5>
            <button className="btn-close" onClick={onClose}></button>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="modal-body">
              {error && (
                <div className="alert alert-danger">
                  <i className="fa fa-exclamation-circle me-2"></i>{error}
                </div>
              )}

              {/* Format Guide */}
              <div className="alert alert-info mb-3" style={{ fontSize: '0.85rem' }}>
                <strong><i className="fa fa-info-circle me-1"></i>Required Excel Columns:</strong>
                <code className="ms-2">student_id, first_name, last_name, middle_name (optional), email</code>
                <br/>
                <span className="text-muted">Default password will be set to the student's ID number.</span>
              </div>

              <div className="row g-3">
                <div className="col-md-6">
                  <label className="form-label fw-semibold">Section <span className="text-danger">*</span></label>
                  <input
                    name="section" className="form-control"
                    placeholder="e.g. BSIT 4A"
                    value={form.section} onChange={handleChange} required
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label fw-semibold">Program <span className="text-danger">*</span></label>
                  <select
                    name="program" className="form-select"
                    value={form.program} onChange={handleChange} required
                  >
                    <option value="">Select a program</option>
                    {programs.map((p) => (
                      <option key={p.id} value={p.name}>{p.code} — {p.name}</option>
                    ))}
                  </select>
                </div>
                <div className="col-md-5">
                  <label className="form-label fw-semibold">Academic Year <span className="text-danger">*</span></label>
                  <input
                    name="academic_year" className="form-control"
                    placeholder="e.g. 2025-2026"
                    value={form.academic_year} onChange={handleChange} required
                  />
                </div>
                <div className="col-md-3">
                  <label className="form-label fw-semibold">Semester <span className="text-danger">*</span></label>
                  <select name="semester" className="form-select" value={form.semester} onChange={handleChange} required>
                    <option value="1">1st Semester</option>
                    <option value="2">2nd Semester</option>
                    <option value="3">Summer</option>
                  </select>
                </div>
                <div className="col-md-4">
                  <label className="form-label fw-semibold">Faculty User ID <span className="text-danger">*</span></label>
                  <input
                    name="faculty_user_id" className="form-control" type="number"
                    placeholder="e.g. 12"
                    value={form.faculty_user_id} onChange={handleChange} required
                  />
                  <small className="text-muted">The system ID of the assigned faculty member.</small>
                </div>
                <div className="col-12">
                  <label className="form-label fw-semibold">Class List File <span className="text-danger">*</span></label>
                  <input
                    ref={fileRef}
                    type="file"
                    className="form-control"
                    accept=".xlsx,.xls,.csv"
                    onChange={e => setFile(e.target.files[0])}
                    required
                  />
                  <small className="text-muted">Accepted: .xlsx, .xls, .csv</small>
                </div>
              </div>
            </div>

            <div className="modal-footer">
              <button type="button" className="btn btn-outline-secondary" onClick={onClose} disabled={uploading}>
                Cancel
              </button>
              <button type="submit" className="btn btn-success" disabled={uploading}>
                <i className={`fa fa-${uploading ? 'spinner fa-spin' : 'upload'} me-2`}></i>
                {uploading ? 'Uploading…' : 'Upload & Assign Students'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default ClassListUploadModal

import { useState, useRef } from 'react'
import api from '../services/api'
import ModalPortal from './modals/ModalPortal'

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
  const [file, setFile]         = useState(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError]       = useState(null)
  const [dragActive, setDragActive] = useState(false)
  const fileRef = useRef(null)

  const handleChange = e =>
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }))

  const selectFile = (next) => {
    if (!next) {
      setFile(null)
      return
    }
    const ok = /\.(xlsx|xls|csv)$/i.test(next.name)
    if (!ok) {
      setError('Please select an Excel or CSV file (.xlsx, .xls, or .csv).')
      setFile(null)
      if (fileRef.current) fileRef.current.value = ''
      return
    }
    setError(null)
    setFile(next)
  }

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
    <ModalPortal>
      <div
        className="modal fade show d-block"
        tabIndex="-1"
        style={{ background: 'rgba(0,0,0,0.45)' }}
        onClick={e => { if (e.target === e.currentTarget) onClose() }}
      >
        <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div className="modal-content shadow-lg">
            <div className="modal-header">
              <h5 className="modal-title">
                <i className="fa fa-file-excel me-2 text-success"></i>
                Upload Class List (Excel)
              </h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>

            <form onSubmit={handleSubmit}>
              <div className="modal-body">
                {error && (
                  <div className="alert alert-danger">
                    <i className="fa fa-exclamation-circle me-2"></i>{error}
                  </div>
                )}

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
                      placeholder="e.g. 4ITD"
                      value={form.section} onChange={handleChange} required
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label fw-semibold">Program <span className="text-danger">*</span></label>
                    <input
                      name="program" className="form-control"
                      placeholder="e.g. BS Information Technology"
                      value={form.program} onChange={handleChange} required
                    />
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
                    <label className="form-label fw-semibold" htmlFor="class-list-file">
                      Class List File <span className="text-danger">*</span>
                    </label>
                    <div
                      className={`signature-file-control ${dragActive ? 'is-dragging' : ''} ${file ? 'has-file' : ''}`}
                      role="button"
                      tabIndex={0}
                      onClick={() => fileRef.current?.click()}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                          e.preventDefault()
                          fileRef.current?.click()
                        }
                      }}
                      onDragOver={(e) => { e.preventDefault(); setDragActive(true) }}
                      onDragLeave={() => setDragActive(false)}
                      onDrop={(e) => {
                        e.preventDefault()
                        setDragActive(false)
                        selectFile(e.dataTransfer.files?.[0] || null)
                      }}
                    >
                      <input
                        id="class-list-file"
                        ref={fileRef}
                        type="file"
                        className="visually-hidden"
                        accept=".xlsx,.xls,.csv"
                        onChange={e => selectFile(e.target.files[0] || null)}
                        onClick={(e) => e.stopPropagation()}
                      />
                      <span className="signature-file-icon" aria-hidden="true">
                        <i className="fa fa-file-excel"></i>
                      </span>
                      <span className="signature-file-copy">
                        <strong>{file ? file.name : 'Choose class list file'}</strong>
                        <span>{file ? 'Ready to upload' : 'No file selected yet · .xlsx, .xls, or .csv'}</span>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <div className="modal-footer flex-wrap gap-2">
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
    </ModalPortal>
  )
}

export default ClassListUploadModal

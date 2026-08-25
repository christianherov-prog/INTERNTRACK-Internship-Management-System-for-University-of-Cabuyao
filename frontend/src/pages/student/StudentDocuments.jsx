import { useState, useEffect, useRef } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { documentStatusConfig } from '../../utils/documentStatus'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'


function StudentDocuments() {
  const currentTerm = useCurrentTerm()
  const [documents, setDocuments] = useState([])
  const [loading, setLoading]     = useState(true)
  const [error, setError]         = useState(null)
  const [uploading, setUploading]     = useState(null) // type being uploaded
  const [message, setMessage]         = useState(null)

  const [activeType, setActiveType]   = useState(null)
  const [showModal, setShowModal]     = useState(false)
  const [selectedFiles, setSelectedFiles] = useState([])
  const [driveLink, setDriveLink]     = useState('')

  const fetchDocuments = () => {
    setLoading(true)
    setError(null)
    api.get('/student/documents')
      .then(res => {
        setDocuments(unwrapList(res.data).items)
      })
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load documents.')
        setDocuments([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchDocuments() }, [])

  const triggerUpload = (type) => {
    setActiveType(type)
    setSelectedFiles([])
    setDriveLink('')
    setShowModal(true)
  }

  const handleCloseModal = () => {
    setShowModal(false)
    setActiveType(null)
    setSelectedFiles([])
    setDriveLink('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (selectedFiles.length === 0 && !driveLink) {
      alert("Please provide either a file or a Google Drive link.")
      return
    }

    const formData = new FormData()
    formData.append('document_type', activeType)
    if (selectedFiles.length > 0) { selectedFiles.forEach(file => formData.append('files[]', file)) }
    if (driveLink) formData.append('drive_link', driveLink)

    setUploading(activeType); setMessage(null)
    handleCloseModal()
    try {
      await api.post('/student/documents/upload', formData)
      setMessage({ type: 'success', text: `"${activeType}" submitted successfully!` })
      fetchDocuments()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Submission failed.' })
    } finally {
      setUploading(null)
    }
  }

  const handleDownloadTemplate = async (templateId, name) => {
    try {
      const response = await api.get(`/student/requirements/${templateId}/template`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `${name}_template.pdf`)
      document.body.appendChild(link)
      link.click()
      link.parentNode.removeChild(link)
    } catch (err) {
      setMessage({ type: 'danger', text: 'Failed to download template.' })
    }
  }

  const handlePreviewTemplate = async (templateId, name) => {
    try {
      const response = await api.get(`/student/requirements/${templateId}/template?preview=1`, { responseType: 'blob' })
      const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/pdf' })
      const url = window.URL.createObjectURL(blob)
      window.open(url, '_blank')
    } catch (err) {
      if (err.response && err.response.status === 404) {
        setMessage({ type: 'danger', text: 'Template file is missing on the server. Please contact your coordinator.' })
      } else {
        setMessage({ type: 'danger', text: 'Failed to preview template.' })
      }
    }
  }

  const completed = documents.filter(d => d.status === 'completed' || d.status === 'approved').length
  const total = documents.length

  return (
    <Layout title="Documents & Requirements" subtitle={currentTerm} icon="fa-folder-open" bodyClass="student-page">
      {error && <PageError message={error} onRetry={fetchDocuments} />}

      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

      {/* Upload/Submit Modal */}
      {showModal && (
        <div className="modal fade show" style={{ display: 'block', backgroundColor: 'rgba(0,0,0,0.5)' }} tabIndex="-1" onClick={handleCloseModal}>
          <div className="modal-dialog modal-dialog-centered" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Submit Document: {activeType}</h5>
                <button type="button" className="btn-close" onClick={handleCloseModal}></button>
              </div>
              <div className="modal-body">
                <form id="submissionForm" onSubmit={handleSubmit}>
                  <div className="mb-3">
                    <label className="form-label fw-semibold">File Upload (Optional)</label>
                    <input type="file" className="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onChange={e => setSelectedFiles(Array.from(e.target.files))} />
                    <div className="form-text">Max size: 10MB per file. You can select multiple files.</div>
                  </div>
                  <div className="mb-3">
                    <label className="form-label fw-semibold">Google Drive Link (Optional)</label>
                    <input 
                      type="url" 
                      className="form-control" 
                      placeholder="https://drive.google.com/..." 
                      value={driveLink} 
                      onChange={e => setDriveLink(e.target.value)} 
                    />
                  </div>
                  <div className="alert alert-info py-2" style={{ fontSize: '0.85rem' }}>
                    <i className="fa fa-info-circle me-1"></i> You can provide a file, a link, or both.
                  </div>
                </form>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={handleCloseModal}>Cancel</button>
                <button type="submit" form="submissionForm" className="btn btn-primary" disabled={!!uploading || (!selectedFile && !driveLink)}>
                  <i className="fa fa-paper-plane me-1"></i> {uploading ? 'Submitting…' : 'Submit'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Summary */}
      <div className="row g-3 mb-4">
        <div className="col-sm-6">
          <div className="stat-card">
            <div className="stat-icon green"><i className="fa fa-circle-check"></i></div>
            <div>
              <div className="stat-value">{completed} / {total}</div>
              <div className="stat-label">Documents Submitted</div>
            </div>
          </div>
        </div>
        <div className="col-sm-6">
          <div className="stat-card">
            <div className="stat-icon blue"><i className="fa fa-folder"></i></div>
            <div>
              <div className="stat-value">{total - completed}</div>
              <div className="stat-label">Pending Submission</div>
            </div>
          </div>
        </div>
      </div>


      {/* Documents Cards */}
      <div className="row g-4 mt-2">
        {documents.map((doc, idx) => {
          const cfg = documentStatusConfig(doc.status)

          return (
            <div className="col-12" key={doc.document_type}>
              <div className="card border-0 shadow-sm rounded-4 h-100 transition-hover">

                {/* Card Header: Title & Sender */}
                <div className="card-header bg-white border-bottom p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                  <div>
                    <span className="badge bg-light text-secondary border mb-2">Requirement #{idx + 1}</span>
                    <h5 className="fw-bold mb-0 text-dark">{doc.document_type}</h5>
                  </div>

                  {doc.sender && (
                    <div className="text-md-end text-start">
                      <div className="fw-bold text-dark small">{doc.sender.name}</div>
                      <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                        {doc.sender.role} 
                        {doc.deadline && <span className="text-danger ms-1"> Due: {new Date(doc.deadline).toLocaleDateString()}</span>}
                      </div>
                    </div>
                  )}
                </div>

                {/* Card Body: Instructions & Files */}
                <div className="card-body p-4">
                  {doc.description && (
                    <div className="mb-4">
                      <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.8rem' }}>Instructions</h6>
                      <div className="p-3 bg-light rounded-3 text-dark small">{doc.description}</div>
                    </div>
                  )}

                  {(doc.has_template || doc.template_link) && (
                    <div className="mb-4">
                      <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.8rem' }}>Attached Files & Links</h6>
                      <div className="d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                        {doc.template_attachments && doc.template_attachments.length > 0 && doc.template_attachments.map(att => (
                          <div key={att.id} className="mb-2">
                            <AuthenticatedFileLink 
                              path={att.file_path}
                              className="btn btn-link text-start text-decoration-none p-0 d-flex align-items-center fw-medium w-100"
                              style={{ fontSize: '0.95rem' }}
                            >
                              <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '40px', height: '40px' }}>
                                <i className="fa fa-file-pdf text-danger fs-5"></i>
                              </div>
                              <div>
                                <div className="text-dark mb-0 text-truncate" style={{ maxWidth: '300px' }}>{att.file_name || `${doc.document_type} Template`}</div>
                                <div className="text-muted small fw-normal">Click to preview document</div>
                              </div>
                            </AuthenticatedFileLink>
                          </div>
                        ))}
                        
                        {doc.has_template && doc.template_link && <hr className="my-1 border-secondary opacity-10" />}

                        {doc.template_link && (
                          <a 
                            href={doc.template_link} 
                            target="_blank" 
                            rel="noreferrer" 
                            className="text-decoration-none d-flex align-items-center fw-medium"
                            style={{ fontSize: '0.95rem' }}
                          >
                            <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '40px', height: '40px' }}>
                              <i className="fa fa-link text-primary fs-5"></i>
                            </div>
                            <div className="text-truncate">
                              <div className="text-primary mb-0 text-truncate">{doc.template_link}</div>
                              <div className="text-muted small fw-normal">External Link</div>
                            </div>
                          </a>
                        )}
                      </div>
                    </div>
                  )}

                  {doc.attachments && doc.attachments.length > 0 && (
                      <div className="mb-4">
                        <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.8rem' }}>Your Submission</h6>
                        <div className="d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                          {doc.attachments.map(att => (
                            <AuthenticatedFileLink 
                              key={att.id}
                              path={att.file_path}
                              className="btn btn-link text-start text-decoration-none p-0 d-flex align-items-center fw-medium"
                              style={{ fontSize: '0.95rem' }}
                            >
                              <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '40px', height: '40px' }}>
                                <i className="fa fa-file-image text-success fs-5"></i>
                              </div>
                              <div>
                                <div className="text-dark mb-0">{att.file_name || `${doc.document_type} Submission`}</div>
                                <div className="text-muted small fw-normal">Click to preview document</div>
                              </div>
                            </AuthenticatedFileLink>
                          ))}
                        </div>
                      </div>
                    )}

                  <div className="d-flex flex-wrap align-items-center justify-content-between p-3 rounded-3 bg-light border">
                    <div className="d-flex align-items-center gap-3">
                      <div className="text-center">
                        <div className="text-muted text-uppercase fw-semibold mb-1" style={{ fontSize: '0.75rem' }}>Current Status</div>
                        <span className={`badge ${cfg.badge} px-3 py-2 rounded-pill`}>
                          <i className={`fa ${cfg.icon} me-1`}></i> {cfg.label}
                        </span>
                      </div>

                      {doc.submitted_at && (
                        <div className="border-start ps-3">
                          <div className="text-muted text-uppercase fw-semibold mb-1" style={{ fontSize: '0.75rem' }}>Submitted On</div>
                          <div className="small fw-medium text-dark">{new Date(doc.submitted_at).toLocaleString()}</div>
                        </div>
                      )}
                    </div>

                    {doc.remarks && (
                      <div className="flex-grow-1 border-start ps-3 ms-3">
                        <div className="text-muted text-uppercase fw-semibold mb-1" style={{ fontSize: '0.75rem' }}>Remarks</div>
                        <div className={`small fw-medium ${doc.status === 'rejected' ? 'text-danger' : 'text-dark'}`}>
                          {doc.status === 'rejected' && <i className="fa fa-exclamation-triangle me-1"></i>}
                          {doc.remarks}
                        </div>
                      </div>
                    )}
                  </div>
                </div>

                {/* Card Footer: Actions */}
                <div className="card-footer bg-white border-top p-4 d-flex justify-content-end">
                    {(doc.status === 'not_submitted' || doc.status === 'no_submission' || doc.status === 'rejected') && (
                      <button
                        className="btn btn-primary rounded-pill px-4 shadow-sm"
                        onClick={() => triggerUpload(doc.document_type)}
                        disabled={uploading === doc.document_type || doc.is_missed}
                      >
                        {uploading === doc.document_type ? <i className="fa fa-spinner fa-spin me-2"></i> : <i className="fa fa-upload me-2"></i>}
                        {doc.status === 'rejected' ? 'Re-upload Submission' : 'Upload Submission'}
                      </button>
                    )}
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </Layout>
  )
}

export default StudentDocuments


















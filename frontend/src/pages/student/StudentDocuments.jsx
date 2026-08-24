import { useState, useEffect, useRef } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { documentStatusConfig } from '../../utils/documentStatus'
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
  const [selectedFile, setSelectedFile] = useState(null)
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
    setSelectedFile(null)
    setDriveLink('')
    setShowModal(true)
  }

  const handleCloseModal = () => {
    setShowModal(false)
    setActiveType(null)
    setSelectedFile(null)
    setDriveLink('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!selectedFile && !driveLink) {
      alert("Please provide either a file or a Google Drive link.")
      return
    }

    const formData = new FormData()
    formData.append('document_type', activeType)
    if (selectedFile) formData.append('file', selectedFile)
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
      setMessage({ type: 'danger', text: 'Failed to preview template.' })
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
        <div className="modal fade show" style={{ display: 'block', backgroundColor: 'rgba(0,0,0,0.5)' }} tabIndex="-1">
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Submit Document: {activeType}</h5>
                <button type="button" className="btn-close" onClick={handleCloseModal}></button>
              </div>
              <div className="modal-body">
                <form id="submissionForm" onSubmit={handleSubmit}>
                  <div className="mb-3">
                    <label className="form-label fw-semibold">File Upload (Optional)</label>
                    <input 
                      type="file" 
                      className="form-control" 
                      accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                      onChange={e => setSelectedFile(e.target.files[0])} 
                    />
                    <div className="form-text">Max size: 10MB</div>
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


      {/* Documents Table */}
      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-file-lines"></i><h6>Required Documents</h6></div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr><th>#</th><th>Document Type</th><th>Deadline</th><th>Status</th><th>Submitted</th><th>Remarks</th><th className="text-center">Action</th></tr>
                </thead>
                <tbody>
                  {documents.length === 0 ? (
                    <tr>
                      <td colSpan="7" className="text-center py-5 text-muted">
                        <i className="fa fa-folder-open fs-1 opacity-50 mb-3"></i>
                        <h5>No Requirements Yet</h5>
                        <p className="small">Your faculty or coordinator has not assigned any document requirements yet.</p>
                      </td>
                    </tr>
                  ) : (
                    documents.map((doc, idx) => {
                      const cfg = documentStatusConfig(doc.status)
                    return (
                      <tr key={doc.document_type}>
                        <td>{idx + 1}</td>
                        <td><i className="fa fa-file-pdf me-2 text-danger"></i>{doc.document_type}</td>
                        <td style={{fontSize:'0.85rem'}} className={doc.is_missed ? 'text-danger fw-semibold' : 'text-muted'}>
                          {doc.deadline ? new Date(doc.deadline).toLocaleString() : '—'}
                        </td>
                        <td><span className={`badge-status ${cfg.badge}`}><i className={`fa ${cfg.icon} me-1`}></i>{cfg.label}</span></td>
                        <td style={{fontSize:'0.82rem',color:'#64748b'}}>{doc.submitted_at ?? '—'}</td>
                        <td style={{fontSize:'0.82rem'}}>
                          {doc.status === 'rejected'
                            ? <span className="text-danger fw-semibold"><i className="fa fa-exclamation-circle me-1"></i>{doc.remarks ?? 'Rejected'}</span>
                            : <span style={{color:'#64748b'}}>{doc.remarks ?? '—'}</span>
                          }
                        </td>
                        <td className="text-center">
                          <div className="d-flex justify-content-center gap-2">
                            {doc.has_template && (
                              <button
                                className="btn btn-sm btn-outline-info"
                                onClick={() => handlePreviewTemplate(doc.template_id, doc.document_type)}
                                title="Preview Template"
                              >
                                <i className="fa fa-eye"></i>
                              </button>
                            )}
                            {doc.template_link && (
                              <a
                                href={doc.template_link}
                                target="_blank"
                                rel="noreferrer"
                                className="btn btn-sm btn-outline-secondary"
                                title="Open Template Link"
                              >
                                <i className="fa fa-external-link-alt"></i>
                              </a>
                            )}
                            {doc.file_url && (
                              <a
                                href={doc.file_url}
                                target="_blank"
                                rel="noreferrer"
                                className="btn btn-sm btn-outline-success"
                                title="Download / Preview File"
                              >
                                <i className="fa fa-file-pdf"></i>
                              </a>
                            )}
                            {doc.drive_link && (
                              <a
                                href={doc.drive_link}
                                target="_blank"
                                rel="noreferrer"
                                className="btn btn-sm btn-outline-info"
                                title="View Google Drive Link"
                              >
                                <i className="fa fa-link"></i>
                              </a>
                            )}
                            {(doc.status === 'completed' || doc.status === 'approved') ? (
                              <button
                                className="btn btn-sm btn-outline-primary"
                                onClick={() => triggerUpload(doc.document_type)}
                                disabled={uploading === doc.document_type || doc.is_missed}
                                title="Replace Document"
                              >
                                {uploading === doc.document_type
                                  ? <i className="fa fa-spinner fa-spin"></i>
                                  : <i className="fa fa-redo"></i>}
                              </button>
                            ) : (
                              <button
                                className="btn btn-sm btn-primary"
                                onClick={() => triggerUpload(doc.document_type)}
                                disabled={uploading === doc.document_type || doc.is_missed}
                                title={doc.is_missed ? "Deadline has passed" : "Upload Document"}
                              >
                                {uploading === doc.document_type
                                  ? <><i className="fa fa-spinner fa-spin me-1"></i>Uploading…</>
                                  : <><i className="fa fa-upload me-1"></i>Upload</>}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default StudentDocuments

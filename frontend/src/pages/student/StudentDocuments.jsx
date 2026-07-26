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
  const [uploading, setUploading] = useState(null) // type being uploaded
  const [message, setMessage]     = useState(null)

  const fileInputRef              = useRef(null)
  const [activeType, setActiveType] = useState(null)

  const fetchDocuments = () => {
    setLoading(true)
    setError(null)
    api.get('/student/documents')
      .then(res => setDocuments(unwrapList(res.data).items))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load documents.')
        setDocuments([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchDocuments() }, [])

  const triggerUpload = (type) => {

    setActiveType(type)
    fileInputRef.current.click()
  }

  const handleFileChange = async (e) => {
    const file = e.target.files[0]
    if (!file || !activeType) return

    const formData = new FormData()
    formData.append('document_type', activeType)
    formData.append('file', file)


    setUploading(activeType); setMessage(null)
    try {
      await api.post('/student/documents/upload', formData)
      setMessage({ type: 'success', text: `"${activeType}" uploaded successfully!` })
      fetchDocuments()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message ?? 'Upload failed.' })
    } finally {
      setUploading(null)
      setActiveType(null)
      e.target.value = ''
    }
  }

  const approved  = documents.filter(d => d.status === 'approved').length
  const submitted = documents.filter(d => d.status !== 'not_submitted').length

  return (
    <Layout title="Documents & Requirements" subtitle={currentTerm} icon="fa-folder-open" bodyClass="student-page">
      {error && <PageError message={error} onRetry={fetchDocuments} />}

      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

      {/* Hidden file input */}
      <input type="file" ref={fileInputRef} style={{display:'none'}} accept=".pdf,.jpg,.jpeg,.png" onChange={handleFileChange} />

      {/* Summary */}
      <div className="row g-3 mb-4">
        <div className="col-sm-4">
          <div className="stat-card"><div className="stat-icon green"><i className="fa fa-circle-check"></i></div><div><div className="stat-value">{approved}</div><div className="stat-label">Approved</div></div></div>
        </div>
        <div className="col-sm-4">
          <div className="stat-card"><div className="stat-icon amber"><i className="fa fa-clock"></i></div><div><div className="stat-value">{submitted - approved}</div><div className="stat-label">Pending Review</div></div></div>
        </div>
        <div className="col-sm-4">
          <div className="stat-card"><div className="stat-icon blue"><i className="fa fa-folder"></i></div><div><div className="stat-value">{documents.length - submitted}</div><div className="stat-label">Not Submitted</div></div></div>
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
                  <tr><th>#</th><th>Document Type</th><th>Status</th><th>Submitted</th><th>Remarks</th><th className="text-center">Action</th></tr>
                </thead>
                <tbody>
                  {documents.map((doc, idx) => {
                    const cfg = documentStatusConfig(doc.status)
                    return (
                      <tr key={doc.document_type}>
                        <td>{idx + 1}</td>
                        <td><i className="fa fa-file-pdf me-2 text-danger"></i>{doc.document_type}</td>
                        <td><span className={`badge-status ${cfg.badge}`}><i className={`fa ${cfg.icon} me-1`}></i>{cfg.label}</span></td>
                        <td style={{fontSize:'0.82rem',color:'#64748b'}}>{doc.submitted_at ?? '—'}</td>
                        <td style={{fontSize:'0.82rem',color: doc.status === 'rejected' ? '#dc2626' : '#64748b'}}>{doc.remarks ?? '—'}</td>
                        <td className="text-center">
                          {doc.status === 'approved' ? (
                            <span className="text-success" style={{fontSize:'0.82rem'}}><i className="fa fa-lock me-1"></i>Locked</span>
                          ) : (
                            <button
                              className="btn btn-sm btn-outline-green"
                              onClick={() => triggerUpload(doc.document_type)}
                              disabled={uploading === doc.document_type}
                            >
                              {uploading === doc.document_type
                                ? <><i className="fa fa-spinner fa-spin me-1"></i>Uploading…</>
                                : <><i className="fa fa-upload me-1"></i>{doc.status === 'not_submitted' ? 'Upload' : 'Resubmit'}</>
                              }
                            </button>
                          )}
                        </td>
                      </tr>
                    )
                  })}
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

import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

const BACKEND_URL = import.meta.env.VITE_API_BASE_URL?.replace(/\/api\/v1\/?$/, '') || 'http://127.0.0.1:8001'

/**
 * Faculty stage of document routing: only pending_faculty / current_stage=faculty.
 */
function FacultyDocuments() {
  const [docs, setDocs] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [processing, setProcessing] = useState(null)
  const [message, setMessage] = useState(null)
  const [remarkModal, setRemarkModal] = useState(null)
  const [remark, setRemark] = useState('')

  const fetchDocs = () => {
    setLoading(true)
    setLoadError(null)
    api.get('/faculty/documents')
      .then(res => setDocs(unwrapList(res.data).items))
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load documents.')
        setDocs([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchDocs() }, [])

  const verify = async (id) => {
    setProcessing(id)
    try {
      await api.patch(`/faculty/documents/${id}/verify`)
      setMessage({ type: 'success', text: 'Document fully approved.' })
      fetchDocs()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to verify document.' })
    } finally {
      setProcessing(null)
    }
  }

  const submitReject = async () => {
    if (!remark.trim() || !remarkModal) return
    setProcessing(remarkModal.id)
    try {
      await api.patch(`/faculty/documents/${remarkModal.id}/reject`, { remarks: remark })
      setMessage({ type: 'warning', text: 'Document rejected.' })
      setRemarkModal(null)
      fetchDocs()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to reject.' })
    } finally {
      setProcessing(null)
    }
  }

  return (
    <Layout title="Document Verification" subtitle="Faculty stage" icon="fa-file-circle-check" bodyClass="faculty-page">
      {loadError && <PageError message={loadError} onRetry={fetchDocs} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {remarkModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.4)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Reject Document</h5>
                <button className="btn-close" onClick={() => setRemarkModal(null)}></button>
              </div>
              <div className="modal-body">
                <label className="form-label fw-semibold">Remarks <span className="text-danger">*</span></label>
                <textarea className="form-control" rows={3} value={remark} onChange={e => setRemark(e.target.value)} placeholder="Reason for rejection…" />
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setRemarkModal(null)}>Cancel</button>
                <button className="btn btn-danger" onClick={submitReject} disabled={!remark.trim() || processing === remarkModal.id}>
                  Reject
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-inbox"></i>
          <h6>Awaiting Faculty Verification</h6>
          <span className="ms-auto badge bg-info text-dark">{docs.length} in queue</span>
        </div>
        <p className="px-3 pt-3 mb-0 text-muted" style={{ fontSize: '0.85rem' }}>
          Documents here already passed coordinator review. Your verify/reject is the final stage.
        </p>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : docs.length === 0 ? (
            <div className="text-center py-4 text-muted">No documents awaiting faculty verification.</div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Document Type</th>
                    <th>File</th>
                    <th>Stage</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {docs.map(doc => {
                    const p = doc.internship?.student?.student_profile
                      || doc.internship?.student?.studentProfile
                    const name = p ? `${p.first_name} ${p.last_name}` : '—'
                    return (
                      <tr key={doc.id}>
                        <td className="fw-semibold">{name}</td>
                        <td>{doc.document_type}</td>
                        <td>
                          {doc.file_path ? (
                            <a href={`${BACKEND_URL}/storage/${doc.file_path}`} target="_blank" rel="noreferrer">
                              <i className="fa fa-eye me-1"></i>{doc.file_name || 'View'}
                            </a>
                          ) : '—'}
                        </td>
                        <td><span className="badge bg-secondary">Faculty review</span></td>
                        <td className="text-center">
                          <button
                            className="btn btn-sm btn-success me-2"
                            onClick={() => verify(doc.id)}
                            disabled={processing === doc.id}
                          >
                            <i className="fa fa-check me-1"></i>Verify
                          </button>
                          <button
                            className="btn btn-sm btn-danger"
                            onClick={() => { setRemarkModal({ id: doc.id }); setRemark('') }}
                            disabled={processing === doc.id}
                          >
                            <i className="fa fa-times me-1"></i>Reject
                          </button>
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

export default FacultyDocuments

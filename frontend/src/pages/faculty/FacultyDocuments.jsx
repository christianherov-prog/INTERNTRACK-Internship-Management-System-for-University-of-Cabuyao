import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { documentStatusLabel } from '../../utils/documentStatus'

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
  const [verifyModal, setVerifyModal] = useState(null) // { id, document_type }
  const [remarks, setRemarks] = useState('')

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

  const openVerifyModal = (doc) => {
    setVerifyModal(doc)
    setRemarks('')
  }

  const submitVerify = async () => {
    if (!verifyModal) return
    setProcessing(verifyModal.id)
    try {
      const fd = new FormData()
      fd.append('_method', 'PATCH')
      if (remarks) fd.append('remarks', remarks)

      await api.post(`/faculty/documents/${verifyModal.id}/verify`, fd)
      setMessage({ type: 'success', text: `Document ${verifyModal.document_type} verified and approved.` })
      setVerifyModal(null)
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

      {verifyModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.4)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Verify Document</h5>
                <button className="btn-close" onClick={() => setVerifyModal(null)}></button>
              </div>
              <div className="modal-body">
                <p className="text-muted" style={{ fontSize: '0.85rem' }}>
                  Confirm final faculty verification. This fully approves the document.
                </p>
                <div className="mb-3">
                  <label className="form-label fw-semibold">Remarks (optional)</label>
                  <textarea
                    className="form-control"
                    rows={2}
                    value={remarks}
                    onChange={e => setRemarks(e.target.value)}
                    placeholder="Add a note..."
                  ></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setVerifyModal(null)}>Cancel</button>
                <button
                  className="btn btn-success"
                  onClick={submitVerify}
                  disabled={processing === verifyModal.id}
                >
                  <i className={`fa fa-${processing === verifyModal.id ? 'spinner fa-spin' : 'check'} me-2`}></i>
                  Verify &amp; Approve
                </button>
              </div>
            </div>
          </div>
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
          Documents uploaded by your assigned students arrive here directly for your verification and approval.
        </p>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : docs.length === 0 ? (
            <EmptyState
              icon="fa-file-circle-check"
              title="Queue is empty"
              message="No documents are awaiting faculty verification right now."
            />
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
                            <AuthenticatedFileLink path={doc.file_path}>
                              <i className="fa fa-eye me-1"></i>{doc.file_name || 'View'}
                            </AuthenticatedFileLink>
                          ) : '—'}
                        </td>
                        <td><span className="badge bg-info text-dark">{documentStatusLabel('pending_faculty')}</span></td>
                        <td className="text-center">
                          <button
                            className="btn btn-sm btn-success me-2"
                            onClick={() => openVerifyModal(doc)}
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

import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { documentStatusLabel } from '../../utils/documentStatus'
import { useCachedPage } from '../../hooks/useCachedPage'
import InternTrackLoader from '../../components/InternTrackLoader'
import { invalidateStudentDocuments } from '../../utils/pageCache'

/**
 * Faculty stage of document routing: only pending_faculty / current_stage=faculty.
 */
function FacultyDocuments() {
  const { loading, seed, run } = useCachedPage('faculty:documents')
  const [docs, setDocs] = useState(() => unwrapList(seed).items)
  const [loadError, setLoadError] = useState(null)
  const [processing, setProcessing] = useState(null)
  const [message, setMessage] = useState(null)
  const [remarkModal, setRemarkModal] = useState(null)
  const [remark, setRemark] = useState('')
  const [verifyModal, setVerifyModal] = useState(null) // { id, document_type }
  const [remarks, setRemarks] = useState('')
  const [selected, setSelected] = useState([])
  const [downloading, setDownloading] = useState(false)

  const fetchDocs = () => {
    setLoadError(null)
    run(() => api.get('/faculty/documents').then(res => res.data))
      .then((next) => { if (next) setDocs(unwrapList(next).items) })
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load documents.')
      })
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
      await api.post(`/faculty/documents/${verifyModal.id}/review`, { action: 'approve', remarks })
      setMessage({ type: 'success', text: `Document "${verifyModal.document_type}" approved successfully.` })
      setVerifyModal(null)
      invalidateStudentDocuments()
      window.dispatchEvent(new CustomEvent('interntrack:document-reviewed'))
      fetchDocs()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to approve document.' })
    } finally {
      setProcessing(null)
    }
  }

  const toggleAll = () => setSelected(docs.length > 0 && selected.length === docs.length ? [] : docs.map(d => d.id))
  const toggleOne = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])

  const downloadSelected = async () => {
    if (selected.length === 0) return
    setDownloading(true)
    try {
      const res = await api.post('/faculty/documents/bulk-download', { document_ids: selected }, { responseType: 'blob' })
      if (res.data?.type === 'application/json') {
        const text = await res.data.text()
        const json = JSON.parse(text)
        setMessage({ type: 'danger', text: json.message || 'Download failed.' })
        return
      }
      const url = window.URL.createObjectURL(res.data)
      const a = document.createElement('a')
      a.href = url
      a.download = 'interntrack-documents.zip'
      a.click()
      window.URL.revokeObjectURL(url)
    } catch {
      setMessage({ type: 'danger', text: 'Download failed.' })
    } finally {
      setDownloading(false)
    }
  }

  const submitReject = async () => {
    if (!remark.trim() || !remarkModal) return
    setProcessing(remarkModal.id)
    try {
      await api.post(`/faculty/documents/${remarkModal.id}/review`, { action: 'reject', remarks: remark })
      setMessage({ type: 'warning', text: 'Document rejected.' })
      setRemarkModal(null)
      invalidateStudentDocuments()
      window.dispatchEvent(new CustomEvent('interntrack:document-reviewed'))
      fetchDocs()
    } catch {
      setMessage({ type: 'danger', text: 'Failed to reject.' })
    } finally {
      setProcessing(null)
    }
  }

  return (
    <Layout title="Document Approval" subtitle="Faculty Approval Queue" icon="fa-file-circle-check" bodyClass="faculty-page">
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
                <h5 className="modal-title">Approve Document</h5>
                <button className="btn-close" onClick={() => setVerifyModal(null)}></button>
              </div>
              <div className="modal-body">
                <p className="text-muted" style={{ fontSize: '0.85rem' }}>
                  Confirm approval of this document. The student will be notified once approved.
                </p>
                <div className="mb-3">
                  <label className="form-label fw-semibold">Remarks (optional)</label>
                  <textarea maxLength={1000}
                    className="form-control"
                    rows={2}
                    value={remarks}
                    onChange={e => setRemarks(e.target.value)}
                    placeholder="Note"
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
                  Approve
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
                <textarea maxLength={1000} className="form-control" rows={3} value={remark} onChange={e => setRemark(e.target.value)} placeholder="Reason" />
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
          Documents uploaded by your assigned students appear here directly for your approval.
        </p>
        {selected.length > 0 && (
          <div className="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style={{ background: '#f0f9ff' }}>
            <span className="fw-semibold" style={{ fontSize: '0.85rem' }}>{selected.length} selected</span>
            <button className="btn btn-sm btn-outline-primary ms-auto" onClick={downloadSelected} disabled={downloading}>
              <i className={`fa fa-${downloading ? 'spinner fa-spin' : 'file-zipper'} me-1`}></i>Download ZIP
            </button>
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setSelected([])}>Clear</button>
          </div>
        )}
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><InternTrackLoader /></div>
          ) : docs.length === 0 ? (
            <EmptyState
              icon="fa-file-circle-check"
              title="No pending documents"
              message="No documents are awaiting your approval right now."
            />
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style={{ width: 36 }}>
                      <input type="checkbox" className="form-check-input" checked={docs.length > 0 && selected.length === docs.length} onChange={toggleAll} />
                    </th>
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
                    const name = p ? `${p.last_name}, ${p.first_name}` : '—'
                    return (
                      <tr key={doc.id}>
                        <td>
                          <input type="checkbox" className="form-check-input" checked={selected.includes(doc.id)} onChange={() => toggleOne(doc.id)} />
                        </td>
                        <td className="fw-semibold">{name}</td>
                        <td>{doc.document_type}</td>
                        <td>
                          {doc.attachments?.length > 0 ? doc.attachments.map(att => (
                            <AuthenticatedFileLink key={att.id || att.file_path} path={att.file_path}>
                              <i className="fa fa-eye me-1"></i>{att.file_name || 'View'}
                            </AuthenticatedFileLink>
                          )) : doc.file_path ? (
                            <AuthenticatedFileLink path={doc.file_path}>
                              <i className="fa fa-eye me-1"></i>{doc.file_name || 'View'}
                            </AuthenticatedFileLink>
                          ) : doc.drive_link ? (
                            <a href={doc.drive_link} target="_blank" rel="noreferrer">Drive link</a>
                          ) : '—'}
                        </td>
                        <td><span className="badge bg-info text-dark">{documentStatusLabel(doc.status || 'pending_faculty')}</span></td>
                        <td className="text-center">
                          {(doc.attachments?.[0]?.file_path || doc.file_path) && (
                            <AuthenticatedFileLink
                              path={doc.attachments?.[0]?.file_path || doc.file_path}
                              className="btn btn-sm btn-info text-white me-2"
                            >
                              <i className="fa fa-eye me-1"></i>Preview
                            </AuthenticatedFileLink>
                          )}
                          <button
                            className="btn btn-sm btn-success me-2"
                            onClick={() => openVerifyModal(doc)}
                            disabled={processing === doc.id}
                          >
                            <i className="fa fa-check me-1"></i>Approve
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

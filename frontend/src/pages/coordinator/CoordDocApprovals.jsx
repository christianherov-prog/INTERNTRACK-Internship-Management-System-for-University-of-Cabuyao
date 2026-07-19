import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import ConfirmModal from '../../components/modals/ConfirmModal'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function CoordDocApprovals() {
  const [docs, setDocs]       = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [processing, setProcessing] = useState(null) // single-row id, or 'bulk'
  const [message, setMessage] = useState(null)
  const [remarkModal, setRemarkModal] = useState(null) // { id } | { bulk: true }
  const [bulkApproveOpen, setBulkApproveOpen] = useState(false)
  const [remark, setRemark]   = useState('')
  const [selected, setSelected] = useState([]) // array of doc ids

  const fetchDocs = () => {
    setLoading(true)
    setLoadError(null)
    api.get('/coordinator/documents')
      .then(res => setDocs(unwrapList(res.data).items))
      .catch((err) => {
        setLoadError(err.response?.data?.message || 'Failed to load documents.')
        setDocs([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchDocs() }, [])

  const allSelected = docs.length > 0 && selected.length === docs.length

  const toggleAll = () => setSelected(allSelected ? [] : docs.map(d => d.id))
  const toggleOne = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])

  const approve = async (id) => {
    setProcessing(id)
    try {
      await api.patch(`/coordinator/documents/${id}/approve`)
      setMessage({ type: 'success', text: 'Forwarded to faculty verification.' })
      setSelected(prev => prev.filter(x => x !== id))
      fetchDocs()
    } catch { setMessage({ type: 'danger', text: 'Failed to approve.' }) }
    finally { setProcessing(null) }
  }

  const openRejectModal = (id) => { setRemarkModal({ id }); setRemark('') }
  const openBulkRejectModal = () => { setRemarkModal({ bulk: true }); setRemark('') }

  const submitReject = async () => {
    if (!remark.trim()) return
    const isBulk = remarkModal.bulk
    setProcessing(isBulk ? 'bulk' : remarkModal.id)
    try {
      if (isBulk) {
        await api.patch('/coordinator/documents/bulk-reject', { ids: selected, remarks: remark })
        setMessage({ type: 'warning', text: `${selected.length} document(s) rejected with remarks.` })
        setSelected([])
      } else {
        await api.patch(`/coordinator/documents/${remarkModal.id}/reject`, { remarks: remark })
        setMessage({ type: 'warning', text: 'Document rejected with remarks.' })
        setSelected(prev => prev.filter(x => x !== remarkModal.id))
      }
      setRemarkModal(null)
      fetchDocs()
    } catch { setMessage({ type: 'danger', text: 'Failed to reject.' }) }
    finally { setProcessing(null) }
  }

  const bulkApprove = async () => {
    if (selected.length === 0) return
    setProcessing('bulk')
    try {
      await api.patch('/coordinator/documents/bulk-approve', { ids: selected })
      setMessage({ type: 'success', text: `${selected.length} document(s) forwarded to faculty.` })
      setSelected([])
      setBulkApproveOpen(false)
      fetchDocs()
    } catch { setMessage({ type: 'danger', text: 'Failed to approve selected documents.' }) }
    finally { setProcessing(null) }
  }

  return (
    <Layout title="Document Approvals" subtitle="Coordinator stage" icon="fa-file-circle-check" bodyClass="coordinator-page">
      {loadError && <PageError message={loadError} onRetry={fetchDocs} />}
      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}
      <p className="text-muted mb-3" style={{ fontSize: '0.85rem' }}>
        Queue shows documents at the coordinator stage only. Approving forwards them to faculty for final verification — it is not final approval.
      </p>

      <ConfirmModal
        open={bulkApproveOpen}
        title="Forward selected documents?"
        message={`Forward ${selected.length} selected document(s) to faculty verification? Students will be notified.`}
        confirmLabel="Forward to Faculty"
        loading={processing === 'bulk'}
        onCancel={() => processing !== 'bulk' && setBulkApproveOpen(false)}
        onConfirm={bulkApprove}
      />

      {/* Reject Modal (single or bulk) */}
      {remarkModal && (
        <div className="modal show d-block" tabIndex="-1" style={{background:'rgba(0,0,0,0.4)'}}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">{remarkModal.bulk ? `Reject ${selected.length} Document(s)` : 'Reject Document'}</h5>
                <button className="btn-close" onClick={() => setRemarkModal(null)}></button>
              </div>
              <div className="modal-body">
                <label className="form-label fw-semibold">Remarks / Reason for Rejection <span className="text-danger">*</span></label>
                <textarea className="form-control" rows={3} value={remark} onChange={e => setRemark(e.target.value)} placeholder="Provide feedback to the student…"></textarea>
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setRemarkModal(null)}>Cancel</button>
                <button className="btn btn-danger" onClick={submitReject} disabled={!remark.trim() || processing === (remarkModal.bulk ? 'bulk' : remarkModal.id)}>
                  <i className="fa fa-times me-2"></i>Reject {remarkModal.bulk ? 'Selected' : 'Document'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-inbox"></i><h6>Coordinator Review Queue</h6>
          <span className="ms-auto badge bg-warning text-dark">{docs.length} pending</span>
        </div>

        {/* Bulk Action Bar */}
        {selected.length > 0 && (
          <div className="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style={{ background: '#f0f9ff' }}>
            <span className="fw-semibold" style={{ fontSize: '0.85rem' }}>{selected.length} selected</span>
            <button className="btn btn-sm btn-success ms-auto" onClick={() => setBulkApproveOpen(true)} disabled={processing === 'bulk'}>
              <i className={`fa fa-${processing === 'bulk' ? 'spinner fa-spin' : 'check'} me-1`}></i>Forward Selected
            </button>
            <button className="btn btn-sm btn-danger" onClick={openBulkRejectModal} disabled={processing === 'bulk'}>
              <i className="fa fa-times me-1"></i>Reject Selected
            </button>
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setSelected([])}>Clear</button>
          </div>
        )}

        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : docs.length === 0 ? (
            <div className="text-center py-4 text-muted"><i className="fa fa-inbox fa-2x mb-2 d-block"></i>No pending documents.</div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style={{ width: '36px' }}>
                      <input type="checkbox" className="form-check-input" checked={allSelected} onChange={toggleAll} aria-label="Select all" />
                    </th>
                    <th>Student</th><th>Document Type</th><th>File</th><th>Submitted</th><th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {docs.map(doc => (
                    <tr key={doc.id} className={selected.includes(doc.id) ? 'table-active' : ''}>
                      <td>
                        <input type="checkbox" className="form-check-input" checked={selected.includes(doc.id)} onChange={() => toggleOne(doc.id)} aria-label={`Select ${doc.document_type}`} />
                      </td>
                      <td className="fw-semibold">{doc.internship?.student?.studentProfile ? `${doc.internship.student.studentProfile.first_name} ${doc.internship.student.studentProfile.last_name}` : '—'}</td>
                      <td><i className="fa fa-file-pdf me-2 text-danger"></i>{doc.document_type}</td>
                      <td><a href={`http://127.0.0.1:8001/storage/${doc.file_path}`} target="_blank" rel="noreferrer" className="text-primary" style={{fontSize:'0.82rem'}}><i className="fa fa-eye me-1"></i>{doc.file_name}</a></td>
                      <td style={{fontSize:'0.82rem',color:'#64748b'}}>{doc.submitted_at ? new Date(doc.submitted_at).toLocaleDateString() : '—'}</td>
                      <td className="text-center">
                        <button className="btn btn-sm btn-success me-2" onClick={() => approve(doc.id)} disabled={processing === doc.id || processing === 'bulk'}>
                          <i className="fa fa-check me-1"></i>Forward to Faculty
                        </button>
                        <button className="btn btn-sm btn-danger" onClick={() => openRejectModal(doc.id)} disabled={processing === doc.id || processing === 'bulk'}>
                          <i className="fa fa-times me-1"></i>Reject
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default CoordDocApprovals

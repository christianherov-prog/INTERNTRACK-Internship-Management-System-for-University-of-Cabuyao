import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect, useRef } from 'react'
import api from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'
import toast from 'react-hot-toast'
import Layout from '../../components/Layout'
import { useConfirm } from '../../contexts/ConfirmContext'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { documentStatusConfig } from '../../utils/documentStatus'

const REVIEWABLE_STATUSES = ['pending', 'pending_review', 'pending_faculty', 'under_review', 'resubmitted']

export default function ManageRequirementsTemplates({ embedded = false }) {
  const confirm = useConfirm()
  const { user } = useAuth()
  const [requirements, setRequirements] = useState([])
  const [options, setOptions] = useState({ students: [], sections: [], programs: [] })
  const isCoordinator = user?.role === 'coordinator'
  const [isLoading, setIsLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  // Submissions Modal State
  const [isSubmissionsModalOpen, setIsSubmissionsModalOpen] = useState(false)
  const [activeReqSubmissions, setActiveReqSubmissions] = useState(null)

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingReq, setEditingReq] = useState(null)

  // Form State
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    targetType: 'student', // 'student', 'section', 'program'
    selectedTargets: [], // array of ids
    templateFiles: [],
    removeAttachments: [],
    driveLink: '',
    deadline: '',
  })

  // Review State
  const [reviewingDoc, setReviewingDoc] = useState(null)
  const [reviewRemarks, setReviewRemarks] = useState('')
  const [reviewingBusy, setReviewingBusy] = useState(false)
  const [targetSearch, setTargetSearch] = useState('')

  const fileInputRef = useRef(null)
  const rolePath = user?.role

  const isTargetSelected = (id) => formData.selectedTargets.some((t) => String(t) === String(id))

  const toggleTarget = (id, checked) => {
    const sid = String(id)
    setFormData((prev) => ({
      ...prev,
      selectedTargets: checked
        ? [...prev.selectedTargets.filter((t) => String(t) !== sid), sid]
        : prev.selectedTargets.filter((t) => String(t) !== sid),
    }))
  }

  const targetList = formData.targetType === 'student'
    ? options.students
    : formData.targetType === 'section'
      ? options.sections
      : options.programs

  const visibleTargets = targetList.filter((item) => {
    const q = targetSearch.trim().toLowerCase()
    if (!q) return true
    return `${item.name || ''} ${item.section || ''}`.toLowerCase().includes(q)
  })

  useEffect(() => {
    if (!rolePath) return
    fetchRequirements()
    fetchOptions()
  }, [rolePath])

  const fetchOptions = async () => {
    try {
      const { data } = await api.get(`/${rolePath}/requirements/options`)
      setOptions({
        students: data.students || [],
        sections: data.sections || [],
        programs: data.programs || [],
      })
    } catch (err) {
      console.error('Failed to fetch options', err)
    }
  }

  const fetchRequirements = async () => {
    try {
      setIsLoading(true)
      const { data } = await api.get(`/${rolePath}/requirements`)
      setRequirements(data.data || [])
    } catch (err) {
      toast.error('Failed to load requirements')
    } finally {
      setIsLoading(false)
    }
  }

  const handleOpenModal = (req = null) => {
    if (req) {
      setEditingReq(req)
      setFormData({
        name: req.name,
        description: req.description || '',
        targetType: req.targets && req.targets.length > 0 ? req.targets[0].target_type : 'student',
        selectedTargets: req.targets ? req.targets.map(t => String(t.target_id)) : [],
        templateFiles: [],
        removeAttachments: [],
        driveLink: req.drive_link || '',
        deadline: req.deadline ? new Date(req.deadline).toISOString().substring(0, 16) : '',
      })
      setTargetSearch('')
    } else {
      setEditingReq(null)
      setFormData({ name: '', description: '', targetType: 'student', selectedTargets: [], templateFiles: [], removeAttachments: [], driveLink: '', deadline: '' })
      setTargetSearch('')
    }
    setIsModalOpen(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (submitting) return

    const targetItems = formData.selectedTargets.map(id => ({ type: formData.targetType, id }))

    if (targetItems.length === 0) {
      toast.error('Please specify at least one target.')
      return
    }

    const form = new FormData()
    form.append('name', formData.name)
    form.append('description', formData.description)
    form.append('category', 'general')
    if (formData.deadline) {
      form.append('deadline', formData.deadline)
    }
    targetItems.forEach((t, i) => {
      form.append(`targets[${i}][type]`, t.type)
      form.append(`targets[${i}][id]`, t.id)
    })

    if (formData.templateFiles && formData.templateFiles.length > 0) {
      formData.templateFiles.forEach(file => {
        form.append('template_files[]', file)
      })
    }
    
    if (formData.removeAttachments && formData.removeAttachments.length > 0) {
      formData.removeAttachments.forEach(id => {
        form.append('remove_attachments[]', id)
      })
    }

    if (formData.driveLink) {
      form.append('drive_link', formData.driveLink)
    }

    setSubmitting(true)
    try {
      if (editingReq) {
        await api.post(`/${rolePath}/requirements/${editingReq.id}`, form)
        toast.success('Requirement updated successfully')
      } else {
        await api.post(`/${rolePath}/requirements`, form)
        toast.success('Requirement created successfully')
      }
      setIsModalOpen(false)
      fetchRequirements()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to save requirement')
    } finally {
      setSubmitting(false)
    }
  }

  const handleReview = async (docId, action) => {
    if (!docId || reviewingBusy) return
    setReviewingBusy(true)
    try {
      await api.post(`/${rolePath}/documents/${docId}/review`, {
        action,
        remarks: reviewRemarks
      })
      toast.success(`Document ${action === 'approve' ? 'approved' : 'rejected'}. The student has been notified.`)
      setReviewingDoc(null)
      setReviewRemarks('')
      const { data } = await api.get(`/${rolePath}/requirements`)
      const list = data.data || []
      setRequirements(list)
      if (activeReqSubmissions) {
        const updated = list.find((r) => r.id === activeReqSubmissions.id)
        if (updated) setActiveReqSubmissions(updated)
      }
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to review document')
    } finally {
      setReviewingBusy(false)
    }
  }

  const handleDelete = async (id) => {
    if (!(await confirm({ message: 'Are you sure you want to delete this requirement?', variant: 'danger' }))) return
    try {
      await api.delete(`/${rolePath}/requirements/${id}`)
      toast.success('Requirement deleted')
      fetchRequirements()
    } catch (err) {
      toast.error('Failed to delete requirement')
    }
  }

  const Wrapper = embedded ? 'div' : Layout;
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "Manage Requirements", subtitle: "Configure the documents required from your students.", icon: "fa-file-circle-check", bodyClass: `${user.role}-page` };

  return (
    <Wrapper {...wrapperProps}>
      <div className="d-flex justify-content-end mb-4">
        <button className="btn btn-primary shadow-sm" onClick={() => handleOpenModal()}>
          <i className="fa fa-plus me-2"></i> Add Requirement
        </button>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-list"></i>
          <h6>Active Requirements</h6>
        </div>

        {isLoading ? (
          <div className="text-center py-5">
            <i className="fa fa-spinner fa-spin fa-2x text-muted"></i>
          </div>
        ) : requirements.length === 0 ? (
          <div className="text-center py-5">
            <div className="mb-3 text-primary" style={{ fontSize: '3rem' }}>
              <i className="fa fa-folder-open text-muted opacity-50"></i>
            </div>
            <h5 className="text-muted">No Requirements Yet</h5>
            <p className="text-muted small">You haven't set up any required documents. Add one to start tracking student compliance.</p>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>Requirement Name</th>
                  <th>Target Type</th>
                  <th>Assigned To</th>
                  <th>Submissions</th>
                  <th>Document</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {requirements.map((req) => (
                  <tr key={req.id}>
                    <td>
                      <div className="fw-bold text-dark">{req.name}</div>
                      {req.description && (
                        <div className="text-muted small mt-1" style={{ maxWidth: '300px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          {req.description}
                        </div>
                      )}
                    </td>
                    <td>
                      <span className="badge bg-secondary text-capitalize">
                        {req.targets?.[0]?.target_type || 'Unknown'}
                      </span>
                    </td>
                    <td>
                      <div className="small text-muted fw-medium">
                        {req.targets?.length || 0} {req.targets?.length === 1 ? 'target' : 'targets'}
                      </div>
                      <div className="small text-dark" style={{ maxWidth: '220px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={(req.targets || []).map(t => t.label || t.target_id).join(', ')}>
                        {(req.targets || []).map(t => t.target_type === 'section' ? (formatYearSection(t.label || t.target_id) || t.target_id) : (t.label || t.target_id)).join(', ') || '—'}
                      </div>
                    </td>
                    <td>
                      <div className="d-flex align-items-center gap-3">
                        <div className="d-flex flex-column" style={{ fontSize: '0.8rem', minWidth: '90px' }}>
                          <span className="text-success fw-medium">
                            <i className="fa fa-check me-1"></i>
                            {req.submissions?.filter(s => s.status === 'approved' || s.status === 'completed').length || 0} Approved
                          </span>
                          <span className="text-warning text-dark fw-medium">
                            <i className="fa fa-clock me-1"></i>
                            {req.submissions?.filter(s => REVIEWABLE_STATUSES.includes(s.status)).length || 0} Pending
                          </span>
                          <span className="text-secondary fw-medium">
                            <i className="fa fa-minus me-1"></i>
                            {req.submissions?.filter(s => s.status === 'not_submitted' || s.status === 'no_submission').length || 0} Missing
                          </span>
                        </div>
                        <button
                          className="btn btn-sm btn-light text-primary border"
                          onClick={() => {
                            setActiveReqSubmissions(req)
                            setIsSubmissionsModalOpen(true)
                          }}
                          title="View Submissions"
                        >
                          <i className="fa fa-users"></i>
                        </button>
                      </div>
                    </td>
                    <td>
                      <div className="d-flex align-items-center gap-2">
                        {req.attachments && req.attachments.length > 0 && req.attachments.map(att => (
                          <AuthenticatedFileLink 
                            key={att.id}
                            path={att.file_path} 
                            className="btn btn-sm btn-outline-info rounded-pill px-3 text-truncate text-decoration-none me-2"
                            style={{ maxWidth: '200px', display: 'inline-block' }}
                            title="Preview Template File"
                          >
                            <i className="fa fa-eye me-1"></i> {att.file_name || `${req.name} Template`}
                          </AuthenticatedFileLink>
                        ))}
                        {req.drive_link && (
                          <a
                            href={req.drive_link}
                            target="_blank"
                            rel="noreferrer"
                            className="btn btn-sm btn-outline-primary rounded-pill px-3 text-truncate"
                            style={{ maxWidth: '200px', display: 'inline-block' }}
                            title="View Google Drive Link"
                          >
                            <i className="fa fa-link me-1"></i> {req.drive_link.replace(/^https?:\/\//, '')}
                          </a>
                        )}
                        {(!req.attachments?.length && !req.drive_link && !req.template_file_path) && (
                          <span className="text-muted small">—</span>
                        )}
                      </div>
                    </td>
                    <td className="text-end">
                      <button
                        className="btn btn-sm btn-light me-2 text-primary"
                        onClick={() => handleOpenModal(req)}
                        title="Edit Requirement"
                      >
                        <i className="fa fa-edit"></i>
                      </button>
                      <button
                        className="btn btn-sm btn-light text-danger"
                        onClick={() => handleDelete(req.id)}
                        title="Delete Requirement"
                      >
                        <i className="fa fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add/Edit Modal */}
      {isModalOpen && (
        <>
          <div className="modal-backdrop fade show"></div>
          <div className="modal fade show d-block" tabIndex="-1">
            <div className="modal-dialog modal-dialog-centered modal-xl" >
              <div className="modal-content border-0 shadow">
                <div className="modal-header border-bottom-0 pb-0">
                  <h5 className="modal-title fw-bold">
                    {editingReq ? 'Edit Requirement' : 'Add Requirement'}
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setIsModalOpen(false)}></button>
                </div>
                <div className="modal-body">
                  <form id="requirementForm" onSubmit={handleSubmit}>
                    <div className="mb-3">
                      <label className="form-label fw-semibold">Requirement Name</label>
                      <input
                        required
                        type="text"
                        className="form-control"
                        value={formData.name}
                        onChange={e => setFormData({ ...formData, name: e.target.value })}
                        placeholder="e.g. Application Letter"
                      />
                    </div>

                    <div className="mb-3">
                      <label className="form-label fw-semibold">Submission Deadline <span className="text-muted fw-normal">(Optional)</span></label>
                      <input
                        type="datetime-local"
                        className="form-control"
                        value={formData.deadline}
                        onChange={e => setFormData({ ...formData, deadline: e.target.value })}
                      />
                    </div>

                    <div className="mb-3">
                      <label className="form-label fw-semibold">Description <span className="text-muted fw-normal">(Optional)</span></label>
                      <textarea
                        className="form-control"
                        value={formData.description}
                        onChange={e => setFormData({ ...formData, description: e.target.value })}
                        rows="2"
                        placeholder="Provide instructions for students..."
                      ></textarea>
                    </div>

                    <div className="row mb-3">
                      <div className="col-md-12 mb-3">
                        <label className="form-label fw-semibold">Target By</label>
                        <select
                          className="form-select"
                          value={formData.targetType}
                          onChange={e => {
                            setFormData({ ...formData, targetType: e.target.value, selectedTargets: [] })
                            setTargetSearch('')
                          }}
                        >
                          <option value="student">Students</option>
                          <option value="section">Sections</option>
                          {isCoordinator && <option value="program">Programs</option>}
                        </select>
                      </div>
                      <div className="col-md-12">
                        <label className="form-label fw-semibold">Select Targets</label>
                        <input
                          type="search"
                          className="form-control form-control-sm mb-2"
                          placeholder="Search targets…"
                          value={targetSearch}
                          onChange={e => setTargetSearch(e.target.value)}
                        />
                        {visibleTargets.length > 0 && (
                          <button
                            type="button"
                            className="btn btn-link btn-sm px-0 mb-1"
                            onClick={() => {
                              const ids = visibleTargets.map(item => String(item.id))
                              const allSelected = ids.every(id => isTargetSelected(id))
                              setFormData(prev => ({
                                ...prev,
                                selectedTargets: allSelected
                                  ? prev.selectedTargets.filter(t => !ids.includes(String(t)))
                                  : [...new Set([...prev.selectedTargets.map(String), ...ids])],
                              }))
                            }}
                          >
                            {visibleTargets.every(item => isTargetSelected(item.id)) ? 'Clear visible' : 'Select visible'}
                          </button>
                        )}
                        <div className="border rounded p-2" style={{ maxHeight: '200px', overflowY: 'auto' }}>
                          {visibleTargets.map(item => (
                            <div className="form-check" key={`${formData.targetType}_${item.id}`}>
                              <input
                                className="form-check-input"
                                type="checkbox"
                                id={`target_${formData.targetType}_${item.id}`}
                                checked={isTargetSelected(item.id)}
                                onChange={(e) => toggleTarget(item.id, e.target.checked)}
                              />
                              <label className="form-check-label" htmlFor={`target_${formData.targetType}_${item.id}`}>
                                {formData.targetType === 'section' ? (formatYearSection(item.name) || item.name) : item.name}
                                {formData.targetType === 'student' && (
                                  <span className="text-muted small"> ({formatYearSection(item.section) || item.section || 'No Section'})</span>
                                )}
                              </label>
                            </div>
                          ))}
                          {targetList.length === 0 && (
                            <div className="text-muted small py-2">No {formData.targetType}s available.</div>
                          )}
                          {targetList.length > 0 && visibleTargets.length === 0 && (
                            <div className="text-muted small py-2">No matches for “{targetSearch}”.</div>
                          )}
                        </div>
                        {formData.selectedTargets.length > 0 && (
                          <div className="form-text small text-muted mt-1">
                            {formData.selectedTargets.length} target(s) selected
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="mb-3">
                      <label className="form-label fw-semibold">File Upload <span className="text-muted fw-normal">(Optional)</span></label>
                      <input
                        type="file"
                        className="form-control"
                        multiple
                        onChange={e => setFormData({ ...formData, templateFiles: Array.from(e.target.files) })}
                        accept=".doc,.docx,.pdf,.jpg,.jpeg,.png"
                        ref={fileInputRef}
                      />
                      <div className="form-text small text-muted">Upload documents for students to fill out. You can select multiple files.</div>
                      
                      {/* Show newly selected files */}
                      {formData.templateFiles.length > 0 && (
                        <div className="mt-3">
                          <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.75rem', letterSpacing: '0.5px' }}>Files to upload</h6>
                          <div className="d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                            {formData.templateFiles.map((f, i) => (
                              <div key={i} className="d-flex align-items-center">
                                <div className="bg-white border rounded p-1 me-2 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '30px', height: '30px' }}>
                                  <i className="fa fa-file text-secondary"></i>
                                </div>
                                <div className="text-dark fw-medium mb-0 text-truncate" style={{ fontSize: '0.85rem', maxWidth: '300px' }}>{f.name}</div>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Show existing files */}
                      {editingReq && ( (editingReq.attachments && editingReq.attachments.length > 0) || editingReq.drive_link) && (
                        <div className="mt-4 mb-2">
                          <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.75rem', letterSpacing: '0.5px' }}>Attached Files & Links</h6>
                          <div className="d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                            {editingReq.attachments && editingReq.attachments.filter(att => !formData.removeAttachments.includes(att.id)).map(att => (
                              <div key={att.id} className="d-flex align-items-center justify-content-between border-bottom pb-2 mb-1 last-border-none">
                                <div className="d-flex align-items-center">
                                  <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '40px', height: '40px' }}>
                                    <i className="fa fa-file-pdf text-danger fs-5"></i>
                                  </div>
                                  <div>
                                    <div className="text-dark fw-medium mb-0 text-truncate" style={{ fontSize: '0.9rem', maxWidth: '300px' }}>{att.file_name || `${editingReq.name} Template`}</div>
                                    <div className="text-muted small fw-normal">Currently attached file</div>
                                  </div>
                                </div>
                                <button type="button" className="btn btn-sm btn-outline-danger border-0 rounded-circle" onClick={() => setFormData(prev => ({ ...prev, removeAttachments: [...prev.removeAttachments, att.id] }))} title="Remove File">
                                  <i className="fa fa-times"></i>
                                </button>
                              </div>
                            ))}

                            {editingReq.drive_link && (
                              <div className="d-flex align-items-center mt-2">
                                <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '40px', height: '40px' }}>
                                  <i className="fa fa-link text-primary fs-5"></i>
                                </div>
                                <div>
                                  <div className="text-dark fw-medium mb-0 text-truncate" style={{ fontSize: '0.9rem', maxWidth: '300px' }}>{editingReq.drive_link.replace(/^https?:\/\//, '')}</div>
                                  <div className="text-muted small fw-normal">External Link</div>
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      )}
                    </div>
                    <div className="mb-3">
                      <label className="form-label fw-semibold">Link <span className="text-muted fw-normal">(Optional)</span></label>
                      <input
                        type="url"
                        className="form-control"

                        value={formData.driveLink}
                        onChange={e => setFormData({ ...formData, driveLink: e.target.value })}
                      />
                      <div className="form-text small text-muted">Provide a link</div>
                    </div>
                  </form>
                </div>
                <div className="modal-footer border-top-0 pt-0">
                  <button type="button" className="btn btn-light" onClick={() => setIsModalOpen(false)}>Cancel</button>
                  <button type="submit" form="requirementForm" className="btn btn-primary px-4" disabled={submitting}>
                    {submitting ? 'Saving…' : (editingReq ? 'Save Changes' : 'Create Requirement')}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Submissions Modal */}
      {isSubmissionsModalOpen && activeReqSubmissions && (
        <>
          <div className="modal-backdrop fade show"></div>
          <div className="modal fade show d-block" tabIndex="-1">
            <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" style={{ maxWidth: '1000px' }}>
              <div className="modal-content border-0 shadow">
              <div className="modal-header">
                <h5 className="modal-title fw-bold">
                  Submissions for: {activeReqSubmissions.name}
                </h5>
                <button type="button" className="btn-close" onClick={() => setIsSubmissionsModalOpen(false)}></button>
              </div>
              <div className="modal-body">
                <div className="d-flex justify-content-between mb-3 px-1">
                  <span className="text-muted small">
                    Tracking compliance for {activeReqSubmissions.total_assigned} assigned student(s).
                  </span>
                  <span className="fw-bold text-primary small">
                    {activeReqSubmissions.completed_count} Completed
                  </span>
                </div>

                <div className="border rounded">
                  {activeReqSubmissions.submissions && activeReqSubmissions.submissions.length > 0 ? (
                    <table className="table table-hover mb-0 align-middle">
                      <thead className="table-light sticky-top">
                        <tr>
                          <th>Student Name</th>
                          <th>Section</th>
                          <th>Status</th>
                          <th>Submitted At</th>
                          <th>Remarks</th>
                          <th className="text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {activeReqSubmissions.submissions.map((sub, idx) => (
                          <tr key={sub.document_id || sub.student_id || idx}>
                            <td>{sub.student_name}</td>
                            <td>{formatYearSection(sub.section) || '—'}</td>
                            <td>
                              {(() => {
                                const cfg = documentStatusConfig(sub.status)
                                return (
                                  <span className={`badge ${cfg.badge}`}>
                                    <i className={`fa ${cfg.icon} me-1`}></i>{cfg.label}
                                  </span>
                                )
                              })()}
                            </td>
                            <td>
                              {sub.submitted_at ? new Date(sub.submitted_at).toLocaleString() : '—'}
                            </td>
                            <td className="small" style={{ maxWidth: '180px' }}>
                              {sub.remarks ? (
                                <span className={sub.status === 'rejected' ? 'text-danger' : 'text-muted'}>{sub.remarks}</span>
                              ) : '—'}
                            </td>
                            <td className="text-end">
                              <div className="d-flex flex-wrap justify-content-end align-items-center gap-2" onClick={(e) => e.stopPropagation()}>
                                {sub.attachments && sub.attachments.length > 0 && sub.attachments.map(att => (
                                  <AuthenticatedFileLink key={att.id} path={att.file_path} className="text-decoration-none d-inline-flex align-items-center fw-medium text-start border bg-light rounded-3 p-1 pe-3 shadow-sm transition-hover">
                                    <div className="bg-white border rounded p-2 me-2 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '35px', height: '35px' }}>
                                      <i className="fa fa-file-pdf text-success fs-5"></i>
                                    </div>
                                    <div style={{ lineHeight: '1.2' }}>
                                      <div className="text-dark mb-0 text-truncate" style={{ fontSize: '0.85rem', maxWidth: '200px' }}>{att.file_name || 'Submission'}</div>
                                      <div className="text-muted small fw-normal" style={{ fontSize: '0.7rem' }}>Click to preview</div>
                                    </div>
                                  </AuthenticatedFileLink>
                                ))}
                                {sub.drive_link && (
                                  <a href={sub.drive_link} target="_blank" rel="noreferrer" className="text-decoration-none d-inline-flex align-items-center fw-medium text-start border bg-light rounded-3 p-1 pe-3 shadow-sm transition-hover">
                                    <div className="bg-white border rounded p-2 me-2 shadow-sm d-flex justify-content-center align-items-center" style={{ width: '35px', height: '35px' }}>
                                      <i className="fa fa-link text-primary fs-5"></i>
                                    </div>
                                    <div style={{ lineHeight: '1.2' }}>
                                      <div className="text-dark mb-0 text-truncate" style={{ fontSize: '0.85rem', maxWidth: '120px' }}>{sub.drive_link.replace('https://', '').replace('http://', '')}</div>
                                      <div className="text-muted small fw-normal" style={{ fontSize: '0.7rem' }}>External Link</div>
                                    </div>
                                  </a>
                                )}
                                {REVIEWABLE_STATUSES.includes(sub.status) && sub.document_id && (
                                  <button
                                    type="button"
                                    className="btn btn-sm btn-primary"
                                    onClick={() => setReviewingDoc(sub.document_id)}
                                  >
                                    <i className="fa fa-gavel me-1"></i>Review
                                  </button>
                                )}
                                {sub.status === 'not_submitted' || sub.status === 'no_submission' ? (
                                  <span className="text-muted small">Waiting for upload</span>
                                ) : null}
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  ) : (
                    <div className="text-center py-4 text-muted small">
                      No students are currently targeted by this requirement.
                    </div>
                  )}
                </div>
              </div>
              <div className="modal-footer border-top-0 pt-0 mt-2">
                <button type="button" className="btn btn-light" onClick={() => setIsSubmissionsModalOpen(false)}>Close</button>
              </div>
            </div>
            </div>
          </div>
        </>
      )}

      {/* Review Modal */}
      {
        reviewingDoc && (
          <>
            <div className="modal-backdrop fade show" style={{ zIndex: 1060 }}></div>
            <div className="modal fade show d-block" tabIndex="-1" style={{ zIndex: 1065 }}>
              <div className=" modal-dialog modal-dialog-centered">
                <div className="modal-content border-0 shadow">
                  <div className="modal-header border-bottom-0 pb-0">
                    <h5 className="modal-title fw-bold">Review Submission</h5>
                    <button type="button" className="btn-close" onClick={() => { setReviewingDoc(null); setReviewRemarks(''); }}></button>
                  </div>
                  <div className="modal-body">
                    <div className="mb-3">
                      <label className="form-label fw-semibold">Remarks (Optional)</label>
                      <textarea
                        className="form-control"
                        rows="3"
                        placeholder="Add any feedback for the student..."
                        value={reviewRemarks}
                        onChange={e => setReviewRemarks(e.target.value)}
                      ></textarea>
                    </div>
                  </div>
                  <div className="modal-footer border-top-0 pt-0">
                    <button type="button" className="btn btn-light" onClick={() => { setReviewingDoc(null); setReviewRemarks(''); }} disabled={reviewingBusy}>Cancel</button>
                    <button type="button" className="btn btn-danger" onClick={() => handleReview(reviewingDoc, 'reject')} disabled={reviewingBusy}>
                      {reviewingBusy ? 'Saving…' : 'Reject'}
                    </button>
                    <button type="button" className="btn btn-success" onClick={() => handleReview(reviewingDoc, 'approve')} disabled={reviewingBusy}>
                      {reviewingBusy ? 'Saving…' : 'Approve'}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </>
        )
      }
    </Wrapper >
  )
}











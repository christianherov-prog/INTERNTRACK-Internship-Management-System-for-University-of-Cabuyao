import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect, useRef } from 'react'
import api from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'
import toast from 'react-hot-toast'
import Layout from '../../components/Layout'
import { useConfirm } from '../../contexts/ConfirmContext'

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
    templateFile: null,
    deadline: '',
  })

  // Review State
  const [reviewingDoc, setReviewingDoc] = useState(null)
  const [reviewRemarks, setReviewRemarks] = useState('')

  const fileInputRef = useRef(null)

  useEffect(() => {
    fetchRequirements()
    fetchOptions()
  }, [])

  const fetchOptions = async () => {
    try {
      const { data } = await api.get(`/${user.role}/requirements/options`)
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
      const { data } = await api.get(`/${user.role}/requirements`)
      setRequirements(data.data || [])
    } catch (err) {
      toast.error('Failed to load requirements')
    } finally {
      setIsLoading(false)
    }
  }

  const handlePreviewSubmission = async (filePath) => {
    try {
      const response = await api.get(`/files/download?path=${encodeURIComponent(filePath)}`, { responseType: 'blob' })
      const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/pdf' })
      const url = window.URL.createObjectURL(blob)
      window.open(url, '_blank')
    } catch (err) {
      alert("Failed to preview file.")
    }
  }

  const handleOpenModal = (req = null) => {
    if (req) {
      setEditingReq(req)
      setFormData({
        name: req.name,
        description: req.description || '',
        targetType: req.targets && req.targets.length > 0 ? req.targets[0].target_type : 'student',
        selectedTargets: req.targets ? req.targets.map(t => t.target_id) : [],
        templateFile: null,
        driveLink: req.drive_link || '',
        deadline: req.deadline ? new Date(req.deadline).toISOString().substring(0, 16) : '',
      })
    } else {
      setEditingReq(null)
      setFormData({ name: '', description: '', targetType: 'student', selectedTargets: [], templateFile: null, driveLink: '', deadline: '' })
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

    if (formData.templateFile) {
      form.append('template_file', formData.templateFile)
    }
    if (formData.driveLink) {
      form.append('drive_link', formData.driveLink)
    }

    setSubmitting(true)
    try {
      if (editingReq) {
        await api.post(`/${user.role}/requirements/${editingReq.id}`, form)
        toast.success('Requirement updated successfully')
      } else {
        await api.post(`/${user.role}/requirements`, form)
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
    if (!docId) return
    try {
      await api.post(`/${user.role}/documents/${docId}/review`, {
        action,
        remarks: reviewRemarks
      })
      toast.success(`Document ${action}d successfully.`)
      setReviewingDoc(null)
      setReviewRemarks('')
      // Refresh the modal data by re-fetching requirements
      fetchRequirements()
      // Note: activeReqSubmissions won't auto-update without re-setting it, 
      // but we can just close/re-open or just fetchRequirements and let the user re-open.
      // For simplicity, close modal to force refresh on next open:
      setIsSubmissionsModalOpen(false)
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to review document')
    }
  }

  const handleDelete = async (id) => {
    if (!(await confirm({ message: 'Are you sure you want to delete this requirement?', variant: 'danger' }))) return
    try {
      await api.delete(`/${user.role}/requirements/${id}`)
      toast.success('Requirement deleted')
      fetchRequirements()
    } catch (err) {
      toast.error('Failed to delete requirement')
    }
  }

  const handlePreviewTemplate = async (id) => {
    try {
      const response = await api.get(`/${user.role}/requirements/${id}/template?preview=1`, { responseType: 'blob' })
      const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/pdf' })
      const url = window.URL.createObjectURL(blob)
      window.open(url, '_blank')
    } catch (err) {
      toast.error('Failed to preview template')
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
                        {req.targets?.length || 0} Rule(s)
                      </div>
                      <div className="small text-muted" style={{ maxWidth: '150px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {req.targets?.map(t => t.target_id).join(', ') || '—'}
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
                            {req.submissions?.filter(s => s.status === 'pending').length || 0} Pending
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
                        {req.template_file_path && (
                          <button
                            className="btn btn-sm btn-outline-info"
                            onClick={() => handlePreviewTemplate(req.id)}
                            title="Preview Template File"
                          >
                            <i className="fa fa-eye me-1"></i> Preview
                          </button>
                        )}
                        {req.drive_link && (
                          <a
                            href={req.drive_link}
                            target="_blank"
                            rel="noreferrer"
                            className="btn btn-sm btn-outline-primary"
                            title="View Google Drive Link"
                          >
                            <i className="fa fa-link me-1"></i> Link
                          </a>
                        )}
                        {(!req.template_file_path && !req.drive_link) && (
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
            <div className="modal-dialog modal-dialog-centered">
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
                          onChange={e => setFormData({ ...formData, targetType: e.target.value, selectedTargets: [] })}
                        >
                          <option value="student">Students</option>
                          <option value="section">Sections</option>
                          {isCoordinator && <option value="program">Programs</option>}
                        </select>
                      </div>
                      <div className="col-md-12">
                        <label className="form-label fw-semibold">Select Targets</label>
                        <div className="border rounded p-2" style={{ maxHeight: '200px', overflowY: 'auto' }}>
                          {formData.targetType === 'student' && options.students.map(s => (
                            <div className="form-check" key={s.id}>
                              <input
                                className="form-check-input"
                                type="checkbox"
                                id={`target_student_${s.id}`}
                                checked={formData.selectedTargets.includes(s.id)}
                                onChange={(e) => {
                                  const newTargets = e.target.checked
                                    ? [...formData.selectedTargets, s.id]
                                    : formData.selectedTargets.filter(t => t !== s.id);
                                  setFormData({ ...formData, selectedTargets: newTargets })
                                }}
                              />
                              <label className="form-check-label" htmlFor={`target_student_${s.id}`}>
                                {s.name} <span className="text-muted small">({s.section || 'No Section'})</span>
                              </label>
                            </div>
                          ))}

                          {formData.targetType === 'student' && options.students.length === 0 && (
                            <div className="text-muted small py-2">No students available.</div>
                          )}

                          {formData.targetType === 'section' && options.sections.map(s => (
                            <div className="form-check" key={s.id}>
                              <input
                                className="form-check-input"
                                type="checkbox"
                                id={`target_section_${s.id}`}
                                checked={formData.selectedTargets.includes(s.id)}
                                onChange={(e) => {
                                  const newTargets = e.target.checked
                                    ? [...formData.selectedTargets, s.id]
                                    : formData.selectedTargets.filter(t => t !== s.id);
                                  setFormData({ ...formData, selectedTargets: newTargets })
                                }}
                              />
                              <label className="form-check-label" htmlFor={`target_section_${s.id}`}>
                                {s.name}
                              </label>
                            </div>
                          ))}

                          {formData.targetType === 'section' && options.sections.length === 0 && (
                            <div className="text-muted small py-2">No sections available.</div>
                          )}

                          {formData.targetType === 'program' && options.programs.map(p => (
                            <div className="form-check" key={p.id}>
                              <input
                                className="form-check-input"
                                type="checkbox"
                                id={`target_program_${p.id}`}
                                checked={formData.selectedTargets.includes(p.id)}
                                onChange={(e) => {
                                  const newTargets = e.target.checked
                                    ? [...formData.selectedTargets, p.id]
                                    : formData.selectedTargets.filter(t => t !== p.id);
                                  setFormData({ ...formData, selectedTargets: newTargets })
                                }}
                              />
                              <label className="form-check-label" htmlFor={`target_program_${p.id}`}>
                                {p.name}
                              </label>
                            </div>
                          ))}

                          {formData.targetType === 'program' && options.programs.length === 0 && (
                            <div className="text-muted small py-2">No programs available.</div>
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
                        onChange={e => setFormData({ ...formData, templateFile: e.target.files[0] })}
                        accept=".doc,.docx,.pdf"
                        ref={fileInputRef}
                      />
                      <div className="form-text small text-muted">Upload a document for students to fill out.</div>
                      {editingReq?.template_file_path && !formData.templateFile && (
                        <div className="alert alert-info py-2 px-3 mt-2 mb-0 d-flex align-items-center">
                          <i className="fa fa-info-circle me-2"></i>
                          <span className="small">A document is already attached to this requirement. Uploading a new file will replace it.</span>
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
            <div className="modal-dialog modal-dialog-centered modal-lg">
              <div className="modal-content border-0 shadow">
                <div className="modal-header border-bottom-0 pb-0">
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

                  <div className="border rounded" style={{ maxHeight: '400px', overflowY: 'auto' }}>
                    {activeReqSubmissions.submissions && activeReqSubmissions.submissions.length > 0 ? (
                      <table className="table table-hover mb-0 align-middle">
                        <thead className="table-light sticky-top">
                          <tr>
                            <th>Student Name</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th className="text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          {activeReqSubmissions.submissions.map((sub, idx) => (
                            <tr key={idx}>
                              <td>{sub.student_name}</td>
                              <td>{formatYearSection(sub.section) || '—'}</td>
                              <td>
                                {sub.status === 'approved' && <span className="badge bg-success"><i className="fa fa-check me-1"></i>Approved</span>}
                                {sub.status === 'completed' && <span className="badge bg-success"><i className="fa fa-check me-1"></i>Approved</span>}
                                {sub.status === 'pending' && <span className="badge bg-warning text-dark"><i className="fa fa-clock me-1"></i>Pending</span>}
                                {sub.status === 'rejected' && <span className="badge bg-danger"><i className="fa fa-times me-1"></i>Rejected</span>}
                                {sub.status === 'no_submission' && <span className="badge bg-dark"><i className="fa fa-ban me-1"></i>Missed Deadline</span>}
                                {sub.status === 'not_submitted' && <span className="badge bg-secondary">Not Submitted</span>}
                              </td>
                              <td>
                                {sub.submitted_at ? new Date(sub.submitted_at).toLocaleDateString() : '—'}
                              </td>
                              <td className="text-end">
                                {sub.file_path && (
                                  <button onClick={() => handlePreviewSubmission(sub.file_path)} className="btn btn-sm btn-outline-success me-2" title="Preview File">
                                    <i className="fa fa-file-pdf"></i>
                                  </button>
                                )}
                                {sub.drive_link && (
                                  <a href={sub.drive_link} target="_blank" rel="noreferrer" className="btn btn-sm btn-outline-info me-2" title="Link">
                                    <i className="fa fa-link"></i>
                                  </a>
                                )}
                                {sub.status === 'pending' && sub.document_id && (
                                  <button
                                    className="btn btn-sm btn-primary"
                                    onClick={() => setReviewingDoc(sub.document_id)}
                                  >
                                    Review
                                  </button>
                                )}
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
      {reviewingDoc && (
        <>
          <div className="modal-backdrop fade show" style={{ zIndex: 1060 }}></div>
          <div className="modal fade show d-block" tabIndex="-1" style={{ zIndex: 1065 }}>
            <div className="modal-dialog modal-dialog-centered">
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
                  <button type="button" className="btn btn-light" onClick={() => { setReviewingDoc(null); setReviewRemarks(''); }}>Cancel</button>
                  <button type="button" className="btn btn-danger" onClick={() => handleReview(reviewingDoc, 'reject')}>Reject</button>
                  <button type="button" className="btn btn-success" onClick={() => handleReview(reviewingDoc, 'approve')}>Approve</button>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </Wrapper>
  )
}
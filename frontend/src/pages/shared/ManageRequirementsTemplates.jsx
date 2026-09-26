import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect, useRef } from 'react'
import api from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'
import toast from 'react-hot-toast'
import Layout from '../../components/Layout'
import { useConfirm } from '../../contexts/ConfirmContext'
import { AuthenticatedFileLink } from '../../components/AuthenticatedFile'
import { documentStatusConfig } from '../../utils/documentStatus'
import { useCachedPage } from '../../hooks/useCachedPage'
import InternTrackLoader from '../../components/InternTrackLoader'
import AppModal from '../../components/modals/AppModal'
import { invalidateStudentDocuments } from '../../utils/pageCache'
import { UPLOAD_MAX_FILES, UPLOAD_MAX_MB } from '../../config/uploads'
import { formatManilaDateTime } from '../../utils/manilaTime'
import {
  formatFileSize,
  uploadErrorMessage,
  uploadLimitHint,
  validateUploadFiles,
} from '../../utils/uploadValidation'

const REVIEWABLE_STATUSES = ['pending', 'pending_review', 'pending_faculty', 'under_review', 'resubmitted']
const REQUIREMENT_FILE_TYPES = 'DOC, DOCX, PDF, JPG, PNG'

export default function ManageRequirementsTemplates({ embedded = false }) {
  const confirm = useConfirm()
  const { user } = useAuth()
  const { loading, seed, run } = useCachedPage(`requirements:${user?.role || 'staff'}`)
  const [requirements, setRequirements] = useState(() => seed ?? [])
  const [options, setOptions] = useState({ students: [], sections: [], programs: [] })
  const isCoordinator = user?.role === 'coordinator'
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

  const [fileError, setFileError] = useState(null)
  const fileInputRef = useRef(null)
  const rolePath = user?.role

  const applyTemplateFiles = (incoming) => {
    const check = validateUploadFiles(incoming)
    if (!check.ok) {
      setFileError(check.error)
      toast.error(check.error)
      // Keep only valid files so the user can remove/replace without restarting the form.
      const valid = (Array.isArray(incoming) ? incoming : []).filter(
        (f) => !(check.invalidFiles || []).includes(f) && (f.size || 0) <= (UPLOAD_MAX_MB * 1024 * 1024)
      )
      const recheck = validateUploadFiles(valid)
      setFormData((prev) => ({ ...prev, templateFiles: recheck.ok ? recheck.files : [] }))
      return
    }
    setFileError(null)
    setFormData((prev) => ({ ...prev, templateFiles: check.files }))
  }

  const removeSelectedFile = (index) => {
    setFormData((prev) => {
      const next = prev.templateFiles.filter((_, i) => i !== index)
      const check = validateUploadFiles(next)
      setFileError(check.ok ? null : check.error)
      return { ...prev, templateFiles: check.ok ? check.files : next }
    })
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

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

  const fetchRequirements = () => {
    if (!rolePath) return
    run(() => api.get(`/${rolePath}/requirements`).then(({ data }) => data.data || []))
      .then((next) => {
        if (next) {
          setRequirements(next)
          setActiveReqSubmissions((current) => {
            if (!current) return current
            return next.find((r) => r.id === current.id) || current
          })
        }
      })
      .catch(() => {
        toast.error('Failed to load requirements')
      })
  }

  const handleOpenModal = (req = null) => {
    setFileError(null)
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
    const isSystemEdit = !!(editingReq?.is_system)

    if (!isSystemEdit && targetItems.length === 0) {
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
    if (!isSystemEdit) {
      targetItems.forEach((t, i) => {
        form.append(`targets[${i}][type]`, t.type)
        form.append(`targets[${i}][id]`, t.id)
      })
    }

    if (formData.templateFiles && formData.templateFiles.length > 0) {
      const check = validateUploadFiles(formData.templateFiles)
      if (!check.ok) {
        setFileError(check.error)
        toast.error(check.error)
        return
      }
      check.files.forEach((file) => {
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
      setFileError(null)
      fetchRequirements()
    } catch (err) {
      toast.error(uploadErrorMessage(err, 'Failed to save requirement'))
    } finally {
      setSubmitting(false)
    }
  }

  const handleReview = async (docId, action) => {
    if (!docId || reviewingBusy) return
    const student = reviewingDoc?.student_name
      || reviewingDoc?.student?.name
      || reviewingDoc?.uploader_name
      || 'this student'
    const docLabel = reviewingDoc?.document_type || reviewingDoc?.requirement_name || 'document'
    const verb = action === 'approve' ? 'Approve' : 'Reject'
    const ok = await confirm({
      title: `${verb} document submission?`,
      message: `${verb} "${docLabel}" submitted by ${student}?`,
      confirmLabel: verb,
      variant: action === 'approve' ? 'primary' : 'danger',
    })
    if (!ok) return

    setReviewingBusy(true)
    try {
      await api.post(`/${rolePath}/documents/${docId}/review`, {
        action,
        remarks: reviewRemarks
      })
      toast.success(`Document ${action === 'approve' ? 'approved' : 'rejected'}. The student has been notified.`)
      setReviewingDoc(null)
      setReviewRemarks('')
      invalidateStudentDocuments()
      window.dispatchEvent(new CustomEvent('interntrack:document-reviewed'))
      fetchRequirements()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to review document')
    } finally {
      setReviewingBusy(false)
    }
  }

  const handleDelete = async (id) => {
    if (!(await confirm({
      title: 'Delete requirement?',
      message: 'Delete this requirement template? Students will no longer see it as required.',
      confirmLabel: 'Delete',
      variant: 'danger',
    }))) return
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

        {loading ? (
          <div className="text-center py-5">
            <InternTrackLoader />
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
                      <div className="fw-bold text-dark">
                        {req.name}
                        {req.is_system ? <span className="badge bg-primary-subtle text-primary ms-2">Standard</span> : null}
                      </div>
                      {req.description && (
                        <div className="text-muted small mt-1" style={{ maxWidth: '300px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          {req.description}
                        </div>
                      )}
                    </td>
                    <td>
                      <span className="badge bg-secondary text-capitalize">
                        {req.target_type_label || req.targets?.[0]?.target_type || (req.is_system ? 'All eligible students' : 'Unknown')}
                      </span>
                    </td>
                    <td>
                      <div className="small text-muted fw-medium">
                        {req.total_assigned ?? req.targets?.length ?? 0}{' '}
                        {(req.total_assigned ?? req.targets?.length ?? 0) === 1 ? 'student' : 'students'}
                      </div>
                      <div className="small text-dark" style={{ maxWidth: '220px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={(req.targets || []).map(t => t.label || t.target_id).join(', ')}>
                        {req.is_system || !(req.targets || []).length
                          ? 'Auto-applies to eligible students'
                          : (req.targets || []).map(t => t.target_type === 'section' ? (formatYearSection(t.label || t.target_id) || t.target_id) : (t.label || t.target_id)).join(', ')}
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
                          <i className="fa fa-users"></i> View Submissions
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
                      {!req.is_system && (
                        <button
                          className="btn btn-sm btn-light text-danger"
                          onClick={() => handleDelete(req.id)}
                          title="Delete Requirement"
                        >
                          <i className="fa fa-trash"></i>
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add/Edit Modal */}
      <AppModal
        open={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        size="lg"
        title={editingReq ? 'Edit Requirement' : 'Add Requirement'}
        icon="fa-file-circle-plus"
        busy={submitting}
        onSubmit={handleSubmit}
        testId="requirement-form-modal"
        footer={(
          <>
            <button type="button" className="btn btn-light" onClick={() => setIsModalOpen(false)} disabled={submitting}>Cancel</button>
            <button type="submit" className="btn btn-primary px-4" disabled={submitting || !!fileError}>
              {submitting ? 'Saving…' : (editingReq ? 'Save Changes' : 'Create Requirement')}
            </button>
          </>
        )}
      >
        <div className="it-form-grid">
          <div>
            <label className="form-label fw-semibold" htmlFor="requirement-name">Requirement Name</label>
            <input maxLength={255}
              id="requirement-name"
              required
              type="text"
              className="form-control"
              value={formData.name}
              onChange={e => setFormData({ ...formData, name: e.target.value })}
              placeholder="Requirement Name"
            />
          </div>

          <div>
            <label className="form-label fw-semibold" htmlFor="requirement-deadline">Submission Deadline <span className="text-muted fw-normal">(Optional)</span></label>
            <input
              id="requirement-deadline"
              type="datetime-local"
              className="form-control"
              value={formData.deadline}
              onChange={e => setFormData({ ...formData, deadline: e.target.value })}
            />
          </div>

          <div className={editingReq?.is_system ? 'it-span-2' : undefined}>
            <label className="form-label fw-semibold" htmlFor="requirement-description">Description <span className="text-muted fw-normal">(Optional)</span></label>
            <textarea maxLength={2000}
              id="requirement-description"
              className="form-control"
              value={formData.description}
              onChange={e => setFormData({ ...formData, description: e.target.value })}
              rows="3"
              placeholder="Instructions"
            ></textarea>
          </div>

          {editingReq?.is_system ? (
            <div className="alert alert-light border small mb-0 it-span-2">
              This is a <strong>standard</strong> InternTrack requirement. It automatically applies to all eligible students in your scope. Targeting is not required.
            </div>
          ) : (
            <>
              <div>
                <label className="form-label fw-semibold" htmlFor="requirement-target-type">Target By</label>
                <select
                  id="requirement-target-type"
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
                <div className="form-text small text-muted">
                  Choose who must submit this requirement, then pick them below.
                </div>
              </div>

              <div className="it-span-2">
                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                  <span className="form-label fw-semibold mb-0" id="requirement-targets-label">Select Targets</span>
                  <span className={`badge ${formData.selectedTargets.length ? 'bg-success' : 'bg-secondary-subtle text-secondary-emphasis'}`} aria-live="polite">
                    {formData.selectedTargets.length} selected
                  </span>
                </div>
                <div className="it-target-picker" role="group" aria-labelledby="requirement-targets-label">
                  <div className="it-target-picker__toolbar">
                    <input maxLength={100}
                      type="search"
                      className="form-control form-control-sm it-target-picker__search"
                      placeholder={`Search ${formData.targetType === 'student' ? 'students or sections' : `${formData.targetType}s`}`}
                      aria-label="Search targets"
                      value={targetSearch}
                      onChange={e => setTargetSearch(e.target.value)}
                    />
                    {visibleTargets.length > 0 && (
                      <button
                        type="button"
                        className="btn btn-outline-primary btn-sm"
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
                  </div>
                  {visibleTargets.length > 0 && (
                    <div className="it-target-picker__list">
                      {visibleTargets.map(item => (
                        <label className="it-target-picker__item" key={`${formData.targetType}_${item.id}`} htmlFor={`target_${formData.targetType}_${item.id}`}>
                          <input
                            className="form-check-input"
                            type="checkbox"
                            id={`target_${formData.targetType}_${item.id}`}
                            checked={isTargetSelected(item.id)}
                            onChange={(e) => toggleTarget(item.id, e.target.checked)}
                          />
                          <span className="it-target-picker__text">
                            {formData.targetType === 'section' ? (formatYearSection(item.name) || item.name) : item.name}
                            {formData.targetType === 'student' && (
                              <span className="it-target-picker__meta">{formatYearSection(item.section) || item.section || 'No Section'}</span>
                            )}
                          </span>
                        </label>
                      ))}
                    </div>
                  )}
                  {targetList.length === 0 && (
                    <div className="it-target-picker__status">No {formData.targetType}s available.</div>
                  )}
                  {targetList.length > 0 && visibleTargets.length === 0 && (
                    <div className="it-target-picker__status">No matches for “{targetSearch}”.</div>
                  )}
                </div>
              </div>
            </>
          )}

          <div>
            <label className="form-label fw-semibold" htmlFor="requirement-template-files">
              File Upload <span className="text-muted fw-normal">(Optional)</span>
            </label>
            <input
              id="requirement-template-files"
              type="file"
              className={`form-control ${fileError ? 'is-invalid' : ''}`}
              multiple
              onChange={(e) => applyTemplateFiles(Array.from(e.target.files || []))}
              accept=".doc,.docx,.pdf,.jpg,.jpeg,.png"
              ref={fileInputRef}
              aria-describedby="requirement-file-hint requirement-file-error"
            />
            <div id="requirement-file-hint" className="form-text small text-muted">
              {uploadLimitHint(REQUIREMENT_FILE_TYPES)}. Up to {UPLOAD_MAX_FILES} files.
            </div>
            {fileError && (
              <div id="requirement-file-error" className="invalid-feedback d-block" role="alert" aria-live="polite">
                <i className="fa fa-triangle-exclamation me-1" aria-hidden="true"></i>
                {fileError}
              </div>
            )}

            {/* Show newly selected files */}
            {formData.templateFiles.length > 0 && (
              <div className="mt-3">
                <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.75rem', letterSpacing: '0.5px' }}>Files to upload</h6>
                <div className="d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                  {formData.templateFiles.map((f, i) => {
                    const over = (f.size || 0) > UPLOAD_MAX_MB * 1024 * 1024
                    return (
                      <div key={`${f.name}-${i}`} className="d-flex align-items-center justify-content-between gap-2">
                        <div className="d-flex align-items-center min-w-0">
                          <div className="bg-white border rounded p-1 me-2 shadow-sm d-flex justify-content-center align-items-center flex-shrink-0" style={{ width: '30px', height: '30px' }}>
                            <i className={`fa ${over ? 'fa-triangle-exclamation text-danger' : 'fa-file text-secondary'}`} aria-hidden="true"></i>
                          </div>
                          <div className="min-w-0">
                            <div className="text-dark fw-medium mb-0 text-truncate" style={{ fontSize: '0.85rem', maxWidth: '260px' }} title={f.name}>{f.name}</div>
                            <div className={`small ${over ? 'text-danger' : 'text-muted'}`}>
                              {formatFileSize(f.size)}
                              {over ? ` · Exceeds ${UPLOAD_MAX_MB} MB` : ''}
                            </div>
                          </div>
                        </div>
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-danger border-0"
                          onClick={() => removeSelectedFile(i)}
                          aria-label={`Remove ${f.name}`}
                          title="Remove"
                        >
                          <i className="fa fa-times" aria-hidden="true"></i>
                        </button>
                      </div>
                    )
                  })}
                </div>
              </div>
            )}
          </div>

          <div>
            <label className="form-label fw-semibold" htmlFor="requirement-link">Link <span className="text-muted fw-normal">(Optional)</span></label>
            <input maxLength={2048}
              id="requirement-link"
              type="url"
              className="form-control"
              placeholder="https://"
              value={formData.driveLink}
              onChange={e => setFormData({ ...formData, driveLink: e.target.value })}
            />
            <div className="form-text small text-muted">Provide a link</div>
          </div>

          {/* Show existing files */}
          {editingReq && ((editingReq.attachments && editingReq.attachments.length > 0) || editingReq.drive_link) && (
            <div className="it-span-2">
              <h6 className="fw-bold text-secondary mb-2 text-uppercase" style={{ fontSize: '0.75rem', letterSpacing: '0.5px' }}>Attached Files & Links</h6>
              <div className="d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                {editingReq.attachments && editingReq.attachments.filter(att => !formData.removeAttachments.includes(att.id)).map(att => (
                  <div key={att.id} className="d-flex align-items-center justify-content-between gap-2 border-bottom pb-2 mb-1 last-border-none">
                    <div className="d-flex align-items-center min-w-0">
                      <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center flex-shrink-0" style={{ width: '40px', height: '40px' }}>
                        <i className="fa fa-file-pdf text-danger fs-5"></i>
                      </div>
                      <div className="min-w-0">
                        <div className="text-dark fw-medium mb-0 text-truncate" style={{ fontSize: '0.9rem' }} title={att.file_name || undefined}>{att.file_name || `${editingReq.name} Template`}</div>
                        <div className="text-muted small fw-normal">Currently attached file</div>
                      </div>
                    </div>
                    <button type="button" className="btn btn-sm btn-outline-danger border-0 rounded-circle flex-shrink-0" onClick={() => setFormData(prev => ({ ...prev, removeAttachments: [...prev.removeAttachments, att.id] }))} title="Remove File" aria-label={`Remove ${att.file_name || 'attached file'}`}>
                      <i className="fa fa-times"></i>
                    </button>
                  </div>
                ))}

                {editingReq.drive_link && (
                  <div className="d-flex align-items-center mt-2 min-w-0">
                    <div className="bg-white border rounded p-2 me-3 shadow-sm d-flex justify-content-center align-items-center flex-shrink-0" style={{ width: '40px', height: '40px' }}>
                      <i className="fa fa-link text-primary fs-5"></i>
                    </div>
                    <div className="min-w-0">
                      <div className="text-dark fw-medium mb-0 text-truncate" style={{ fontSize: '0.9rem' }}>{editingReq.drive_link.replace(/^https?:\/\//, '')}</div>
                      <div className="text-muted small fw-normal">External Link</div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </AppModal>

      {/* Submissions Modal */}
      <AppModal
        open={isSubmissionsModalOpen && !!activeReqSubmissions}
        onClose={() => setIsSubmissionsModalOpen(false)}
        size="data"
        title={`Submissions for: ${activeReqSubmissions?.name ?? ''}`}
        icon="fa-users"
        closeOnBackdrop
        fillBody
        testId="requirement-submissions-modal"
        footer={<button type="button" className="btn btn-light" onClick={() => setIsSubmissionsModalOpen(false)}>Close</button>}
      >
        <div className="d-flex flex-wrap justify-content-between gap-2 px-1">
          <span className="text-muted small">
            Tracking compliance for {activeReqSubmissions?.total_assigned ?? 0} assigned student(s).
          </span>
          <span className="fw-bold text-primary small">
            {activeReqSubmissions?.completed_count ?? 0} Completed
          </span>
        </div>

        {activeReqSubmissions?.submissions?.length > 0 ? (
          <div className="it-modal__table-wrap">
            <table className="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th scope="col" className="it-col-name">Student Name</th>
                  <th scope="col">Section</th>
                  <th scope="col">Status</th>
                  <th scope="col">Submitted At</th>
                  <th scope="col">File</th>
                  <th scope="col">Reviewer</th>
                  <th scope="col">Remarks</th>
                  <th scope="col" className="text-end it-col-sticky-end">Action</th>
                </tr>
              </thead>
              <tbody>
                {activeReqSubmissions.submissions.map((sub, idx) => {
                  const cfg = documentStatusConfig(sub.status)
                  const hasFiles = (sub.attachments && sub.attachments.length > 0) || sub.drive_link
                  return (
                    <tr key={sub.document_id || sub.student_id || idx}>
                      <td className="it-col-name">
                        <div className="fw-semibold text-dark" style={{ overflowWrap: 'anywhere' }}>{sub.student_name}</div>
                        {sub.student_id_number && <div className="text-muted small">{sub.student_id_number}</div>}
                      </td>
                      <td className="it-col-nowrap">{formatYearSection(sub.section) || '—'}</td>
                      <td className="it-col-nowrap">
                        <span className={`badge ${cfg.badge}`}>
                          <i className={`fa ${cfg.icon} me-1`}></i>{cfg.label}
                        </span>
                      </td>
                      <td className="it-col-nowrap small">
                        {formatManilaDateTime(sub.submitted_at)}
                      </td>
                      <td style={{ minWidth: '12rem' }}>
                        {hasFiles ? (
                          <div className="d-flex flex-column gap-1">
                            {sub.attachments && sub.attachments.map(att => (
                              <AuthenticatedFileLink key={att.id} path={att.file_path} className="text-decoration-none d-inline-flex align-items-center gap-2 fw-medium text-start border bg-light rounded-3 px-2 py-1 transition-hover" title={att.file_name || 'Submission'}>
                                <i className="fa fa-file-pdf text-success" aria-hidden="true"></i>
                                <span className="text-dark text-truncate" style={{ fontSize: '0.84rem', maxWidth: '14rem' }}>{att.file_name || 'Submission'}</span>
                              </AuthenticatedFileLink>
                            ))}
                            {sub.drive_link && (
                              <a href={sub.drive_link} target="_blank" rel="noreferrer" className="text-decoration-none d-inline-flex align-items-center gap-2 fw-medium text-start border bg-light rounded-3 px-2 py-1 transition-hover" title={sub.drive_link}>
                                <i className="fa fa-link text-primary" aria-hidden="true"></i>
                                <span className="text-dark text-truncate" style={{ fontSize: '0.84rem', maxWidth: '14rem' }}>{sub.drive_link.replace(/^https?:\/\//, '')}</span>
                              </a>
                            )}
                          </div>
                        ) : (
                          <span className="text-muted small">{sub.status === 'not_submitted' || sub.status === 'no_submission' ? 'Waiting for upload' : '—'}</span>
                        )}
                      </td>
                      <td className="small" style={{ minWidth: '9rem' }}>
                        {sub.reviewed_by_name ? (
                          <>
                            <div className="text-dark">{sub.reviewed_by_name}</div>
                            {sub.reviewed_by_role && <div className="text-muted">{sub.reviewed_by_role}</div>}
                          </>
                        ) : '—'}
                      </td>
                      <td className="small it-col-wrap">
                        {sub.remarks ? (
                          <span className={sub.status === 'rejected' ? 'text-danger' : 'text-muted'}>{sub.remarks}</span>
                        ) : '—'}
                      </td>
                      <td className="text-end it-col-nowrap it-col-sticky-end">
                        {REVIEWABLE_STATUSES.includes(sub.status) && sub.document_id ? (
                          <button
                            type="button"
                            className="btn btn-sm btn-primary"
                            onClick={() => setReviewingDoc(sub.document_id)}
                          >
                            <i className="fa fa-gavel me-1"></i>Review
                          </button>
                        ) : <span className="text-muted small">—</span>}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="it-modal__empty border rounded">
            <i className="fa fa-users-slash fa-2x"></i>
            No students are currently targeted by this requirement.
          </div>
        )}
      </AppModal>

      {/* Review Modal */}
      <AppModal
        open={!!reviewingDoc}
        onClose={() => { setReviewingDoc(null); setReviewRemarks('') }}
        size="sm"
        title="Review Submission"
        icon="fa-gavel"
        busy={reviewingBusy}
        testId="requirement-review-modal"
        footer={(
          <>
            <button type="button" className="btn btn-light" onClick={() => { setReviewingDoc(null); setReviewRemarks('') }} disabled={reviewingBusy}>Cancel</button>
            <button type="button" className="btn btn-danger" onClick={() => handleReview(reviewingDoc, 'reject')} disabled={reviewingBusy}>
              {reviewingBusy ? 'Saving…' : 'Reject'}
            </button>
            <button type="button" className="btn btn-success" onClick={() => handleReview(reviewingDoc, 'approve')} disabled={reviewingBusy}>
              {reviewingBusy ? 'Saving…' : 'Approve'}
            </button>
          </>
        )}
      >
        <label className="form-label fw-semibold" htmlFor="requirement-review-remarks">Remarks (Optional)</label>
        <textarea maxLength={2000}
          id="requirement-review-remarks"
          className="form-control"
          rows="3"
          placeholder="Feedback"
          value={reviewRemarks}
          onChange={e => setReviewRemarks(e.target.value)}
        ></textarea>
      </AppModal>
    </Wrapper >
  )
}











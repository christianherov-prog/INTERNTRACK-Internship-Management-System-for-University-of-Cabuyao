const ACCEPTANCE_MAX_BYTES = 10 * 1024 * 1024

export function formatAcceptanceFileSize(bytes) {
  if (bytes == null) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function isAllowedAcceptance(file) {
  if (!file) return false
  const type = String(file.type || '').toLowerCase()
  if (type === 'application/pdf' || type === 'image/jpeg' || type === 'image/png') return true
  return /\.(pdf|jpe?g|png)$/i.test(file.name || '')
}

export function validateAcceptanceForm(file) {
  if (!file) return 'Acceptance Form is required.'
  if (!isAllowedAcceptance(file)) return 'Acceptance Form must be a PDF or image (JPG/PNG).'
  if (file.size > ACCEPTANCE_MAX_BYTES) return 'Acceptance Form must be 10 MB or smaller.'
  return null
}

/**
 * Shared Acceptance Form picker for supervisor invite register / accept flows.
 * PDF or JPG/PNG · max 10 MB — local selection until parent submits.
 */
export default function AcceptanceFormPicker({
  id = 'acceptance-form-picker',
  file = null,
  onChange,
  onClear,
  disabled = false,
  error = null,
}) {
  const openPicker = () => {
    if (disabled) return
    document.getElementById(id)?.click()
  }

  return (
    <div className="acceptance-form-picker moa-picker">
      <div className="d-flex align-items-baseline justify-content-between gap-2 mb-1">
        <label className="form-label fw-semibold mb-0" htmlFor={id}>
          Acceptance Form <span className="text-danger">*</span>
        </label>
      </div>
      <p className="text-muted small mb-2">
        Upload the signed Acceptance Form for Faculty review.
      </p>
      {file ? (
        <div className="moa-selected-file">
          <i className={`fa ${/\.pdf$/i.test(file.name) ? 'fa-file-pdf' : 'fa-file-image'} moa-selected-icon`} aria-hidden="true"></i>
          <div className="moa-selected-meta">
            <div className="moa-selected-name" title={file.name}>{file.name}</div>
            <div className="moa-selected-size">{formatAcceptanceFileSize(file.size)} · PDF or JPG/PNG · Maximum 10 MB</div>
          </div>
          <div className="moa-selected-actions">
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={openPicker} disabled={disabled}>
              Change
            </button>
            <button type="button" className="btn btn-sm btn-outline-danger" onClick={onClear} disabled={disabled}>
              Remove
            </button>
          </div>
        </div>
      ) : (
        <label htmlFor={id} className={`upload-dropzone moa-dropzone mb-0${disabled ? ' is-disabled' : ''}`}>
          <i className="fa fa-file-upload" aria-hidden="true"></i>
          <strong>Select Acceptance Form</strong>
          <span>PDF or JPG/PNG · Maximum 10 MB</span>
          <span className="moa-select-chip">Select Acceptance Form</span>
        </label>
      )}
      <input
        id={id}
        type="file"
        className="d-none"
        accept="application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png"
        disabled={disabled}
        onChange={(e) => {
          onChange(e.target.files?.[0] || null)
          e.target.value = ''
        }}
      />
      {error && <div className="text-danger small mt-2">{error}</div>}
    </div>
  )
}

import { UPLOAD_MAX_BYTES, UPLOAD_MAX_MB } from '../config/uploads'
import { formatFileSize } from '../utils/uploadValidation'

export function formatMoaFileSize(bytes) {
  return formatFileSize(bytes)
}

export function isPdfMoa(file) {
  if (!file) return false
  return file.type === 'application/pdf' || /\.pdf$/i.test(file.name)
}

export function validateMoaFile(file) {
  if (!file) return null
  if (!isPdfMoa(file)) return 'MOA must be a PDF file.'
  if (file.size > UPLOAD_MAX_BYTES) return `MOA must be ${UPLOAD_MAX_MB} MB or smaller.`
  return null
}

/**
 * Shared MOA picker used by company application and Request New HTE.
 * Selection is local until the parent submits the form.
 */
export default function MoaFilePicker({
  id,
  file,
  onChange,
  onClear,
  optional = true,
  disabled = false,
}) {
  const openPicker = () => {
    if (disabled) return
    document.getElementById(id)?.click()
  }

  return (
    <div className="moa-picker">
      <div className="d-flex align-items-baseline justify-content-between gap-2 mb-1">
        <label className="form-label fw-semibold mb-0" htmlFor={id}>
          Memorandum of Agreement (MOA)
        </label>
        {optional && <span className="text-muted small">Optional</span>}
      </div>
      {file ? (
        <div className="moa-selected-file">
          <i className="fa fa-file-pdf moa-selected-icon" aria-hidden="true"></i>
          <div className="moa-selected-meta">
            <div className="moa-selected-name" title={file.name}>{file.name}</div>
            <div className="moa-selected-size">{formatMoaFileSize(file.size)} · PDF only · Maximum {UPLOAD_MAX_MB} MB</div>
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
          <i className="fa fa-file-pdf" aria-hidden="true"></i>
          <strong>Select Memorandum of Agreement</strong>
          <span>PDF only · Maximum {UPLOAD_MAX_MB} MB</span>
          <span className="moa-select-chip">Select File</span>
        </label>
      )}
      <input
        id={id}
        type="file"
        className="d-none"
        accept="application/pdf,.pdf"
        disabled={disabled}
        onChange={(e) => {
          onChange(e.target.files?.[0] || null)
          e.target.value = ''
        }}
      />
    </div>
  )
}

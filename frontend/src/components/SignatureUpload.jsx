import { useState, useEffect, useRef } from 'react'
import api from '../services/api'
import { AuthenticatedFileImage } from './AuthenticatedFile'
import ConfirmModal from './modals/ConfirmModal'

/**
 * SignatureUpload component
 * Used on profile settings pages for students and supervisors.
 * Allows uploading a signature image; the backend automatically removes the
 * background to create a transparent PNG that stamps cleanly on Form 30 & 31 PDFs.
 *
 * Now includes a live preview of the saved signature via /auth/signature/view.
 */
function SignatureUpload() {
  const [hasSignature, setHasSignature]   = useState(false)
  const [signaturePath, setSignaturePath] = useState(null)
  const [uploading, setUploading]         = useState(false)
  const [removing, setRemoving]           = useState(false)
  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [message, setMessage]             = useState(null)
  const [preview, setPreview]             = useState(null)
  const [dragOver, setDragOver]           = useState(false)
  const fileRef = useRef(null)

  /** Load signature status + path on mount */
  const loadStatus = () => {
    api.get('/auth/signature/status')
      .then(res => {
        setHasSignature(res.data.has_signature)
        setSignaturePath(res.data.signature_path || null)
      })
      .catch(() => {})
  }

  useEffect(() => { loadStatus() }, [])

  const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    setPreview(URL.createObjectURL(file))
    setMessage(null)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setDragOver(false)
    const file = e.dataTransfer.files[0]
    if (!file) return
    if (!file.type.startsWith('image/')) {
      setMessage({ type: 'danger', text: 'Only image files are accepted (PNG, JPG).' })
      return
    }
    // Apply to the hidden input via a DataTransfer trick
    const dt = new DataTransfer()
    dt.items.add(file)
    if (fileRef.current) fileRef.current.files = dt.files
    setPreview(URL.createObjectURL(file))
    setMessage(null)
  }

  const handleUpload = async () => {
    const file = fileRef.current?.files[0]
    if (!file) {
      setMessage({ type: 'danger', text: 'Please select a signature image first.' })
      return
    }
    setUploading(true)
    setMessage(null)
    try {
      const fd = new FormData()
      fd.append('signature', file)
      await api.post('/auth/signature', fd)
      setPreview(null)
      if (fileRef.current) fileRef.current.value = ''
      setMessage({ type: 'success', text: 'Signature uploaded! Background removed automatically — it will stamp cleanly on documents.' })
      loadStatus()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Upload failed.' })
    } finally {
      setUploading(false)
    }
  }

  const handleConfirmRemove = async () => {
    setRemoving(true)
    try {
      await api.delete('/auth/signature')
      setHasSignature(false)
      setSignaturePath(null)
      setShowDeleteModal(false)
      setMessage({ type: 'success', text: 'Signature removed successfully.' })
    } catch {
      setMessage({ type: 'danger', text: 'Failed to remove signature.' })
    } finally {
      setRemoving(false)
    }
  }

  return (
    <div className="content-card mb-4">
      <div className="content-card-header">
        <i className="fa fa-signature"></i>
        <h6>My Signature</h6>
        {hasSignature && (
          <span className="ms-auto badge bg-success-subtle text-success fw-semibold" style={{ fontSize: '0.72rem' }}>
            <i className="fa fa-check me-1"></i>On file
          </span>
        )}
      </div>

      <div className="p-3 p-md-4">
        {/* Description */}
        <p className="text-muted mb-3" style={{ fontSize: '0.875rem' }}>
          Upload a clear photo or scan of your handwritten signature. The system automatically
          removes the white background so it stamps cleanly on official forms —
          <strong> Form 30 (DTR)</strong> and <strong>Form 31 (Weekly Journal)</strong> PDFs.
        </p>

        {/* Feedback message */}
        {message && (
          <div className={`alert alert-${message.type} d-flex align-items-center gap-2 mb-3 py-2`} style={{ fontSize: '0.875rem' }}>
            <i className={`fa fa-${message.type === 'success' ? 'check-circle' : 'exclamation-circle'} flex-shrink-0`}></i>
            <span>{message.text}</span>
            <button type="button" className="btn-close ms-auto" style={{ fontSize: '0.75rem' }} onClick={() => setMessage(null)}></button>
          </div>
        )}

        <div className="row g-4">
          {/* Left — upload zone */}
          <div className="col-md-7">
            <label className="form-label fw-semibold mb-2" style={{ fontSize: '0.85rem' }}>
              Upload Signature Image
            </label>

            {/* Drag-and-drop zone */}
            <div
              id="signature-drop-zone"
              className={`sig-drop-zone${dragOver ? ' dragging' : ''}`}
              onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
              onDragLeave={() => setDragOver(false)}
              onDrop={handleDrop}
              onClick={() => fileRef.current?.click()}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => e.key === 'Enter' && fileRef.current?.click()}
              aria-label="Click or drag to upload signature"
            >
              {preview ? (
                <div className="sig-drop-preview">
                  <img src={preview} alt="Signature preview" style={{ maxHeight: 72, maxWidth: '100%', objectFit: 'contain' }} />
                  <p className="text-muted mt-2 mb-0" style={{ fontSize: '0.78rem' }}>Preview (background will be removed on upload)</p>
                </div>
              ) : (
                <div className="sig-drop-placeholder">
                  <i className="fa fa-cloud-arrow-up fa-2x text-muted mb-2"></i>
                  <p className="mb-0 fw-semibold text-dark" style={{ fontSize: '0.875rem' }}>Click or drag & drop here</p>
                  <p className="mb-0 text-muted" style={{ fontSize: '0.78rem' }}>PNG, JPG up to 5 MB</p>
                </div>
              )}
            </div>

            <input
              ref={fileRef}
              id="signature-file-input"
              type="file"
              className="d-none"
              accept="image/png,image/jpeg,image/jpg"
              onChange={handleFileChange}
            />

            <small className="text-muted d-block mt-2 mb-3" style={{ fontSize: '0.78rem' }}>
              <i className="fa fa-lightbulb me-1 text-warning"></i>
              Best results: sign on plain white paper, then take a close-up photo in good lighting.
            </small>

            <div className="d-flex gap-2 flex-wrap">
              <button
                id="signature-upload-btn"
                className="btn btn-primary d-inline-flex align-items-center gap-2"
                onClick={handleUpload}
                disabled={uploading}
              >
                <i className={`fa fa-${uploading ? 'spinner fa-spin' : 'upload'}`}></i>
                {uploading ? 'Processing…' : hasSignature ? 'Replace Signature' : 'Upload Signature'}
              </button>
              {hasSignature && (
                <button
                  id="signature-remove-btn"
                  type="button"
                  className="btn btn-outline-danger d-inline-flex align-items-center gap-2"
                  onClick={() => setShowDeleteModal(true)}
                  disabled={removing}
                >
                  <i className={`fa fa-${removing ? 'spinner fa-spin' : 'trash'}`}></i>
                  {removing ? 'Removing…' : 'Remove Signature'}
                </button>
              )}
            </div>
          </div>

          {/* Right — current saved signature */}
          <div className="col-md-5">
            <label className="form-label fw-semibold mb-2" style={{ fontSize: '0.85rem' }}>
              Saved Signature
            </label>
            <div className="sig-saved-panel">
              {hasSignature && signaturePath ? (
                <>
                  <div className="sig-saved-canvas">
                    <AuthenticatedFileImage
                      path={signaturePath}
                      alt="Your saved signature"
                      style={{ maxHeight: 80, maxWidth: '100%', objectFit: 'contain' }}
                      fallback={
                        <div className="text-muted small d-flex align-items-center gap-1">
                          <i className="fa fa-spinner fa-spin"></i> Loading…
                        </div>
                      }
                    />
                  </div>
                  <p className="text-muted mt-2 mb-0" style={{ fontSize: '0.78rem' }}>
                    <i className="fa fa-info-circle me-1"></i>
                    This transparent PNG is embedded in generated PDFs.
                  </p>
                </>
              ) : (
                <div className="sig-saved-empty">
                  <i className="fa fa-pen-nib fa-xl text-muted mb-2"></i>
                  <p className="text-muted small mb-0">No signature on file yet</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      <ConfirmModal
        open={showDeleteModal}
        title="Remove Saved Signature?"
        message="Are you sure you want to delete your signature on file? It will be permanently removed and will no longer appear on generated Form 30, Form 31, or certification documents."
        confirmLabel="Remove Signature"
        cancelLabel="Keep Signature"
        variant="danger"
        loading={removing}
        onCancel={() => setShowDeleteModal(false)}
        onConfirm={handleConfirmRemove}
      />

      {/* Scoped styles */}
      <style>{`
        .sig-drop-zone {
          border: 2px dashed #d1d5db;
          border-radius: 10px;
          padding: 20px;
          text-align: center;
          cursor: pointer;
          background: #fafafa;
          transition: border-color 0.18s, background 0.18s;
          min-height: 100px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .sig-drop-zone:hover,
        .sig-drop-zone:focus-within,
        .sig-drop-zone.dragging {
          border-color: #157938;
          background: #f0fdf4;
          outline: none;
        }
        .sig-drop-placeholder { display: flex; flex-direction: column; align-items: center; }
        .sig-drop-preview { display: flex; flex-direction: column; align-items: center; }
        .sig-saved-panel {
          border: 1px solid #e5e7eb;
          border-radius: 10px;
          padding: 16px;
          background: #fff;
          min-height: 120px;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
        }
        .sig-saved-canvas {
          background: repeating-conic-gradient(#f3f4f6 0% 25%, #fff 0% 50%)
            0 0 / 16px 16px;
          border-radius: 6px;
          padding: 12px;
          width: 100%;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 80px;
        }
        .sig-saved-empty {
          display: flex;
          flex-direction: column;
          align-items: center;
          color: #9ca3af;
        }
      `}</style>
    </div>
  )
}

export default SignatureUpload

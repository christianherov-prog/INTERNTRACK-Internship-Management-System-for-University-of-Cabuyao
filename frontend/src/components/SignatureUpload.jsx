import { useState, useEffect, useRef } from 'react'
import api from '../services/api'
import { useToast } from '../contexts/ToastContext'
import { AuthenticatedFileImage } from './AuthenticatedFile'

/**
 * SignatureUpload component
 * Used on profile settings pages for students and supervisors.
 * Allows uploading a signature image; the backend automatically removes the
 * background to create a transparent PNG that stamps cleanly on Form 30 & 31 PDFs.
 */
function SignatureUpload() {
  const [hasSignature, setHasSignature] = useState(false)
  const [signaturePath, setSignaturePath] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [removing, setRemoving] = useState(false)
  const [preview, setPreview] = useState(null)
  const [previewVersion, setPreviewVersion] = useState(Date.now())
  const [selectedFile, setSelectedFile] = useState(null)
  const [dragActive, setDragActive] = useState(false)
  const fileRef = useRef(null)
  const toast = useToast()

  const resetSelection = () => {
    setSelectedFile(null)
    setPreview((current) => {
      if (current) URL.revokeObjectURL(current)
      return null
    })
    if (fileRef.current) fileRef.current.value = ''
  }

  useEffect(() => {
    api.get('/auth/signature/status')
      .then((res) => {
        setHasSignature(res.data.has_signature)
        setSignaturePath(res.data.signature_path || null)
      })
      .catch(() => {})
  }, [])

  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview)
  }, [preview])

  const validateFile = (file) => {
    if (!file) return 'Please select a signature image first.'
    const allowedTypes = ['image/png', 'image/jpeg']
    const allowedNames = /\.(png|jpe?g)$/i
    if (!allowedTypes.includes(file.type) && !allowedNames.test(file.name)) {
      return 'Signature must be a PNG or JPG image.'
    }
    if (file.size > 5 * 1024 * 1024) {
      return 'Signature image must be 5MB or smaller.'
    }
    return ''
  }

  const selectFile = (file) => {
    const error = validateFile(file)
    if (error) {
      resetSelection()
      toast.error(error)
      return
    }

    setSelectedFile(file)
    setPreview((current) => {
      if (current) URL.revokeObjectURL(current)
      return URL.createObjectURL(file)
    })
  }

  const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) {
      resetSelection()
      return
    }
    selectFile(file)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setDragActive(false)
    const file = e.dataTransfer.files?.[0]
    if (file) selectFile(file)
  }

  const handleUpload = async () => {
    const file = selectedFile || fileRef.current?.files[0]
    const validationError = validateFile(file)
    if (validationError) {
      toast.error(validationError)
      return
    }

    setUploading(true)
    try {
      const fd = new FormData()
      fd.append('signature', file)
      const res = await api.post('/auth/signature', fd)
      setHasSignature(true)
      setSignaturePath(res.data.signature_path || null)
      setPreviewVersion(Date.now())
      resetSelection()
      toast.success('Signature uploaded successfully')
    } catch (err) {
      const text = err.response?.data?.errors?.signature?.[0] || err.response?.data?.message || 'Upload failed.'
      toast.error(text)
    } finally {
      setUploading(false)
    }
  }

  const handleRemove = async () => {
    if (!window.confirm('Remove your saved signature?')) return
    setRemoving(true)
    try {
      await api.delete('/auth/signature')
      setHasSignature(false)
      setSignaturePath(null)
      setPreviewVersion(Date.now())
      resetSelection()
      toast.success('Signature removed.')
    } catch {
      toast.error('Failed to remove signature.')
    } finally {
      setRemoving(false)
    }
  }

  const openPicker = () => fileRef.current?.click()

  return (
    <div className="content-card mb-4 signature-upload-card">
      <div className="content-card-header">
        <i className="fa fa-signature"></i>
        <h6>My Signature</h6>
      </div>
      <div className="p-3">
        <p className="text-muted mb-3 signature-upload-intro">
          Upload a photo or scan of your physical signature. The system will automatically
          remove the white background so it stamps cleanly on your{' '}
          <strong>Form 30 (DTR)</strong> and <strong>Form 31 (Weekly Journal)</strong> PDFs.
        </p>

        {hasSignature && !selectedFile && !uploading && (
          <div className="signature-status-banner mb-3" data-state="saved">
            <i className="fa fa-check-circle" aria-hidden="true"></i>
            <span>Signature on file — it will be embedded in your Form 30 / 31 PDFs.</span>
          </div>
        )}
        {uploading && (
          <div className="signature-status-banner mb-3" data-state="uploading">
            <i className="fa fa-spinner fa-spin" aria-hidden="true"></i>
            <span>Uploading and removing the background…</span>
          </div>
        )}

        <div className="signature-upload-layout">
          <div className="signature-upload-main">
            <label className="form-label fw-semibold" htmlFor="signature-file-input">
              Upload Signature Image
            </label>
            <div
              className={`signature-file-control ${dragActive ? 'is-dragging' : ''} ${selectedFile ? 'has-file' : ''}`}
              role="button"
              tabIndex={0}
              onClick={openPicker}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault()
                  openPicker()
                }
              }}
              onDragOver={(e) => {
                e.preventDefault()
                setDragActive(true)
              }}
              onDragLeave={() => setDragActive(false)}
              onDrop={handleDrop}
            >
              <input
                id="signature-file-input"
                ref={fileRef}
                type="file"
                className="visually-hidden"
                accept="image/png,image/jpeg,image/jpg"
                onChange={handleFileChange}
                onClick={(e) => e.stopPropagation()}
              />
              <span className="signature-file-icon" aria-hidden="true">
                <i className={`fa fa-${selectedFile ? 'file-image' : 'folder-plus'}`}></i>
              </span>
              <span className="signature-file-copy">
                <strong>{selectedFile ? selectedFile.name : 'Choose signature image'}</strong>
                <span>
                  {selectedFile
                    ? 'Selected — click Upload Signature to save'
                    : 'No file selected yet'}
                </span>
              </span>
            </div>
            <small className="form-text d-block mb-3">
              Accepted: PNG, JPG. Max 5MB. For best results, sign on white paper and take a clear photo.
            </small>
            <div className="d-flex gap-2 flex-wrap signature-upload-actions">
              <button
                type="button"
                className="btn-green d-inline-flex align-items-center gap-2"
                onClick={handleUpload}
                disabled={uploading || !selectedFile}
              >
                <i className={`fa fa-${uploading ? 'spinner fa-spin' : 'upload'}`}></i>
                {uploading ? 'Processing...' : hasSignature ? 'Replace Signature' : 'Upload Signature'}
              </button>
              {hasSignature && (
                <button
                  type="button"
                  className="btn btn-outline-danger"
                  onClick={handleRemove}
                  disabled={removing || uploading}
                >
                  <i className="fa fa-trash me-2"></i>
                  {removing ? 'Removing...' : 'Remove'}
                </button>
              )}
            </div>
          </div>

          {(preview || (hasSignature && signaturePath)) && (
            <div className="signature-preview-panel">
              <p className="signature-preview-title">{preview ? 'Preview (original)' : 'Current signature'}</p>
              <div className="signature-preview-canvas">
                {preview ? (
                  <img src={preview} alt="Signature preview" />
                ) : (
                  <AuthenticatedFileImage key={previewVersion} path={signaturePath} alt="Current signature" />
                )}
              </div>
              {preview && (
                <p className="signature-preview-note">Background will be removed on upload.</p>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default SignatureUpload

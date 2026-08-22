import { useState, useEffect, useRef } from 'react'
import api from '../services/api'

/**
 * SignatureUpload component
 * Used on profile settings pages for students and supervisors.
 * Allows uploading a signature image; the backend automatically removes the
 * background to create a transparent PNG that stamps cleanly on Form 30 & 31 PDFs.
 */
function SignatureUpload() {
  const [hasSignature, setHasSignature] = useState(false)
  const [uploading, setUploading]       = useState(false)
  const [removing, setRemoving]         = useState(false)
  const [message, setMessage]           = useState(null)
  const [preview, setPreview]           = useState(null)
  const fileRef = useRef(null)

  useEffect(() => {
    api.get('/auth/signature/status')
      .then(res => setHasSignature(res.data.has_signature))
      .catch(() => {})
  }, [])

  const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    setPreview(URL.createObjectURL(file))
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
      setHasSignature(true)
      setPreview(null)
      if (fileRef.current) fileRef.current.value = ''
      setMessage({ type: 'success', text: 'Signature uploaded! Background has been automatically removed.' })
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Upload failed.' })
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
      setMessage({ type: 'success', text: 'Signature removed.' })
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
      </div>
      <div className="p-3">
        <p className="text-muted mb-3" style={{ fontSize: '0.9rem' }}>
          Upload a photo or scan of your physical signature. The system will automatically
          remove the white background so it stamps cleanly on official documents, evaluations,{' '}
          <strong>Form 30 (DTR)</strong>, and <strong>Form 31 (Weekly Journal)</strong> PDFs.
        </p>

        {message && (
          <div className={`alert alert-${message.type} mb-3`}>
            <i className={`fa fa-${message.type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2`}></i>
            {message.text}
          </div>
        )}

        {hasSignature && (
          <div className="alert alert-success mb-3 d-flex align-items-center gap-2">
            <i className="fa fa-check-circle"></i>
            <span>You have a signature on file. It will be automatically embedded in your generated PDFs.</span>
          </div>
        )}

        <div className="d-flex align-items-start gap-3 flex-wrap">
          <div style={{ flex: 1, minWidth: 260 }}>
            <label className="form-label fw-semibold">Upload Signature Image</label>
            <input
              ref={fileRef}
              type="file"
              className="form-control mb-2"
              accept="image/png,image/jpeg,image/jpg"
              onChange={handleFileChange}
            />
            <small className="text-muted d-block mb-3">
              Accepted: PNG, JPG. Max 5MB. For best results, sign on white paper and take a clear photo.
            </small>
            <div className="d-flex gap-2">
              <button
                className="btn btn-primary"
                onClick={handleUpload}
                disabled={uploading}
              >
                <i className={`fa fa-${uploading ? 'spinner fa-spin' : 'upload'} me-2`}></i>
                {uploading ? 'Processing…' : hasSignature ? 'Replace Signature' : 'Upload Signature'}
              </button>
              {hasSignature && (
                <button
                  className="btn btn-outline-danger"
                  onClick={handleRemove}
                  disabled={removing}
                >
                  <i className="fa fa-trash me-2"></i>
                  {removing ? 'Removing…' : 'Remove'}
                </button>
              )}
            </div>
          </div>

          {/* Preview */}
          {preview && (
            <div
              style={{
                border: '1px solid #dee2e6',
                borderRadius: 8,
                padding: 12,
                background: '#f8f9fa',
                textAlign: 'center',
              }}
            >
              <p className="text-muted mb-2" style={{ fontSize: '0.8rem' }}>Preview (original)</p>
              <img
                src={preview}
                alt="Signature preview"
                style={{ maxWidth: 200, maxHeight: 80, objectFit: 'contain' }}
              />
              <p className="text-muted mt-1 mb-0" style={{ fontSize: '0.75rem' }}>
                Background will be removed on upload.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default SignatureUpload

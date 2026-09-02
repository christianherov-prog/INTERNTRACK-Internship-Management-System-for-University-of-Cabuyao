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
  const [fileName, setFileName]         = useState('')
  const fileRef = useRef(null)

  useEffect(() => {
    api.get('/auth/signature/status')
      .then(res => setHasSignature(res.data.has_signature))
      .catch(() => {})
  }, [])

  const applyFile = (file) => {
    if (!file) return
    setFileName(file.name)
    setPreview(URL.createObjectURL(file))
    setMessage(null)
  }

  const handleFileChange = (e) => {
    applyFile(e.target.files[0])
  }

  const handleDrop = (e) => {
    e.preventDefault()
    applyFile(e.dataTransfer.files?.[0])
    if (fileRef.current && e.dataTransfer.files?.[0]) {
      const dt = new DataTransfer()
      dt.items.add(e.dataTransfer.files[0])
      fileRef.current.files = dt.files
    }
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
      setFileName('')
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
            <input
              ref={fileRef}
              id="signature-file"
              type="file"
              className="d-none"
              accept="image/png,image/jpeg,image/jpg"
              onChange={handleFileChange}
            />
            <label
              htmlFor="signature-file"
              className={`upload-dropzone sig-dropzone mb-3${fileName ? ' is-filled' : ''}`}
              onDragOver={(e) => e.preventDefault()}
              onDrop={handleDrop}
            >
              <i className="fa fa-cloud-upload-alt" aria-hidden="true"></i>
              <strong>{fileName ? fileName : 'Choose a signature image'}</strong>
              <span>PNG or JPG · Max 5MB · Sign on white paper for best results</span>
            </label>
            <div className="d-flex gap-2">
              <button
                type="button"
                className="btn-green"
                onClick={handleUpload}
                disabled={uploading}
              >
                <i className={`fa fa-${uploading ? 'spinner fa-spin' : 'upload'} me-2`}></i>
                {uploading ? 'Processing…' : hasSignature ? 'Replace Signature' : 'Upload Signature'}
              </button>
              {hasSignature && (
                <button
                  type="button"
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

          {preview && (
            <div
              style={{
                border: '1px solid #d9e9de',
                borderRadius: 8,
                padding: 12,
                background: '#f8fdf9',
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

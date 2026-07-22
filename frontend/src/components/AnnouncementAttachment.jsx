import { useEffect, useState } from 'react'
import '../styles/announcement-attachments.css'

const ATTACH_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/gif,image/webp,application/pdf'
const ATTACH_MAX_BYTES = 10 * 1024 * 1024
const ATTACH_EXT_OK = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'])

export { ATTACH_ACCEPT }

function fileExt(name) {
  const parts = String(name || '').toLowerCase().split('.')
  return parts.length > 1 ? parts.pop() : ''
}

export function isImageFile(fileOrMeta) {
  if (!fileOrMeta) return false
  if (fileOrMeta.is_image != null) return Boolean(fileOrMeta.is_image)
  const mime = (fileOrMeta.type || fileOrMeta.mime || '').toLowerCase()
  if (mime.startsWith('image/')) return true
  return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt(fileOrMeta.name || fileOrMeta.filename))
}

function fileTypeIcon(filename) {
  const ext = fileExt(filename)
  if (ext === 'pdf') return 'fa-file-pdf'
  if (['doc', 'docx'].includes(ext)) return 'fa-file-word'
  if (['xls', 'xlsx'].includes(ext)) return 'fa-file-excel'
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'fa-file-image'
  return 'fa-file'
}

function formatBytes(n) {
  const size = Number(n) || 0
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

export function validateAttachFile(file) {
  if (!file) return 'No file selected.'
  const ext = fileExt(file.name)
  if (!ATTACH_EXT_OK.has(ext)) {
    return 'Unsupported file type. Use images (jpg, png, gif, webp) or documents (pdf, doc, docx, xls, xlsx).'
  }
  if (file.size > ATTACH_MAX_BYTES) {
    return 'File is too large. Maximum size is 10 MB.'
  }
  return null
}

/** Image thumbnail / file card for an announcement attachment. */
export function AnnouncementAttachmentView({ attachment }) {
  const [broken, setBroken] = useState(false)
  const [lightbox, setLightbox] = useState(null)

  useEffect(() => {
    setBroken(false)
  }, [attachment?.url])

  if (!attachment) return null

  if (attachment.is_image) {
    if (broken || !attachment.url) {
      return (
        <div className="ann-attach-unavailable" role="status">
          <i className="fa fa-image" aria-hidden="true" />
          <span>Image unavailable</span>
        </div>
      )
    }
    return (
      <>
        <button
          type="button"
          className="ann-attach-image-btn"
          onClick={() => setLightbox({ url: attachment.url, filename: attachment.filename || 'Image' })}
          aria-label={`View image ${attachment.filename || ''}`}
        >
          <img
            src={attachment.url}
            alt={attachment.filename || 'Attached image'}
            className="ann-attach-thumb"
            onError={() => setBroken(true)}
          />
        </button>
        {lightbox && (
          <div
            className="ann-lightbox"
            role="dialog"
            aria-modal="true"
            aria-label={lightbox.filename}
            onClick={() => setLightbox(null)}
          >
            <button
              type="button"
              className="ann-lightbox-close"
              aria-label="Close image"
              onClick={() => setLightbox(null)}
            >
              <i className="fa fa-times" aria-hidden="true" />
            </button>
            <img
              src={lightbox.url}
              alt={lightbox.filename}
              className="ann-lightbox-img"
              onClick={(e) => e.stopPropagation()}
              onError={() => setLightbox(null)}
            />
            <div className="ann-lightbox-caption">{lightbox.filename}</div>
          </div>
        )}
      </>
    )
  }

  if (!attachment.url) {
    return (
      <div className="ann-attach-unavailable" role="status">
        <i className="fa fa-file" aria-hidden="true" />
        <span>File unavailable</span>
      </div>
    )
  }

  return (
    <div className="ann-attach-file">
      <div className="ann-attach-file-icon" aria-hidden="true">
        <i className={`fa ${fileTypeIcon(attachment.filename)}`} />
      </div>
      <div className="ann-attach-file-meta">
        <div className="ann-attach-file-name" title={attachment.filename}>
          {attachment.filename || 'Attachment'}
        </div>
        <div className="ann-attach-file-sub">{formatBytes(attachment.size)}</div>
      </div>
      <a
        className="btn btn-sm btn-outline-secondary ann-attach-download"
        href={attachment.url}
        target="_blank"
        rel="noopener noreferrer"
        download={attachment.filename || undefined}
      >
        <i className="fa fa-download" aria-hidden="true" />
        <span>Open</span>
      </a>
    </div>
  )
}

/** Composer preview before posting. */
export function AnnouncementAttachPreview({ file, previewUrl, onRemove, disabled }) {
  if (!file && !previewUrl) return null
  return (
    <div className="ann-attach-preview" aria-live="polite">
      {previewUrl ? (
        <img src={previewUrl} alt="" className="ann-attach-preview-thumb" />
      ) : (
        <div className="ann-attach-preview-file">
          <i className={`fa ${fileTypeIcon(file?.name)}`} aria-hidden="true" />
          <div>
            <div className="ann-attach-preview-name">{file?.name}</div>
            <div className="ann-attach-preview-sub">{formatBytes(file?.size)}</div>
          </div>
        </div>
      )}
      <button
        type="button"
        className="btn btn-sm btn-outline-danger"
        onClick={onRemove}
        disabled={disabled}
        aria-label="Remove attachment"
      >
        <i className="fa fa-times" aria-hidden="true" /> Remove
      </button>
    </div>
  )
}

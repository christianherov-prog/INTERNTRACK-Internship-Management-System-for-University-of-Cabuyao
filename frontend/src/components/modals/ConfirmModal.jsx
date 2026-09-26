import { useId, useRef } from 'react'
import { createPortal } from 'react-dom'
import useModalLayer from './useModalLayer'

/**
 * Shared themed confirmation dialog (replaces window.confirm across roles).
 *
 * Props:
 *   open, title, message, confirmLabel, cancelLabel
 *   variant: 'danger' | 'primary' (default 'primary')
 *   loading, error, onCancel, onConfirm
 */
function ConfirmModal({
  open,
  title = 'Are you sure?',
  message = '',
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'primary',
  loading = false,
  error = null,
  children = null,
  loadingLabel = 'Working…',
  onCancel,
  onConfirm,
}) {
  const titleId = useId()
  const descId = useId()
  const dialogRef = useRef(null)
  const cancelRef = useRef(null)
  // Shared overlay behaviour: page scroll lock on <html>, Escape (unless
  // loading), Tab trap, focus on Cancel, and focus returned on close.
  useModalLayer({ open, onClose: onCancel, busy: loading, dialogRef, initialFocusRef: cancelRef })

  if (!open) return null

  const handleBackdropClick = (e) => {
    if (loading) return
    if (e.target === e.currentTarget) onCancel?.()
  }

  const confirmClass =
    variant === 'danger' ? 'it-confirm-btn it-confirm-btn-danger' : 'it-confirm-btn it-confirm-btn-primary'

  return createPortal(
    <div className="it-confirm-overlay" role="presentation" onClick={handleBackdropClick}>
      <div
        ref={dialogRef}
        className={`it-confirm-modal${children ? ' it-confirm-modal-form' : ''}`}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descId}
      >
        <div className="it-confirm-icon" aria-hidden="true">
          <i className={`fa ${variant === 'danger' ? 'fa-exclamation-triangle' : 'fa-question-circle'}`} />
        </div>
        <h3 id={titleId} className="it-confirm-title">{title}</h3>
        {message && (
          <p id={descId} className="it-confirm-sub">{message}</p>
        )}
        {children && (
          <div className="it-confirm-body text-start w-100 mb-2" style={{ fontSize: '0.88rem' }}>
            {children}
          </div>
        )}

        {error && (
          <div className="it-confirm-error" role="alert">
            <i className="fa fa-exclamation-circle" aria-hidden="true" />
            <span>{error}</span>
          </div>
        )}

        <div className="it-confirm-actions">
          <button
            ref={cancelRef}
            type="button"
            className="it-confirm-btn it-confirm-btn-cancel"
            onClick={onCancel}
            disabled={loading}
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            className={confirmClass}
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? (
              <>
                <i className="fa fa-spinner fa-spin" aria-hidden="true" />
                {loadingLabel}
              </>
            ) : (
              confirmLabel
            )}
          </button>
        </div>
      </div>
    </div>,
    document.body
  )
}

export default ConfirmModal

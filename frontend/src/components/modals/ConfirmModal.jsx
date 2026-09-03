import { useEffect, useId, useRef } from 'react'

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
  onCancel,
  onConfirm,
}) {
  const titleId = useId()
  const descId = useId()
  const dialogRef = useRef(null)
  const cancelRef = useRef(null)
  const previouslyFocused = useRef(null)

  useEffect(() => {
    if (!open) return undefined

    previouslyFocused.current = document.activeElement
    const timer = window.setTimeout(() => cancelRef.current?.focus(), 0)

    const onKeyDown = (e) => {
      if (e.key === 'Escape' && !loading) {
        e.preventDefault()
        onCancel?.()
        return
      }
      if (e.key !== 'Tab' || !dialogRef.current) return

      const focusable = dialogRef.current.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )
      const list = Array.from(focusable).filter((el) => el.offsetParent !== null || el === document.activeElement)
      if (list.length === 0) return
      const first = list[0]
      const last = list[list.length - 1]
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault()
        last.focus()
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault()
        first.focus()
      }
    }

    document.addEventListener('keydown', onKeyDown)
    const prevOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      window.clearTimeout(timer)
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = prevOverflow
      previouslyFocused.current?.focus?.()
    }
  }, [open, loading, onCancel])

  if (!open) return null

  const handleBackdropClick = (e) => {
    if (loading) return
    if (e.target === e.currentTarget) onCancel?.()
  }

  const confirmClass =
    variant === 'danger' ? 'it-confirm-btn it-confirm-btn-danger' : 'it-confirm-btn it-confirm-btn-danger'

  return (
    <div className="it-confirm-overlay" role="presentation" onClick={handleBackdropClick}>
      <div
        ref={dialogRef}
        className="it-confirm-modal"
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
                Working…
              </>
            ) : (
              confirmLabel
            )}
          </button>
        </div>
      </div>
    </div>
  )
}

export default ConfirmModal

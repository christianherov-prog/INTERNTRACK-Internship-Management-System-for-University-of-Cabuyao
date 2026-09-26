import { useId, useRef } from 'react'
import { createPortal } from 'react-dom'
import { modalSizeClass } from './modalManager'
import useModalLayer from './useModalLayer'

/**
 * Shared dialog shell for every role.
 *
 * - Portaled to <body>: a transformed/animated page card can never become the
 *   containing block of the overlay (no left-pinned, clipped or jumping modal).
 * - Centered in the viewport with a gutter; header / body / footer are a flex
 *   column, so only the body scrolls and the title and actions stay visible.
 * - Sizes: sm (confirmations), md (short forms), lg (large forms, details),
 *   data (tables, submissions, report and CSV previews), document (official
 *   forms). See styles/modal-system.css.
 *
 * Props:
 *   open, onClose, title, subtitle, icon, size, footer, children
 *   busy            – blocks Escape, backdrop and the close button while saving
 *   closeOnBackdrop – read-only viewers may close on an outside click; forms
 *                     default to false so a stray click never discards input
 *   onSubmit        – renders the dialog as a <form>; footer submit buttons work
 *   fillBody        – the body is a flex column whose .it-modal__table-wrap takes
 *                     the remaining height and owns the table's scrolling
 *   headerActions   – extra controls beside the close button (e.g. Print)
 *   banner          – a fixed strip between the header and the scrolling body
 *   footerAlign     – 'end' (default) | 'between'
 *   footerBare      – the footer content brings its own layout (no padding/flex)
 */
export default function AppModal({
  open = true,
  onClose,
  title,
  subtitle = null,
  icon = null,
  size = 'md',
  footer = null,
  footerAlign = 'end',
  footerBare = false,
  banner = null,
  children,
  busy = false,
  closeOnBackdrop = false,
  onSubmit = null,
  fillBody = false,
  headerActions = null,
  initialFocusRef = null,
  role = 'dialog',
  className = '',
  bodyClassName = '',
  describedBy,
  testId,
}) {
  const titleId = useId()
  const dialogRef = useRef(null)
  const pressStartedOnBackdrop = useRef(false)
  const { requestClose } = useModalLayer({ open, onClose, busy, dialogRef, initialFocusRef })

  if (!open) return null

  const Dialog = onSubmit ? 'form' : 'section'
  const dialogProps = onSubmit ? { onSubmit } : {}

  const content = (
    <div className="it-modal-layer" role="presentation" data-testid={testId}>
      <div className="it-modal-backdrop" aria-hidden="true" />
      <div
        className="it-modal-viewport"
        onMouseDown={(e) => { pressStartedOnBackdrop.current = e.target === e.currentTarget }}
        onClick={(e) => {
          if (closeOnBackdrop && pressStartedOnBackdrop.current && e.target === e.currentTarget) requestClose()
          pressStartedOnBackdrop.current = false
        }}
      >
        <Dialog
          ref={dialogRef}
          className={['it-modal', modalSizeClass(size), className].filter(Boolean).join(' ')}
          role={role}
          aria-modal="true"
          aria-labelledby={title != null ? titleId : undefined}
          aria-describedby={describedBy}
          tabIndex={-1}
          {...dialogProps}
        >
          {title != null && (
            <header className="it-modal__header">
              <div className="it-modal__heading">
                {icon && <i className={`fa ${icon} it-modal__icon`} aria-hidden="true" />}
                <div className="it-modal__titles">
                  <h2 id={titleId} className="it-modal__title">{title}</h2>
                  {subtitle && <p className="it-modal__subtitle">{subtitle}</p>}
                </div>
              </div>
              {headerActions && <div className="it-modal__header-actions">{headerActions}</div>}
              <button
                type="button"
                className="btn-close it-modal__close"
                aria-label="Close"
                onClick={requestClose}
                disabled={busy}
              />
            </header>
          )}
          {banner}
          <div className={['it-modal__body', fillBody ? 'it-modal__body--fill' : '', bodyClassName].filter(Boolean).join(' ')}>
            {children}
          </div>
          {footer && (
            <footer
              className={[
                'it-modal__footer',
                footerAlign === 'between' ? 'it-modal__footer--between' : '',
                footerBare ? 'it-modal__footer--bare' : '',
              ].filter(Boolean).join(' ')}
            >
              {footer}
            </footer>
          )}
        </Dialog>
      </div>
    </div>
  )

  return typeof document === 'undefined' ? content : createPortal(content, document.body)
}

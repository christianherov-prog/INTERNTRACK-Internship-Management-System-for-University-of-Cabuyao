import { useRef } from 'react'
import { createPortal } from 'react-dom'
import useModalLayer from './useModalLayer'

/**
 * Full-screen image viewer shared by announcements and messages. Portaled to
 * <body> (never trapped in a hover-lifted card), with Escape, focus return and
 * the shared scroll lock. `className` keeps each surface's existing look.
 */
export default function Lightbox({ open, onClose, label, className, children }) {
  const dialogRef = useRef(null)
  useModalLayer({ open, onClose, dialogRef })

  if (!open) return null

  return createPortal(
    <div
      ref={dialogRef}
      className={className}
      role="dialog"
      aria-modal="true"
      aria-label={label}
      tabIndex={-1}
      onClick={onClose}
    >
      {children}
    </div>,
    document.body
  )
}

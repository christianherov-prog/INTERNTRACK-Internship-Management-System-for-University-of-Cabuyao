import { useEffect, useId, useRef } from 'react'
import {
  focusableWithin,
  isTopModal,
  lockPageScroll,
  popModal,
  pushModal,
  trapTab,
  unlockPageScroll,
} from './modalManager'

/**
 * Behaviour shared by every overlay: page scroll lock, Escape to close the
 * topmost dialog (unless busy), Tab trapped inside it, initial focus, and focus
 * returned to the opener on close.
 *
 * The effect depends on `open` only; the latest onClose/busy are read from
 * refs, so parent re-renders never re-run it (no focus jumps, no loops).
 */
export default function useModalLayer({ open, onClose, busy = false, dialogRef, initialFocusRef }) {
  const id = useId()
  const onCloseRef = useRef(onClose)
  const busyRef = useRef(busy)

  useEffect(() => {
    onCloseRef.current = onClose
    busyRef.current = busy
  })

  useEffect(() => {
    if (!open) return undefined

    const opener = document.activeElement
    pushModal(id)
    lockPageScroll()

    const focusTimer = window.setTimeout(() => {
      const root = dialogRef?.current
      if (!root || root.contains(document.activeElement)) return
      const target = initialFocusRef?.current
        || root.querySelector('[autofocus], [data-autofocus]')
        || focusableWithin(root).find((el) => !el.classList.contains('btn-close'))
        || root
      target.focus?.({ preventScroll: true })
    }, 0)

    const onKeyDown = (event) => {
      if (!isTopModal(id)) return
      if (event.key === 'Escape') {
        if (busyRef.current) return
        event.preventDefault()
        event.stopPropagation()
        onCloseRef.current?.()
        return
      }
      trapTab(event, dialogRef?.current)
    }

    document.addEventListener('keydown', onKeyDown)

    return () => {
      window.clearTimeout(focusTimer)
      document.removeEventListener('keydown', onKeyDown)
      popModal(id)
      unlockPageScroll()
      if (opener && typeof opener.focus === 'function' && document.contains(opener)) {
        opener.focus({ preventScroll: true })
      }
    }
  }, [open, id])

  return {
    id,
    requestClose: () => {
      if (!busyRef.current) onCloseRef.current?.()
    },
  }
}

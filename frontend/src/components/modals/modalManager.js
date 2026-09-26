/**
 * Shared overlay bookkeeping for every dialog in INTERNTRACK (AppModal,
 * ConfirmModal, FormPreviewModal, lightboxes).
 *
 * - One page scroll lock, reference-counted, so nested dialogs never unlock
 *   the page early. The page scrolls on <html> (html and body both set
 *   overflow-x, so `overflow: hidden` on body alone never locked it). The
 *   lock pins <body> at the current scroll offset and, when the page had a
 *   scrollbar, keeps its track (`overflow-y: scroll`), so the viewport width,
 *   sidebar, topbar and dialog centre never shift; the scroll position is
 *   restored on unlock. It also stops background scrolling on iOS.
 * - A stack of open dialogs, so Escape and focus trapping act on the topmost
 *   dialog only.
 */

export const MODAL_SIZES = ['sm', 'md', 'lg', 'data', 'document']

export const SCROLL_LOCK_CLASS = 'it-scroll-locked'
export const SCROLL_LOCK_BAR_CLASS = 'it-scroll-locked--bar'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

let lockCount = 0
let savedScrollY = 0
const stack = []

export function modalSizeClass(size) {
  return `it-modal--${MODAL_SIZES.includes(size) ? size : 'md'}`
}

export function lockPageScroll(doc = globalThis.document) {
  lockCount += 1
  const root = doc?.documentElement
  if (lockCount === 1 && root) {
    const win = doc.defaultView
    savedScrollY = win?.scrollY || 0
    if (win && win.innerWidth > root.clientWidth) root.classList.add(SCROLL_LOCK_BAR_CLASS)
    if (doc.body) doc.body.style.top = `-${savedScrollY}px`
    root.classList.add(SCROLL_LOCK_CLASS)
  }
  return lockCount
}

export function unlockPageScroll(doc = globalThis.document) {
  const wasLocked = lockCount > 0
  lockCount = Math.max(0, lockCount - 1)
  const root = doc?.documentElement
  if (wasLocked && lockCount === 0 && root) {
    root.classList.remove(SCROLL_LOCK_CLASS, SCROLL_LOCK_BAR_CLASS)
    if (doc.body) doc.body.style.top = ''
    doc.defaultView?.scrollTo?.({ top: savedScrollY, left: 0, behavior: 'instant' })
  }
  return lockCount
}

export function pageScrollLockCount() {
  return lockCount
}

export function pushModal(id) {
  const at = stack.indexOf(id)
  if (at !== -1) stack.splice(at, 1)
  stack.push(id)
}

export function popModal(id) {
  const at = stack.indexOf(id)
  if (at !== -1) stack.splice(at, 1)
}

export function isTopModal(id) {
  return stack.length > 0 && stack[stack.length - 1] === id
}

export function openModalCount() {
  return stack.length
}

export function focusableWithin(root) {
  if (!root?.querySelectorAll) return []
  return Array.from(root.querySelectorAll(FOCUSABLE)).filter(
    (el) => el.offsetParent !== null || el === root.ownerDocument?.activeElement
  )
}

/** Keep Tab / Shift+Tab inside `root`. Returns true when focus was moved. */
export function trapTab(event, root) {
  if (event.key !== 'Tab' || !root) return false
  const list = focusableWithin(root)
  const active = root.ownerDocument?.activeElement
  if (list.length === 0) {
    event.preventDefault()
    root.focus?.()
    return true
  }
  const first = list[0]
  const last = list[list.length - 1]
  const outside = !root.contains(active)
  if (event.shiftKey && (active === first || outside)) {
    event.preventDefault()
    last.focus()
    return true
  }
  if (!event.shiftKey && (active === last || outside)) {
    event.preventDefault()
    first.focus()
    return true
  }
  return false
}

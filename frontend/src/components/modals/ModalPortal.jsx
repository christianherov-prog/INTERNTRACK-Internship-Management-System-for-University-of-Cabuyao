import { createPortal } from 'react-dom'

/**
 * Renders children into document.body so fixed overlays are centered
 * against the full viewport (not a transformed ancestor like .sidebar).
 */
function ModalPortal({ children }) {
  if (typeof document === 'undefined') return null
  return createPortal(children, document.body)
}

export default ModalPortal

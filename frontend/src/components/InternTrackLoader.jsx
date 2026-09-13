import { useState } from 'react'

export const PNC_LOGO_SRC = '/images/pnc-logo.png'

/**
 * Small University of Cabuyao / PNC logo used only when content cannot be shown yet.
 * Do not use for sidebar navigation or action-button busy states.
 */
export default function InternTrackLoader({
  size = 40,
  label = null,
  centered = true,
  className = '',
}) {
  const [broken, setBroken] = useState(false)
  const px = Math.min(48, Math.max(32, Number(size) || 40))

  return (
    <div
      className={`it-loader ${centered ? 'it-loader-centered' : 'it-loader-inline'} ${className}`.trim()}
      role="status"
      aria-live="polite"
      aria-label={label || 'Loading'}
    >
      {broken ? (
        <span className="it-loader-fallback" style={{ width: px, height: px }} aria-hidden="true" />
      ) : (
        <img
          src={PNC_LOGO_SRC}
          alt=""
          className="it-loader-logo"
          width={px}
          height={px}
          style={{ width: px, height: px }}
          onError={() => setBroken(true)}
        />
      )}
      {label ? <span className="it-loader-label">{label}</span> : <span className="visually-hidden">Loading</span>}
    </div>
  )
}

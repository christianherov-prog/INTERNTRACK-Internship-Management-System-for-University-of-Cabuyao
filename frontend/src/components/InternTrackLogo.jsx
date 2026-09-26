const MARK = {
  light: '/interntrack-mark.png',
  dark: '/interntrack-mark-dark.png',
}

export const LOGO_SUBTITLE = 'Internship Management System'

export function InternTrackMark({ variant = 'light', className = '' }) {
  return (
    <img
      src={MARK[variant] || MARK.light}
      alt="INTERNTRACK"
      className={className}
    />
  )
}

export default function InternTrackLogo({
  variant = 'light',
  showSubtitle = false,
  className = '',
  markClassName = '',
  subtitleClassName = '',
}) {
  return (
    <div className={className}>
      <InternTrackMark variant={variant} className={markClassName} />
      {showSubtitle ? (
        <div className={subtitleClassName}>{LOGO_SUBTITLE}</div>
      ) : null}
    </div>
  )
}

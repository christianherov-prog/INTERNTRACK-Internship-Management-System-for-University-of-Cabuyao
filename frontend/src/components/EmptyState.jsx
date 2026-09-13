/** Consistent empty list / no-data panel for role pages. */
function EmptyState({
  icon = 'fa-inbox',
  title = 'Nothing here yet',
  message = 'There is no data to show right now.',
  action = null,
}) {
  return (
    <div className="text-center py-5 px-3 text-muted">
      <i className={`fa ${icon} fa-2x mb-3 d-block opacity-50`} aria-hidden="true" />
      <div className="fw-semibold text-dark mb-1">{title}</div>
      <p className="small mb-0" style={{ maxWidth: 420, margin: '0 auto' }}>{message}</p>
      {action}
    </div>
  )
}

export default EmptyState

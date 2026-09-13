/**
 * Shared async-aware button: shows a spinner and stays disabled while busy.
 * Drop-in for Bootstrap `btn` classes already used across roles.
 */
function AsyncButton({
  busy = false,
  busyLabel = null,
  children,
  className = 'btn btn-primary',
  type = 'button',
  disabled = false,
  onClick,
  ...rest
}) {
  const handleClick = (e) => {
    if (busy || disabled) {
      e.preventDefault()
      return
    }
    onClick?.(e)
  }

  return (
    <button
      type={type}
      className={className}
      disabled={disabled || busy}
      aria-busy={busy ? 'true' : undefined}
      onClick={handleClick}
      {...rest}
    >
      {busy ? (
        <>
          <i className="fa fa-spinner fa-spin me-1" aria-hidden="true" />
          {busyLabel || children}
        </>
      ) : (
        children
      )}
    </button>
  )
}

export default AsyncButton

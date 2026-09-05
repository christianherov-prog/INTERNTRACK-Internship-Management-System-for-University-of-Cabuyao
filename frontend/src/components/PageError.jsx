/** Consistent inline error banner for failed API loads across role pages. */
function PageError({ message = 'Something went wrong. Please try again.', onRetry = null }) {
  return (
    <div className="alert alert-danger d-flex align-items-center justify-content-between gap-3 mb-4" role="alert">
      <div>
        <i className="fa fa-exclamation-circle me-2" aria-hidden="true" />
        {message}
      </div>
      {onRetry && (
        <button type="button" className="btn btn-sm btn-outline-danger" onClick={onRetry}>
          Retry
        </button>
      )}
    </div>
  )
}

export default PageError

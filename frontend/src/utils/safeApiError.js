const SECRET_ERROR = /SQLSTATE|SQL:|Connection:\s*mysql|Unknown column|stack trace|Illuminate\\|SQLSTATE\[/i

/**
 * User-facing API error text. Never surface SQL, connection, or filesystem internals.
 */
export function safeApiError(err, fallback = 'Something went wrong. Please try again.') {
  const errors = err?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat().find(Boolean)
    if (typeof first === 'string' && first.trim() && !SECRET_ERROR.test(first)) {
      return first
    }
  }

  const raw = err?.response?.data?.message || ''
  if (!raw || SECRET_ERROR.test(raw)) {
    return fallback
  }

  return raw
}

export function safeUploadError(err) {
  return safeApiError(err, 'File upload failed. Please try again.')
}

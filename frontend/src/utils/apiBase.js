/**
 * Resolve Laravel API base URL for the current browser host.
 * Visiting localhost → API on localhost; visiting a LAN IP → API on that same IP.
 * Port/path still come from VITE_API_BASE_URL when set.
 */
export function resolveApiBaseUrl() {
  const fallback = 'http://127.0.0.1:8001/api/v1'
  const envUrl = (import.meta.env.VITE_API_BASE_URL || '').trim() || fallback

  if (typeof window === 'undefined' || !window.location?.hostname) {
    return envUrl.replace(/\/$/, '')
  }

  try {
    const url = new URL(envUrl)
    url.hostname = window.location.hostname
    return url.toString().replace(/\/$/, '')
  } catch {
    return `http://${window.location.hostname}:8001/api/v1`
  }
}

/** Backend origin without /api/v1 (avatars, Echo auth, storage). */
export function resolveApiOrigin() {
  return resolveApiBaseUrl().replace(/\/api\/v1\/?$/, '')
}

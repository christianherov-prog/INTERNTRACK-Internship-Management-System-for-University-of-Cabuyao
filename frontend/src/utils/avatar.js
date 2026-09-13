/**
 * Shared avatar URL helpers — navbar, settings, and any list views should use these
 * so they all read from the same AuthContext user.avatarUrl source of truth.
 */

/** Strip a prior ?t=… and append a fresh cache-buster so the browser reloads the image. */
export function withAvatarCacheBust(url, version = Date.now()) {
  if (!url) return null
  const base = String(url).split('?')[0]
  return `${base}?t=${version}`
}

/** Resolve the display src from the shared user object (AuthContext). */
export function getAvatarSrc(user) {
  if (!user?.avatarUrl) return null
  if (user.avatarVersion != null) {
    return withAvatarCacheBust(user.avatarUrl, user.avatarVersion)
  }
  return user.avatarUrl
}

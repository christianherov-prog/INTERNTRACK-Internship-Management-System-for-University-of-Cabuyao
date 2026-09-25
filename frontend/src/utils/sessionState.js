/**
 * User-scoped browser state.
 *
 * The session itself is an HttpOnly cookie shared by all tabs. Everything
 * here is per-tab UI state that belongs to ONE signed-in account and must
 * never reach the next account that signs in on the same tab:
 *
 *   interntrack_session             cached /auth/user payload (optimistic paint)
 *   interntrack_staff_workspace     Coordinator ⇄ Faculty workspace choice
 *   interntrack_active_internship   selected deployment → X-Internship-Id header
 *   interntrack_page_cache          cached page GET payloads (cleared via pageCache)
 *   interntrack_msg_last_<userId>   last opened message thread
 *
 * The active internship is stored together with the id of the user who
 * selected it, and is only ever read back for that same user.
 */

export const SESSION_KEY = 'interntrack_session'
export const STAFF_WORKSPACE_KEY = 'interntrack_staff_workspace'
export const ACTIVE_INTERNSHIP_KEY = 'interntrack_active_internship'
const MESSAGE_THREAD_PREFIX = 'interntrack_msg_last_'

function storage() {
  try {
    return typeof sessionStorage === 'undefined' ? null : sessionStorage
  } catch {
    return null
  }
}

/** Id of the account this tab is signed in as (from the cached session), or null. */
export function sessionUserId() {
  const s = storage()
  if (!s) return null
  try {
    const user = JSON.parse(s.getItem(SESSION_KEY) || 'null')
    return user?.id ?? null
  } catch {
    return null
  }
}

/**
 * The internship this tab selected — only if it was selected by `userId`.
 * Anything else (another account's id, the legacy bare-id format) is discarded.
 */
export function getActiveInternshipId(userId = sessionUserId()) {
  const s = storage()
  if (!s) return null
  const raw = s.getItem(ACTIVE_INTERNSHIP_KEY)
  if (!raw) return null

  let entry = null
  try {
    entry = JSON.parse(raw)
  } catch {
    entry = null
  }

  const owned = entry && typeof entry === 'object'
    && userId != null
    && String(entry.userId) === String(userId)
    && entry.internshipId != null
  if (!owned) {
    s.removeItem(ACTIVE_INTERNSHIP_KEY)
    return null
  }
  return String(entry.internshipId)
}

export function setActiveInternshipId(internshipId, userId = sessionUserId()) {
  const s = storage()
  if (!s) return
  if (internshipId == null || internshipId === '' || userId == null) {
    s.removeItem(ACTIVE_INTERNSHIP_KEY)
    return
  }
  s.setItem(ACTIVE_INTERNSHIP_KEY, JSON.stringify({ userId, internshipId: String(internshipId) }))
}

/** Remove every piece of per-account tab state (used on logout and before any login). */
export function clearUserScopedState() {
  const s = storage()
  if (!s) return
  const keys = []
  for (let i = 0; i < s.length; i += 1) {
    const key = s.key(i)
    if (
      key === SESSION_KEY
      || key === STAFF_WORKSPACE_KEY
      || key === ACTIVE_INTERNSHIP_KEY
      || (key && key.startsWith(MESSAGE_THREAD_PREFIX))
    ) {
      keys.push(key)
    }
  }
  keys.forEach((key) => s.removeItem(key))
}

/** Keep the tab's state consistent with the account the server reports. */
export function adoptSessionUser(user) {
  const s = storage()
  if (!s || !user) return
  const previous = sessionUserId()
  if (previous != null && String(previous) !== String(user.id)) {
    // A different account now owns this tab: nothing of the old one may survive.
    clearUserScopedState()
  }
  s.setItem(SESSION_KEY, JSON.stringify(user))
  // Drops an internship id that does not belong to this account.
  getActiveInternshipId(user.id)
}

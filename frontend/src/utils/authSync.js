/**
 * Cross-tab auth signals. The session itself is an HttpOnly cookie shared by
 * every tab; these events only tell other open tabs to refresh their UI state
 * after a login (possibly as another user) or a logout.
 */
const STORAGE_KEY = 'interntrack_auth_event'
const CHANNEL_NAME = 'interntrack-auth'

let channel = null
function getChannel() {
  if (channel || typeof BroadcastChannel === 'undefined') return channel
  try {
    channel = new BroadcastChannel(CHANNEL_NAME)
  } catch {
    channel = null
  }
  return channel
}

/** @param {'login'|'logout'} type */
export function broadcastAuthEvent(type, userId = null) {
  const event = { type, userId, at: Date.now() }
  try {
    getChannel()?.postMessage(event)
  } catch { /* ignore */ }
  try {
    // storage events reach other tabs even where BroadcastChannel is missing.
    localStorage.setItem(STORAGE_KEY, JSON.stringify(event))
  } catch { /* storage may be blocked */ }
}

/** Subscribe to auth events from other tabs. Returns an unsubscribe function. */
export function onAuthEvent(handler) {
  const seen = new Set()
  const dispatch = (event) => {
    if (!event || typeof event !== 'object') return
    const key = `${event.type}:${event.at}`
    if (seen.has(key)) return
    seen.add(key)
    handler(event)
  }

  const ch = getChannel()
  const onMessage = (e) => dispatch(e.data)
  ch?.addEventListener('message', onMessage)

  const onStorage = (e) => {
    if (e.key !== STORAGE_KEY || !e.newValue) return
    try {
      dispatch(JSON.parse(e.newValue))
    } catch { /* ignore */ }
  }
  window.addEventListener('storage', onStorage)

  return () => {
    ch?.removeEventListener('message', onMessage)
    window.removeEventListener('storage', onStorage)
  }
}

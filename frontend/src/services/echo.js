import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echoInstance = null
let liveStatus = 'polling' // 'live' | 'polling' | 'unavailable'
const statusListeners = new Set()

function notifyStatus(next) {
  liveStatus = next
  statusListeners.forEach((fn) => {
    try { fn(next) } catch { /* ignore */ }
  })
}

/** Resolve API origin (without /api/v1) for broadcasting auth. */
function apiOrigin() {
  const base = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8001/api/v1'
  return base.replace(/\/api\/v1\/?$/, '')
}

/**
 * Init Laravel Echo → Reverb. Safe no-op when VITE_REVERB_APP_KEY is missing.
 * On connect failure / disconnect, status becomes "polling" so UI can speed up HTTP refresh.
 */
export function initEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key) {
    notifyStatus('polling')
    return null
  }

  if (echoInstance) {
    return echoInstance
  }

  window.Pusher = Pusher

  const token = sessionStorage.getItem('interntrack_token')
  try {
    echoInstance = new Echo({
      broadcaster: 'reverb',
      key,
      wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${apiOrigin()}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      },
    })

    const pusher = echoInstance.connector?.pusher
    if (pusher?.connection) {
      pusher.connection.bind('connected', () => notifyStatus('live'))
      pusher.connection.bind('disconnected', () => notifyStatus('polling'))
      pusher.connection.bind('unavailable', () => notifyStatus('polling'))
      pusher.connection.bind('failed', () => notifyStatus('polling'))
      pusher.connection.bind('error', () => notifyStatus('polling'))
    } else {
      // Assume live until proven otherwise; polling still runs as safety net.
      notifyStatus('live')
    }
  } catch {
    echoInstance = null
    notifyStatus('polling')
    return null
  }

  return echoInstance
}

export function getEcho() {
  return echoInstance || initEcho()
}

export function disconnectEcho() {
  if (echoInstance) {
    try { echoInstance.disconnect() } catch { /* ignore */ }
    echoInstance = null
  }
  notifyStatus('polling')
}

/** Current realtime mode: live (WebSocket) or polling (HTTP refresh). */
export function getLiveStatus() {
  return liveStatus
}

/** Subscribe to live/polling status changes. Returns unsubscribe. */
export function subscribeLiveStatus(listener) {
  statusListeners.add(listener)
  listener(liveStatus)
  return () => statusListeners.delete(listener)
}

/**
 * Recommended notification poll interval (ms).
 * Faster when Reverb is down / not configured; slower when WebSocket is live.
 */
export function notificationPollMs() {
  return liveStatus === 'live' ? 60000 : 15000
}

/** Recommended chat poll interval (ms). */
export function messagePollMs() {
  return liveStatus === 'live' ? 30000 : 10000
}

/** Subscribe to private user notification channel. Returns unsubscribe fn. */
export function subscribeUserNotifications(userId, onNotification) {
  const echo = getEcho()
  if (!echo || !userId) {
    notifyStatus(import.meta.env.VITE_REVERB_APP_KEY ? liveStatus : 'polling')
    return () => {}
  }

  const channelName = `App.Models.User.${userId}`
  try {
    const channel = echo.private(channelName)
    channel.listen('.notification.created', (payload) => {
      onNotification?.(payload)
    })
    return () => {
      echo.leave(channelName)
    }
  } catch {
    notifyStatus('polling')
    return () => {}
  }
}

/** Subscribe to conversation messages. Returns unsubscribe fn. */
export function subscribeConversation(conversationId, onMessage) {
  const echo = getEcho()
  if (!echo || !conversationId) {
    return () => {}
  }

  const channelName = `conversation.${conversationId}`
  try {
    const channel = echo.private(channelName)
    channel.listen('.message.sent', (payload) => {
      onMessage?.(payload)
    })
    return () => {
      echo.leave(channelName)
    }
  } catch {
    notifyStatus('polling')
    return () => {}
  }
}

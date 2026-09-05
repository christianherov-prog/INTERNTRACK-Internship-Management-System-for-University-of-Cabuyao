import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react'

const ToastContext = createContext(null)

let toastId = 0

/**
 * App-wide toast host. Prefer top-right so it doesn't block form UI.
 * Usage: const toast = useToast(); toast.success('…'); toast.error('…');
 */
export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])
  const timers = useRef(new Map())

  const dismiss = useCallback((id) => {
    const t = timers.current.get(id)
    if (t) {
      clearTimeout(t)
      timers.current.delete(id)
    }
    setToasts((prev) => prev.filter((x) => x.id !== id))
  }, [])

  const push = useCallback((type, message, durationMs = 3000) => {
    const id = ++toastId
    setToasts((prev) => [...prev, { id, type, message }])
    const timer = setTimeout(() => dismiss(id), durationMs)
    timers.current.set(id, timer)
    return id
  }, [dismiss])

  const api = useMemo(() => ({
    success: (message, durationMs) => push('success', message, durationMs),
    error: (message, durationMs) => push('error', message, durationMs ?? 4500),
    info: (message, durationMs) => push('info', message, durationMs),
    dismiss,
  }), [push, dismiss])

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="toast-viewport" aria-live="polite" aria-relevant="additions">
        {toasts.map((t) => (
          <div
            key={t.id}
            className={`app-toast app-toast--${t.type}`}
            role="status"
          >
            <i
              className={`fa ${
                t.type === 'success' ? 'fa-circle-check'
                  : t.type === 'error' ? 'fa-circle-exclamation'
                    : 'fa-circle-info'
              }`}
              aria-hidden="true"
            />
            <span className="app-toast__message">{t.message}</span>
            <button
              type="button"
              className="app-toast__close"
              aria-label="Dismiss"
              onClick={() => dismiss(t.id)}
            >
              <i className="fa fa-times" aria-hidden="true" />
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    throw new Error('useToast must be used within ToastProvider')
  }
  return ctx
}

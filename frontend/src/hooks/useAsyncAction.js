import { useCallback, useRef, useState } from 'react'

/**
 * Shared async-action guard: disables the trigger while a request is in flight
 * and ignores re-entrant clicks for the same key (or the global lock).
 */
export function useAsyncAction() {
  const [busyKey, setBusyKey] = useState(null)
  const inFlight = useRef(false)

  const run = useCallback(async (key, action) => {
    if (inFlight.current) return undefined
    inFlight.current = true
    setBusyKey(key ?? true)
    try {
      return await action()
    } finally {
      inFlight.current = false
      setBusyKey(null)
    }
  }, [])

  const isBusy = useCallback((key) => {
    if (busyKey == null) return false
    if (key == null) return Boolean(busyKey)
    return busyKey === key || busyKey === true
  }, [busyKey])

  return {
    busyKey,
    busy: busyKey != null,
    isBusy,
    run,
  }
}

export default useAsyncAction

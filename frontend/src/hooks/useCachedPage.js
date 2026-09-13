import { useCallback, useState } from 'react'
import { cacheGet, cacheHas, cacheRun } from '../utils/pageCache'

/**
 * Stale-while-revalidate helper for page-level GET data.
 *
 * Navigation never blocks on a full-page spinner: cached payloads render
 * immediately, and first visits show the page shell while `run()` fills in.
 * Action-level loading (submit/approve/export) stays in the calling page.
 *
 * `pending` is true only until the first fetch for this key finishes, and
 * only when nothing is cached yet — use it for an inline placeholder, not a
 * full-page blank spinner.
 */
export function useCachedPage(key) {
  const [pending, setPending] = useState(() => !cacheHas(key))

  const run = useCallback(
    async (producer) => {
      try {
        return await cacheRun(key, producer)
      } catch (err) {
        if (cacheHas(key)) return cacheGet(key)
        throw err
      } finally {
        setPending(false)
      }
    },
    [key],
  )

  return {
    // True only for the first uncached fetch. Cached navigations stay false
    // so the page shell and stale data remain visible during background refresh.
    loading: pending,
    pending,
    seed: cacheGet(key),
    run,
  }
}

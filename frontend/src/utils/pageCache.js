/**
 * Session-scoped cache for page GET payloads.
 * Survives in-app route changes (which unmount page components) and tab reloads
 * via sessionStorage, so revisits can render immediately while a background
 * refresh runs. Cleared on login/logout so another account never sees leftover data.
 */

const STORAGE_KEY = 'interntrack_page_cache'

const store = new Map()
const inflight = new Map()

function readPersisted() {
  if (typeof sessionStorage === 'undefined') return
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return
    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object') return
    Object.entries(parsed).forEach(([key, data]) => {
      store.set(key, { data, at: Date.now() })
    })
  } catch {
    sessionStorage.removeItem(STORAGE_KEY)
  }
}

function persist() {
  if (typeof sessionStorage === 'undefined') return
  try {
    const payload = {}
    store.forEach((value, key) => {
      payload[key] = value.data
    })
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
  } catch {
    // Quota or serialization failure — keep the in-memory cache only.
  }
}

readPersisted()

export function cacheHas(key) {
  return store.has(key)
}

export function cacheGet(key) {
  return store.has(key) ? store.get(key).data : undefined
}

export function cacheSet(key, data) {
  store.set(key, { data, at: Date.now() })
  persist()
}

export function cacheDelete(key) {
  store.delete(key)
  inflight.delete(key)
  persist()
}

export function invalidateStudentPortfolio() {
  cacheDelete('student:portfolio')
}

/** Invalidate student documents + shared compliance surfaces after upload/review. */
export function invalidateStudentDocuments() {
  const keys = []
  store.forEach((_, key) => {
    if (
      key === 'student:documents'
      || key === 'student:dashboard'
      || key === 'student:records'
      || key.startsWith('faculty:assigned')
      || key.startsWith('coordinator:records')
      || key.includes('student-progress')
      || key.includes('compliance')
      || key.includes('documents')
    ) {
      keys.push(key)
    }
  })
  keys.forEach(cacheDelete)
  invalidateStudentPortfolio()
}

/** Invalidate attendance-related student caches after clock/break/correction mutations. */
export function invalidateStudentAttendance() {
  const keys = []
  store.forEach((_, key) => {
    if (key === 'student:attendance' || key.startsWith('student:attendance:') || key === 'student:attendance-hub' || key === 'student:dashboard' || key === 'student:records') {
      keys.push(key)
    }
  })
  keys.forEach(cacheDelete)
  invalidateOfficialFormCaches()
}

export function invalidateOfficialFormCaches() {
  invalidateStudentPortfolio()
  const keys = []
  store.forEach((_, key) => {
    if (
      key.startsWith('faculty:assigned')
      || key.startsWith('supervisor:assigned')
      || key.startsWith('coordinator:records')
      || key.includes('student-progress')
    ) {
      keys.push(key)
    }
  })
  keys.forEach(cacheDelete)
}

export function cacheClear() {
  store.clear()
  inflight.clear()
  if (typeof sessionStorage !== 'undefined') {
    sessionStorage.removeItem(STORAGE_KEY)
  }
}

function dedupe(key, producer) {
  if (inflight.has(key)) return inflight.get(key)
  const pending = Promise.resolve()
    .then(producer)
    .finally(() => {
      inflight.delete(key)
    })
  inflight.set(key, pending)
  return pending
}

/**
 * Fetch (or join an in-flight fetch) and store the result.
 * Caller is responsible for applying the returned value to React state.
 */
export function cacheRun(key, producer) {
  return dedupe(key, producer).then((data) => {
    cacheSet(key, data)
    return data
  })
}

/** Warm the cache without throwing; no-ops if the key is already filled or in flight. */
export function prefetchPage(key, producer) {
  if (cacheHas(key) || inflight.has(key)) return Promise.resolve(cacheGet(key))
  return cacheRun(key, producer).catch(() => undefined)
}

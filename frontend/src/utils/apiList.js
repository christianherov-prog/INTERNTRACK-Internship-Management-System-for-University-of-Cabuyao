/**
 * Normalize list API payloads to { items, meta }.
 * Prefers the standard envelope: { data: [...], meta: {...} }.
 * Falls back to legacy domain keys (students, journals, …) during migration.
 */
const LEGACY_KEYS = [
  'students',
  'interns',
  'journals',
  'documents',
  'announcements',
  'companies',
  'attendance',
  'notifications',
  'feedback',
  'evaluations',
  'history',
]

export function unwrapList(payload) {
  if (!payload) return { items: [], meta: null }

  if (Array.isArray(payload.data) && (payload.meta || payload.data)) {
    return {
      items: payload.data,
      meta: payload.meta || null,
    }
  }

  for (const key of LEGACY_KEYS) {
    if (payload[key] == null) continue
    const block = payload[key]
    if (Array.isArray(block)) {
      return { items: block, meta: null }
    }
    if (Array.isArray(block?.data)) {
      return {
        items: block.data,
        meta: {
          current_page: block.current_page,
          last_page: block.last_page,
          per_page: block.per_page,
          total: block.total,
        },
      }
    }
  }

  return { items: [], meta: null }
}

/** For grouped endpoints: { data: { pending, completed } } (with legacy fallback). */
export function unwrapGroups(payload, keys = ['pending', 'completed']) {
  const source = payload?.data && typeof payload.data === 'object' && !Array.isArray(payload.data)
    ? payload.data
    : payload || {}

  const out = {}
  keys.forEach((key) => {
    out[key] = Array.isArray(source[key]) ? source[key] : []
  })
  return out
}

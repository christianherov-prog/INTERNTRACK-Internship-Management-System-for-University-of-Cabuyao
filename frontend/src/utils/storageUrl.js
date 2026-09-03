/**
 * Public avatar / legacy public-disk URLs only.
 * Journals, documents, signatures, and portfolio files are private —
 * use AuthenticatedFileLink / AuthenticatedFileImage (GET /files/download).
 */
export function backendOrigin() {
  return import.meta.env.VITE_API_BASE_URL?.replace(/\/api\/v1\/?$/, '') || 'http://127.0.0.1:8001'
}

export function storageUrl(path) {
  if (!path) return ''
  if (/^https?:\/\//i.test(path)) return path
  return `${backendOrigin()}/storage/${String(path).replace(/^\/+/, '')}`
}

import { resolveApiOrigin } from './apiBase'

/**
 * Public avatar / legacy public-disk URLs only.
 * Journals, documents, signatures, and portfolio files are private —
 * use AuthenticatedFileLink / AuthenticatedFileImage (GET /files/download).
 */
export function backendOrigin() {
  return resolveApiOrigin()
}

export function storageUrl(path) {
  if (!path) return ''
  if (/^https?:\/\//i.test(path)) return path
  return `${backendOrigin()}/storage/${String(path).replace(/^\/+/, '')}`
}

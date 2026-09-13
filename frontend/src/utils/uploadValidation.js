import {
  UPLOAD_MAX_BYTES,
  UPLOAD_MAX_FILES,
  UPLOAD_MAX_MB,
  UPLOAD_MAX_REQUEST_BYTES,
  UPLOAD_MAX_REQUEST_MB,
} from '../config/uploads'
import { safeApiError } from './safeApiError'

/** Human-readable size (e.g. 524 KB, 2.4 MB). */
export function formatFileSize(bytes) {
  const n = Number(bytes)
  if (!Number.isFinite(n) || n < 0) return '—'
  if (n < 1024) return `${Math.round(n)} B`
  if (n < 1024 * 1024) {
    const kb = n / 1024
    return `${kb >= 10 ? Math.round(kb) : kb.toFixed(1)} KB`
  }
  const mb = n / (1024 * 1024)
  return `${mb >= 10 ? mb.toFixed(1) : mb.toFixed(1)} MB`
}

export function uploadLimitHint(allowedTypesLabel) {
  const types = allowedTypesLabel ? `${allowedTypesLabel} · ` : ''
  return `${types}Maximum ${UPLOAD_MAX_MB} MB per file`
}

export function oversizedFileMessage(file, maxBytes = UPLOAD_MAX_BYTES) {
  const name = file?.name || 'File'
  const limitMb = Math.round(maxBytes / (1024 * 1024))
  return `"${name}" exceeds the ${limitMb} MB upload limit.`
}

/**
 * Validate one or more files before upload.
 * @returns {{ ok: true, files: File[] } | { ok: false, error: string, invalidFiles: File[] }}
 */
export function validateUploadFiles(files, options = {}) {
  const list = (Array.isArray(files) ? files : [files]).filter(Boolean)
  const maxBytes = options.maxBytes ?? UPLOAD_MAX_BYTES
  const maxFiles = options.maxFiles ?? UPLOAD_MAX_FILES
  const maxRequestBytes = options.maxRequestBytes ?? UPLOAD_MAX_REQUEST_BYTES

  if (list.length === 0) {
    return { ok: true, files: [] }
  }

  if (list.length > maxFiles) {
    return {
      ok: false,
      error: `You can upload at most ${maxFiles} files at a time.`,
      invalidFiles: list.slice(maxFiles),
    }
  }

  const oversized = list.filter((f) => (f.size || 0) > maxBytes)
  if (oversized.length > 0) {
    return {
      ok: false,
      error: oversizedFileMessage(oversized[0], maxBytes),
      invalidFiles: oversized,
    }
  }

  const total = list.reduce((sum, f) => sum + (f.size || 0), 0)
  if (total > maxRequestBytes) {
    return {
      ok: false,
      error: `Selected files total ${formatFileSize(total)}, which exceeds the ${UPLOAD_MAX_REQUEST_MB} MB request limit. Remove some files and try again.`,
      invalidFiles: list,
    }
  }

  return { ok: true, files: list }
}

/** Prefer structured upload errors (413 / 422) over generic Network Error. */
export function uploadErrorMessage(err, fallback = 'File upload failed. Please try again.') {
  const status = err?.response?.status
  if (status === 413) {
    return err?.response?.data?.message
      || `The selected file is too large. Maximum allowed size is ${UPLOAD_MAX_MB} MB per file.`
  }

  if (!err?.response && (err?.code === 'ERR_NETWORK' || /network error/i.test(String(err?.message || '')))) {
    // Often a proxy/body-size reject that never reached Laravel.
    return `The selected file may be too large. Maximum allowed size is ${UPLOAD_MAX_MB} MB per file.`
  }

  return safeApiError(err, fallback)
}

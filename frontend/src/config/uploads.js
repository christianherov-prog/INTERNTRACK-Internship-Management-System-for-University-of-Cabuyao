/**
 * Shared frontend upload policy — keep in sync with config/interntrack.php
 * (INTERNTRACK_UPLOAD_MAX_MB / INTERNTRACK_MAX_UPLOAD_MB).
 */
const envMb = Number(import.meta.env.VITE_INTERNTRACK_MAX_UPLOAD_MB)
const envRequestMb = Number(import.meta.env.VITE_INTERNTRACK_MAX_UPLOAD_REQUEST_MB)
const envMaxFiles = Number(import.meta.env.VITE_INTERNTRACK_MAX_UPLOAD_FILES)

export const UPLOAD_MAX_MB = Number.isFinite(envMb) && envMb > 0 ? envMb : 10
export const UPLOAD_MAX_BYTES = UPLOAD_MAX_MB * 1024 * 1024
export const UPLOAD_MAX_REQUEST_MB = Number.isFinite(envRequestMb) && envRequestMb > 0
  ? Math.max(UPLOAD_MAX_MB, envRequestMb)
  : 30
export const UPLOAD_MAX_REQUEST_BYTES = UPLOAD_MAX_REQUEST_MB * 1024 * 1024
export const UPLOAD_MAX_FILES = Number.isFinite(envMaxFiles) && envMaxFiles > 0 ? envMaxFiles : 5

/**
 * Smoke checks for shared upload size policy + Manage Requirements UX.
 * Run: node scripts/check-upload-limits-ui.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const uploadsConfig = fs.readFileSync(path.join(root, 'src/config/uploads.js'), 'utf8')
const validation = fs.readFileSync(path.join(root, 'src/utils/uploadValidation.js'), 'utf8')
const reqPage = fs.readFileSync(path.join(root, 'src/pages/shared/ManageRequirementsTemplates.jsx'), 'utf8')
const docsPage = fs.readFileSync(path.join(root, 'src/pages/student/StudentDocuments.jsx'), 'utf8')
const backendConfig = fs.readFileSync(path.join(root, '../backend/config/interntrack.php'), 'utf8')
const uploadLimits = fs.readFileSync(path.join(root, '../backend/app/Support/UploadLimits.php'), 'utf8')
const bootstrap = fs.readFileSync(path.join(root, '../backend/bootstrap/app.php'), 'utf8')

const checks = [
  ['frontend uploads config exists', /UPLOAD_MAX_MB/.test(uploadsConfig)],
  ['frontend defaults to 10 MB', /:\s*10\b/.test(uploadsConfig)],
  ['validateUploadFiles helper', /export function validateUploadFiles/.test(validation)],
  ['uploadErrorMessage handles 413', /status === 413/.test(validation)],
  ['formatFileSize helper', /export function formatFileSize/.test(validation)],
  ['backend config has upload_max_mb', /upload_max_mb/.test(backendConfig)],
  ['backend UploadLimits class', /class UploadLimits/.test(uploadLimits)],
  ['PostTooLarge uses UploadLimits', /UploadLimits::requestTooLargeMessage/.test(bootstrap)],
  ['requirements page validates uploads', /validateUploadFiles/.test(reqPage)],
  ['requirements page shows limit hint', /uploadLimitHint/.test(reqPage)],
  ['requirements page uses uploadErrorMessage', /uploadErrorMessage/.test(reqPage)],
  ['requirements blocks submit on fileError', /disabled=\{submitting \|\| !!fileError\}/.test(reqPage)],
  ['student documents validates uploads', /validateUploadFiles/.test(docsPage)],
  ['student documents uses uploadErrorMessage', /uploadErrorMessage/.test(docsPage)],
]

let failed = 0
for (const [label, ok] of checks) {
  if (!ok) {
    console.error('FAIL', label)
    failed += 1
  } else {
    console.log('PASS', label)
  }
}
if (failed) process.exit(1)
console.log(`OK ${checks.length} upload-limit checks`)

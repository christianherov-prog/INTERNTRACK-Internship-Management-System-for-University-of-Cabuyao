/**
 * Smoke checks for feedback limits + hover cleanup.
 * Run: node scripts/check-feedback-and-hover-ui.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const feedbackPage = fs.readFileSync(path.join(root, 'src/pages/supervisor/SupervisorFeedback.jsx'), 'utf8')
const feedbackConfig = fs.readFileSync(path.join(root, 'src/config/feedback.js'), 'utf8')
const css = fs.readFileSync(path.join(root, 'src/styles/styles.css'), 'utf8')
const pageCache = fs.readFileSync(path.join(root, 'src/utils/pageCache.js'), 'utf8')

const checks = [
  ['shared max length 1000', /FEEDBACK_MAX_LENGTH\s*=\s*1000/.test(feedbackConfig)],
  ['shared min length 5', /FEEDBACK_MIN_LENGTH\s*=\s*5/.test(feedbackConfig)],
  ['page uses shared max length', /FEEDBACK_MAX_LENGTH/.test(feedbackPage)],
  ['page shows character counter', /feedback\.length\} \/ \{FEEDBACK_MAX_LENGTH/.test(feedbackPage)],
  ['textarea maxLength bound', /maxLength=\{FEEDBACK_MAX_LENGTH\}/.test(feedbackPage)],
  ['optimistic merge after submit', /mergeFeedbackRow/.test(feedbackPage)],
  ['cacheDelete clears inflight', /inflight\.delete\(key\)/.test(pageCache)],
  ['feature-badge has no hover transform', !/\.feature-badge:hover\s*\{[^}]*transform/.test(css)],
  ['topbar-avatar no scale hover', !/\.topbar-avatar:hover\s*\{[^}]*transform:\s*scale/.test(css)],
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
console.log(`OK ${checks.length} feedback/hover checks`)

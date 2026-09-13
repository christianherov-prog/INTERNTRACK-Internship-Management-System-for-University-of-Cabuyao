/**
 * Smoke checks for Login centering, PNC background, and single-row features.
 * Run: node scripts/check-login-features-layout.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const css = fs.readFileSync(path.join(root, 'src/styles/styles.css'), 'utf8')
const jsx = fs.readFileSync(path.join(root, 'src/pages/LoginPage.jsx'), 'utf8')

const mobileBlock = css.match(/@media \(max-width: 768px\) \{[\s\S]*?(?=\n\/\* Short phones|\n\/\* ── ≤ 576px)/)?.[0] ?? ''

const checks = [
  ['jsx has Secure Access', /Secure Access/.test(jsx)],
  ['jsx has Role-Based Access', /Role-Based Access/.test(jsx)],
  ['jsx has Internship Tracking', /Internship Tracking/.test(jsx)],
  ['jsx does not use secondary row', !/login-features-row-secondary/.test(jsx)],
  ['page uses dvh min-height', /min-height:\s*100dvh/.test(css)],
  ['mobile reuses pnc.jpg background', /background-image:\s*url\('\/assets\/pnc\.jpg'\)/.test(mobileBlock)],
  ['mobile centers login-split-right', /\.login-split-right\s*\{[^}]*align-items:\s*center/s.test(mobileBlock)],
  ['mobile justifies center', /\.login-split-right\s*\{[^}]*justify-content:\s*center/s.test(mobileBlock)],
  ['mobile keeps features flex-direction row', /\.login-features\s*\{[^}]*flex-direction:\s*row/s.test(mobileBlock)],
  ['mobile features nowrap', /\.login-features\s*\{[^}]*flex-wrap:\s*nowrap/s.test(css)],
  ['uses clamp for feature font', /font-size:\s*clamp\(/.test(css)],
  ['smart-detection uses clamp padding', /\.smart-detection-box[\s\S]*?padding:\s*clamp\(/.test(css)],
  ['smart-detection uses clamp title size', /\.smart-detection-header[\s\S]*?font-size:\s*clamp\(/.test(css)],
  ['smart-detection uses clamp body size', /\.smart-detection-text[\s\S]*?font-size:\s*clamp\(/.test(css)],
  ['smart-detection has reduced-motion guard', /prefers-reduced-motion[\s\S]*smart-detection-box/.test(css)],
  ['tablet smart-detection media present', /@media \(max-width: 1024px\) and \(min-width: 769px\)[\s\S]*smart-detection-box/.test(css)],
  ['jsx keeps Smart account detection wording', /Smart account detection/.test(jsx)],
  ['jsx keeps username/student number guidance', /username, student number, employee ID, supervisor ID, or registered email/.test(jsx)],
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
console.log(`OK ${checks.length} login layout checks`)

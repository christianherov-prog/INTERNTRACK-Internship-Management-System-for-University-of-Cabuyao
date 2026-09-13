/**
 * Smoke checks for dual-role workspace switcher.
 * Run: node scripts/check-role-switcher-ui.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const switcher = fs.readFileSync(path.join(root, 'src/components/RoleWorkspaceSwitcher.jsx'), 'utf8')
const topbar = fs.readFileSync(path.join(root, 'src/components/Topbar.jsx'), 'utf8')
const hook = fs.readFileSync(path.join(root, 'src/hooks/useStaffWorkspace.js'), 'utf8')
const css = fs.readFileSync(path.join(root, 'src/styles/styles.css'), 'utf8')

const checks = [
  ['Topbar uses RoleWorkspaceSwitcher', /RoleWorkspaceSwitcher/.test(topbar)],
  ['Topbar no native StaffWorkspaceSwitcher select', !/StaffWorkspaceSwitcher/.test(topbar)],
  ['switcher shows Switch workspace', /Switch workspace/.test(switcher)],
  ['switcher lists Coordinator', /Coordinator/.test(switcher)],
  ['switcher lists Faculty Supervisor', /Faculty Supervisor/.test(switcher)],
  ['aria-expanded present', /aria-expanded/.test(switcher)],
  ['Escape closes menu', /Escape/.test(switcher)],
  ['hook clears page cache on switch', /cacheClear\(\)/.test(hook)],
  ['hook only for coordinator role', /user\?\.role === 'coordinator'/.test(hook)],
  ['CSS has workspace-switcher', /\.workspace-switcher\s*\{/.test(css)],
  ['CSS respects reduced motion', /prefers-reduced-motion[\s\S]*workspace-switcher/.test(css)],
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
console.log(`OK ${checks.length} role switcher UI checks`)

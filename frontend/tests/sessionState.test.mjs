// AUTH-SWITCH-01..05 / 07 — per-account tab state never leaks to the next account.
// Run with: npm test   (Node's built-in test runner, no extra dependencies)
import { test, beforeEach } from 'node:test'
import assert from 'node:assert/strict'

class MemoryStorage {
  constructor() { this.map = new Map() }
  get length() { return this.map.size }
  key(i) { return [...this.map.keys()][i] ?? null }
  getItem(k) { return this.map.has(k) ? this.map.get(k) : null }
  setItem(k, v) { this.map.set(k, String(v)) }
  removeItem(k) { this.map.delete(k) }
  clear() { this.map.clear() }
}

globalThis.sessionStorage = new MemoryStorage()

const {
  ACTIVE_INTERNSHIP_KEY,
  SESSION_KEY,
  STAFF_WORKSPACE_KEY,
  adoptSessionUser,
  clearUserScopedState,
  getActiveInternshipId,
  setActiveInternshipId,
} = await import('../src/utils/sessionState.js')

const A = { id: 101, role: 'student' }
const B = { id: 202, role: 'student' }
const C = { id: 303, role: 'student' }

beforeEach(() => sessionStorage.clear())

/** What the SPA does on login (AuthContext.login) and on logout (clearSession). */
function login(user) { clearUserScopedState(); adoptSessionUser(user) }
function logout() { clearUserScopedState() }

test('AUTH-SWITCH-01: Student A login stores Student A state', () => {
  login(A)
  setActiveInternshipId(5001)
  assert.equal(getActiveInternshipId(), '5001')
  assert.equal(JSON.parse(sessionStorage.getItem(SESSION_KEY)).id, A.id)
})

test('AUTH-SWITCH-02: logout clears every user-scoped key', () => {
  login(A)
  setActiveInternshipId(5001)
  sessionStorage.setItem(STAFF_WORKSPACE_KEY, 'faculty')
  sessionStorage.setItem(`interntrack_msg_last_${A.id}`, '{"k":1}')
  sessionStorage.setItem('unrelated_ui_pref', 'keep')
  logout()
  assert.equal(sessionStorage.getItem(ACTIVE_INTERNSHIP_KEY), null)
  assert.equal(sessionStorage.getItem(SESSION_KEY), null)
  assert.equal(sessionStorage.getItem(STAFF_WORKSPACE_KEY), null)
  assert.equal(sessionStorage.getItem(`interntrack_msg_last_${A.id}`), null)
  assert.equal(sessionStorage.getItem('unrelated_ui_pref'), 'keep', 'non-account UI state is left alone')
})

test('AUTH-SWITCH-03/04: Student B never inherits Student A internship id', () => {
  login(A)
  setActiveInternshipId(5001)
  logout()
  login(B)
  assert.equal(getActiveInternshipId(), null, 'B starts without a selection → backend resolves B\'s own internship')
})

test('AUTH-SWITCH-04: a stale selection from another account is discarded even without a logout', () => {
  // e.g. the cookie session changed underneath the tab (login elsewhere / expiry).
  login(A)
  setActiveInternshipId(5001)
  adoptSessionUser(B) // /auth/user now reports B
  assert.equal(getActiveInternshipId(), null)
  assert.equal(sessionStorage.getItem(ACTIVE_INTERNSHIP_KEY), null)
})

test('AUTH-SWITCH-04: legacy bare-id values (pre-fix format) are never sent', () => {
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(B))
  sessionStorage.setItem(ACTIVE_INTERNSHIP_KEY, '5001')
  assert.equal(getActiveInternshipId(), null)
})

test('AUTH-SWITCH-05: 10+ logout/login cycles A→B→C→A never expose a previous id', () => {
  const internshipOf = { [A.id]: 5001, [B.id]: 6002, [C.id]: 7003 }
  const order = [A, B, C]
  for (let cycle = 0; cycle < 12; cycle += 1) {
    const user = order[cycle % order.length]
    login(user)
    assert.equal(getActiveInternshipId(), null, `cycle ${cycle}: fresh login has no inherited selection`)
    setActiveInternshipId(internshipOf[user.id])
    assert.equal(getActiveInternshipId(), String(internshipOf[user.id]))
    logout()
  }
})

test('AUTH-SWITCH-07: same account (new tab / reload) keeps its own selection', () => {
  login(A)
  setActiveInternshipId(5001)
  adoptSessionUser(A) // /auth/user confirms the same account on reload
  assert.equal(getActiveInternshipId(), '5001')
})

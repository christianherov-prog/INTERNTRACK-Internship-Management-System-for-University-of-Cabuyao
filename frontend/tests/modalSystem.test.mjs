// UI-GLOBAL-01..10: the shared modal system (AppModal + styles/modal-system.css)
// that every role's dialogs, report previews and document previews use.
import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { importBundle, FRONTEND_ROOT } from './helpers/bundle.mjs'

const SRC = join(FRONTEND_ROOT, 'src')
const read = (rel) => readFileSync(join(SRC, rel), 'utf8').replace(/\r\n/g, '\n')
const CSS = read('styles/modal-system.css')

/** Declarations of `selector` (outside or inside the given @media query). */
function rule(selector, media = null) {
  let scope = CSS
  if (media) {
    const at = CSS.indexOf(`@media ${media}`)
    assert.ok(at !== -1, `missing @media ${media}`)
    scope = CSS.slice(at)
  }
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  // The selector must start the rule (line start or after `}` / `,`), so
  // `.a > .b {` never counts as the rule for `.b`.
  const m = scope.match(new RegExp(`(?:^|[},])[ \\t]*${escaped}\\s*\\{([^}]*)\\}`, 'm'))
  assert.ok(m, `missing rule ${selector}${media ? ` in ${media}` : ''}`)
  return m[1].replace(/\s+/g, ' ')
}

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    if (statSync(p).isDirectory()) walk(p, out)
    else if (/\.(jsx?|mjs)$/.test(name)) out.push(p)
  }
  return out
}

let ui
before(async () => {
  ui = await importBundle(`
    export { default as AppModal } from './src/components/modals/AppModal.jsx'
    export { default as ReportExportModal } from './src/components/modals/ReportExportModal.jsx'
    export * as manager from './src/components/modals/modalManager.js'
    export { renderToStaticMarkup } from 'react-dom/server'
    export { createElement as h } from 'react'
  `)
})

const render = (el) => ui.renderToStaticMarkup(el)

test('UI-GLOBAL-01 data dialogs use the wide responsive DATA size', () => {
  const html = render(ui.h(ui.AppModal, { title: 'Submissions for: X', size: 'data', onClose() {} }, 'rows'))
  assert.match(html, /class="it-modal it-modal--data"/)
  assert.match(rule('.it-modal--data'), /max-width: 1680px/)
  assert.match(rule('.it-modal'), /width: 100%/)
  // Unknown sizes fall back to md, never an unstyled dialog.
  assert.equal(ui.manager.modalSizeClass('huge'), 'it-modal--md')
  for (const size of ['sm', 'md', 'lg', 'data', 'document']) {
    assert.ok(CSS.includes(`.it-modal--${size}`), `size ${size} is styled`)
  }
  const req = read('pages/shared/ManageRequirementsTemplates.jsx')
  const submissions = req.slice(req.indexOf('{/* Submissions Modal */}'), req.indexOf('{/* Review Modal */}'))
  assert.match(submissions, /size="data"/)
  assert.match(submissions, /fillBody/)
})

test('UI-GLOBAL-02 header, body and footer are separate flex rows that cannot overlap', () => {
  const html = render(ui.h(ui.AppModal, {
    title: 'Add Requirement', size: 'lg', onClose() {}, footer: ui.h('button', { type: 'button' }, 'Save'),
  }, ui.h('p', null, 'body')))
  const header = html.indexOf('it-modal__header')
  const body = html.indexOf('it-modal__body')
  const footer = html.indexOf('it-modal__footer')
  assert.ok(header > 0 && header < body && body < footer, 'header → body → footer order')
  assert.match(rule('.it-modal'), /display: flex; flex-direction: column;/)
  assert.match(rule('.it-modal'), /max-height: var\(--it-modal-height\)/)
  assert.match(rule('.it-modal__header'), /flex: none/)
  assert.match(rule('.it-modal__footer'), /flex: none/)
  const bodyRule = rule('.it-modal__body')
  assert.match(bodyRule, /flex: 1 1 auto/)
  assert.match(bodyRule, /min-height: 0/)
  assert.match(bodyRule, /overflow-y: auto/)
  // The legacy prototype rules no longer leak onto every Bootstrap/app modal.
  for (const sheet of ['styles/styles.css', 'styles/master-style.css']) {
    assert.doesNotMatch(read(sheet), /^\s*\.modal-content\s*\{/m, `${sheet}: .modal-content is scoped`)
  }
})

test('UI-GLOBAL-03 every report/CSV preview uses the shared DATA report modal', () => {
  const html = render(ui.h(ui.ReportExportModal, {
    preview: { title: 'Student Summary', filename: 'student-summary', rows: [{ 'Student Name': 'A', Hours: 10 }] },
    onClose() {},
  }))
  assert.match(html, /it-modal--data/)
  assert.match(html, /it-modal__body--fill/)
  assert.match(html, /Confirm &amp; Download CSV/)
  const reportPages = walk(join(SRC, 'pages')).filter((f) => readFileSync(f, 'utf8').includes('Export CSV'))
  assert.ok(reportPages.length >= 5, 'report pages found')
  for (const page of reportPages) {
    assert.match(readFileSync(page, 'utf8'), /import ReportExportModal from/, `${page} uses ReportExportModal`)
  }
})

test('UI-GLOBAL-04 large forms use the responsive two-column form grid', () => {
  assert.match(rule('.it-form-grid'), /grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/)
  assert.match(rule('.it-form-grid > .it-span-2'), /grid-column: 1 \/ -1/)
  const req = read('pages/shared/ManageRequirementsTemplates.jsx')
  const form = req.slice(req.indexOf('{/* Add/Edit Modal */}'), req.indexOf('{/* Submissions Modal */}'))
  assert.match(form, /size="lg"/)
  assert.match(form, /className="it-form-grid"/)
  assert.match(form, /it-target-picker__list/)
  assert.match(rule('.it-target-picker__list'), /max-height: 15rem; overflow-y: auto/)
})

test('UI-GLOBAL-05 the table wrapper, not the page or body, owns horizontal scrolling', () => {
  assert.match(rule('.it-modal__table-wrap'), /overflow-x: auto/)
  assert.match(rule('.it-modal__body'), /overflow-x: hidden/)
  assert.match(rule('.it-modal__body--fill'), /overflow: hidden/)
  assert.match(rule('.it-modal__body--fill > .it-modal__table-wrap'), /overflow: auto/)
  assert.match(rule('.it-modal__table-wrap thead th'), /position: sticky; top: 0/)
})

test('UI-GLOBAL-06 dialogs and filter bars cannot create page-level horizontal overflow', () => {
  assert.match(rule('.it-modal-viewport'), /padding: var\(--it-modal-gutter\); overflow: hidden/)
  assert.match(rule('.it-modal-layer'), /position: fixed; inset: 0/)
  // Scroll lock keeps the viewport width: body pinned, scrollbar track kept.
  assert.match(rule('html.it-scroll-locked body'), /position: fixed/)
  assert.match(rule('html.it-scroll-locked--bar'), /overflow-y: scroll/)
  assert.match(rule('.main-content .input-group'), /max-width: 100%/)
  assert.match(rule('.it-confirm-overlay'), /width: auto/)
})

test('UI-GLOBAL-07 forms collapse to one column on mobile', () => {
  assert.match(rule('.it-form-grid', '(max-width: 767.98px)'), /grid-template-columns: minmax\(0, 1fr\)/)
  assert.match(rule('.it-target-picker__list', '(max-width: 575.98px)'), /grid-template-columns: minmax\(0, 1fr\)/)
})

test('UI-GLOBAL-08 close control and footer stay accessible; dialog is labelled', () => {
  const html = render(ui.h(ui.AppModal, {
    title: 'Review Submission', size: 'sm', onClose() {}, footer: ui.h('button', { type: 'button' }, 'Approve'),
  }, 'x'))
  assert.match(html, /role="dialog"/)
  assert.match(html, /aria-modal="true"/)
  const labelledBy = html.match(/aria-labelledby="([^"]+)"/)[1]
  assert.ok(html.includes(`id="${labelledBy}"`), 'title labels the dialog')
  assert.match(html, /<button type="button" class="btn-close it-modal__close" aria-label="Close"/)
  // Busy dialogs keep the close control but disable it (no half-saved dismissals).
  const busy = render(ui.h(ui.AppModal, { title: 'Saving', busy: true, onClose() {} }, 'x'))
  assert.match(busy, /aria-label="Close" disabled=""/)
  // A form dialog renders as <form>, so footer submit buttons submit it.
  const form = render(ui.h(ui.AppModal, { title: 'F', onSubmit() {}, onClose() {} }, 'x'))
  assert.match(form, /<form class="it-modal it-modal--md"/)
})

test('UI-GLOBAL-09 an empty table dialog renders a proper empty state', () => {
  const html = render(ui.h(ui.ReportExportModal, { preview: { filename: 'x', rows: [] }, onClose() {} }))
  assert.doesNotMatch(html, /<table/)
  assert.match(html, /it-modal__empty/)
  assert.match(html, /No data rows available/)
  assert.match(html, /disabled=""[^>]*>.*Confirm &amp; Download CSV/s)
  assert.match(rule('.it-modal__empty'), /min-height: 160px/)
})

test('UI-GLOBAL-10 long content wraps inside the dialog instead of breaking it', () => {
  const long = 'Extremely long remarks '.repeat(12)
  const html = render(ui.h(ui.ReportExportModal, {
    preview: { filename: 'x', rows: [{ Name: 'Short', Remarks: long }] }, onClose() {},
  }))
  assert.match(html, /<td class="it-cell-long">Extremely long remarks/)
  assert.doesNotMatch(html, /<td class="it-cell-long">Short/)
  assert.match(rule('.it-cell-long'), /white-space: normal !important; overflow-wrap: anywhere/)
  assert.match(rule('.it-modal__title'), /overflow-wrap: anywhere/)
})

test('every overlay goes through the shared system (no hand-rolled Bootstrap modal shells)', () => {
  for (const file of walk(SRC)) {
    const src = readFileSync(file, 'utf8')
    assert.doesNotMatch(src, /className="modal[ "]|className="modal-dialog|(?<![\w-])modal-backdrop/, `${file} uses AppModal`)
  }
  for (const rel of ['components/modals/ConfirmModal.jsx', 'components/modals/ConfirmLogoutModal.jsx', 'components/modals/Lightbox.jsx', 'pages/coordinator/CoordSupervisorApprovals.jsx']) {
    const src = read(rel)
    assert.match(src, /useModalLayer\(/, `${rel} uses the shared layer`)
    assert.match(src, /document\.body/, `${rel} is portaled`)
  }
  assert.match(read('components/portfolio/FormPreviewModal.jsx'), /size="document"/)
})

test('nested dialogs share one scroll lock and Escape targets the topmost', () => {
  const classes = new Set()
  const scrolledTo = []
  const doc = {
    documentElement: { clientWidth: 1351, classList: { add: (...c) => c.forEach((x) => classes.add(x)), remove: (...c) => c.forEach((x) => classes.delete(x)) } },
    body: { style: { top: '' } },
    defaultView: { innerWidth: 1366, scrollY: 420, scrollTo: (o) => scrolledTo.push(o.top) },
  }
  const m = ui.manager
  m.lockPageScroll(doc)
  m.lockPageScroll(doc)
  m.pushModal('submissions')
  m.pushModal('review')
  assert.ok(classes.has(m.SCROLL_LOCK_CLASS))
  assert.ok(classes.has(m.SCROLL_LOCK_BAR_CLASS), 'a page with a scrollbar keeps its track (no width jump)')
  assert.equal(doc.body.style.top, '-420px', 'page is pinned at its scroll offset')
  assert.ok(m.isTopModal('review'))
  assert.ok(!m.isTopModal('submissions'))
  m.popModal('review')
  m.unlockPageScroll(doc)
  assert.ok(classes.has(m.SCROLL_LOCK_CLASS), 'page stays locked while a dialog is open')
  assert.ok(m.isTopModal('submissions'))
  m.popModal('submissions')
  m.unlockPageScroll(doc)
  assert.ok(!classes.has(m.SCROLL_LOCK_CLASS) && !classes.has(m.SCROLL_LOCK_BAR_CLASS))
  assert.equal(doc.body.style.top, '')
  assert.deepEqual(scrolledTo, [420], 'scroll position restored once, on the last close')
  assert.equal(m.unlockPageScroll(doc), 0, 'never goes negative')
  assert.deepEqual(scrolledTo, [420], 'an extra unlock does not scroll')
  assert.match(rule('html.it-scroll-locked body'), /position: fixed/)
})

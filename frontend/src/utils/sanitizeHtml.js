/**
 * Client-side allow-list sanitizer for portfolio rich text. Mirrors
 * App\Support\PortfolioHtml on the backend; used as defense in depth before any
 * stored HTML is rendered with dangerouslySetInnerHTML.
 */
const ALLOWED = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'H2', 'H3', 'H4', 'BLOCKQUOTE', 'DIV'])
const DROP = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'NOSCRIPT', 'TEMPLATE', 'SVG', 'MATH'])
const ALIGN = new Set(['left', 'center', 'right', 'justify'])

function clean(node) {
  Array.from(node.childNodes).forEach((child) => {
    if (child.nodeType === Node.TEXT_NODE) return
    if (child.nodeType !== Node.ELEMENT_NODE) {
      child.remove()
      return
    }
    const tag = child.tagName.toUpperCase()
    if (DROP.has(tag)) {
      child.remove()
      return
    }
    clean(child)
    if (!ALLOWED.has(tag)) {
      while (child.firstChild) node.insertBefore(child.firstChild, child)
      child.remove()
      return
    }
    const match = /text-align\s*:\s*([a-z]+)/i.exec(child.getAttribute('style') || '')
    const align = match && ALIGN.has(match[1].toLowerCase()) ? match[1].toLowerCase() : null
    Array.from(child.attributes).forEach((attr) => child.removeAttribute(attr.name))
    if (align) child.setAttribute('style', `text-align: ${align};`)
  })
}

export function sanitizeHtml(html) {
  if (!html) return ''
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  const root = doc.body.firstElementChild
  if (!root) return ''
  clean(root)
  return root.innerHTML
}

/** Wrap legacy plain-text content as paragraphs so it renders in the editor. */
export function plainTextToHtml(text) {
  const value = String(text || '').trim()
  if (!value) return ''
  if (/<\/?[a-z][\s\S]*>/i.test(value)) return value
  return value
    .split(/\r?\n\s*\r?\n|\r?\n/)
    .map((para) => para.trim())
    .filter(Boolean)
    .map((para) => `<p>${para.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>`)
    .join('')
}

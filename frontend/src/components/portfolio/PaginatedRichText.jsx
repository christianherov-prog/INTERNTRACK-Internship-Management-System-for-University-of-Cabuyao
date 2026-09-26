import { Fragment, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { sanitizeHtml } from '../../utils/sanitizeHtml'

const MM = 96 / 25.4
/** A4 content box: 297mm tall minus .a4-page padding (1.27cm top, 1.8cm bottom). */
const PAGE_CONTENT_HEIGHT = (297 - 12.7 - 18) * MM
/** 210mm wide minus 1.27cm left/right padding. */
const PAGE_CONTENT_WIDTH = (210 - 25.4) * MM
/** Room kept free for the page number and rounding differences. */
const SAFETY = 36
const SPLIT_CHARS = 650
const SPLIT_LIST_ITEMS = 6

/** Split long paragraphs at sentence boundaries (only between top-level text runs). */
function splitParagraph(el) {
  if ((el.textContent || '').length <= SPLIT_CHARS) return [el.outerHTML]
  const chunks = []
  let current = []
  let size = 0
  const flush = () => {
    if (current.length) chunks.push(current.join(''))
    current = []
    size = 0
  }
  el.childNodes.forEach((node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      const sentences = node.textContent.split(/(?<=[.!?])\s+/)
      sentences.forEach((sentence, i) => {
        const text = sentence + (i < sentences.length - 1 ? ' ' : '')
        current.push(text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))
        size += text.length
        if (size >= SPLIT_CHARS && i < sentences.length - 1) flush()
      })
    } else {
      current.push(node.outerHTML || node.textContent || '')
      size += (node.textContent || '').length
    }
  })
  flush()
  const style = el.getAttribute('style')
  const tag = el.tagName.toLowerCase()
  return chunks.map((inner, i) => `<${tag}${style ? ` style="${style}"` : ''}${i > 0 ? ' class="cbaa-cont"' : ''}>${inner}</${tag}>`)
}

function splitList(el) {
  const items = Array.from(el.children)
  if (items.length <= SPLIT_LIST_ITEMS) return [el.outerHTML]
  const tag = el.tagName.toLowerCase()
  const out = []
  for (let i = 0; i < items.length; i += SPLIT_LIST_ITEMS) {
    const start = tag === 'ol' ? ` start="${i + 1}"` : ''
    out.push(`<${tag}${start}>${items.slice(i, i + SPLIT_LIST_ITEMS).map((li) => li.outerHTML).join('')}</${tag}>`)
  }
  return out
}

/** Break sanitized HTML into top-level blocks that can be packed onto pages. */
function toBlocks(html) {
  const doc = new DOMParser().parseFromString(`<div>${sanitizeHtml(html)}</div>`, 'text/html')
  const root = doc.body.firstElementChild
  const blocks = []
  let inline = ''
  Array.from(root?.childNodes || []).forEach((node) => {
    if (node.nodeType === Node.TEXT_NODE || ['B', 'STRONG', 'I', 'EM', 'U', 'BR'].includes(node.tagName)) {
      inline += node.nodeType === Node.TEXT_NODE ? node.textContent : node.outerHTML
      return
    }
    if (inline.trim()) {
      const p = doc.createElement('p')
      p.innerHTML = inline
      blocks.push(...splitParagraph(p))
    }
    inline = ''
    if (['P', 'DIV', 'BLOCKQUOTE'].includes(node.tagName)) blocks.push(...splitParagraph(node))
    else if (['UL', 'OL'].includes(node.tagName)) blocks.push(...splitList(node))
    else blocks.push(node.outerHTML)
  })
  if (inline.trim()) {
    const p = doc.createElement('p')
    p.innerHTML = inline
    blocks.push(...splitParagraph(p))
  }
  return blocks.filter((b) => b.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim())
}

/**
 * Measures rich-text blocks at true A4 width and distributes them across as many
 * A4 sheets as needed. Headings are kept with the block that follows them.
 *
 * sections: [{ tocId, title, level: 'h2'|'h3'|'h4', html, emptyText }]
 */
export default function PaginatedRichText({ sections, header, pageClassName = '' }) {
  const measureRef = useRef(null)
  const [pages, setPages] = useState(null)

  const items = useMemo(() => {
    const list = []
    sections.forEach((s, si) => {
      if (s.title) list.push({ kind: 'heading', level: s.level || 'h3', text: s.title, tocId: s.tocId, key: `h-${si}` })
      const blocks = s.html ? toBlocks(s.html) : []
      if (blocks.length === 0 && s.emptyText === null) return
      if (blocks.length === 0) {
        list.push({ kind: 'empty', text: s.emptyText || 'This section has not been written yet.', key: `e-${si}`, tocId: s.title ? undefined : s.tocId })
      } else {
        blocks.forEach((html, bi) => list.push({ kind: 'html', html, key: `b-${si}-${bi}`, tocId: !s.title && bi === 0 ? s.tocId : undefined }))
      }
    })
    return list
  }, [sections])

  useLayoutEffect(() => {
    const box = measureRef.current
    if (!box) return
    const headerEl = box.querySelector('[data-measure="header"]')
    const headerHeight = headerEl ? headerEl.getBoundingClientRect().height : 0
    const capacity = PAGE_CONTENT_HEIGHT - headerHeight - SAFETY
    const heights = Array.from(box.querySelectorAll('[data-measure="item"]')).map((el) => el.getBoundingClientRect().height)

    const out = []
    let current = []
    let used = 0
    items.forEach((item, i) => {
      const h = heights[i] || 0
      const nextH = item.kind === 'heading' ? (heights[i + 1] || 0) : 0
      const need = h + Math.min(nextH, capacity / 3)
      if (current.length > 0 && used + need > capacity) {
        out.push(current)
        current = []
        used = 0
      }
      current.push(i)
      used += h
    })
    if (current.length) out.push(current)
    setPages(out.length ? out : [[]])
  }, [items])

  const renderItem = (item) => {
    if (item.kind === 'heading') {
      const Tag = item.level
      return <Tag className={`cbaa-heading cbaa-${item.level}`} data-toc-id={item.tocId}>{item.text}</Tag>
    }
    if (item.kind === 'empty') {
      return <p className="cbaa-empty" data-toc-id={item.tocId}>{item.text}</p>
    }
    return <div className="cbaa-rich" data-toc-id={item.tocId} dangerouslySetInnerHTML={{ __html: item.html }} />
  }

  return (
    <>
      <div ref={measureRef} className="cbaa-measure portfolio-document no-print" aria-hidden="true" style={{ width: `${PAGE_CONTENT_WIDTH}px` }}>
        <div data-measure="header" style={{ display: 'flow-root' }}>{header}</div>
        {items.map((item) => (
          <div key={item.key} data-measure="item" style={{ display: 'flow-root' }}>{renderItem(item)}</div>
        ))}
      </div>
      {(pages || [[...items.keys()]]).map((indices, pi) => (
        <div key={`rich-page-${pi}`} className={`a4-page page-break portfolio-document position-relative cbaa-page ${pageClassName}`}>
          {header}
          <div className="cbaa-page-body">
            {indices.map((i) => <Fragment key={items[i].key}>{renderItem(items[i])}</Fragment>)}
          </div>
          <div className="page-number"></div>
        </div>
      ))}
    </>
  )
}

import { useEffect, useRef } from 'react'
import { sanitizeHtml } from '../../utils/sanitizeHtml'

const TOOLS = [
  { cmd: 'formatBlock', arg: 'P', icon: 'fa-paragraph', title: 'Paragraph' },
  { cmd: 'formatBlock', arg: 'H2', label: 'H1', title: 'Heading' },
  { cmd: 'formatBlock', arg: 'H3', label: 'H2', title: 'Subheading' },
  { sep: true },
  { cmd: 'bold', icon: 'fa-bold', title: 'Bold (Ctrl+B)' },
  { cmd: 'italic', icon: 'fa-italic', title: 'Italic (Ctrl+I)' },
  { cmd: 'underline', icon: 'fa-underline', title: 'Underline (Ctrl+U)' },
  { sep: true },
  { cmd: 'insertUnorderedList', icon: 'fa-list-ul', title: 'Bullet list' },
  { cmd: 'insertOrderedList', icon: 'fa-list-ol', title: 'Numbered list' },
  { sep: true },
  { cmd: 'justifyLeft', icon: 'fa-align-left', title: 'Align left' },
  { cmd: 'justifyCenter', icon: 'fa-align-center', title: 'Align center' },
  { cmd: 'justifyRight', icon: 'fa-align-right', title: 'Align right' },
  { cmd: 'justifyFull', icon: 'fa-align-justify', title: 'Justify' },
]

/**
 * Minimal dependency-free rich-text editor (contentEditable) for portfolio
 * narrative sections. Emits sanitized HTML through onChange.
 */
export default function RichTextEditor({ id, value, onChange, placeholder, readOnly = false, maxLength = 60000, minHeight = 260 }) {
  const ref = useRef(null)
  const lastEmitted = useRef(null)

  // Sync external value into the editor only when it did not originate here,
  // so typing never resets the caret.
  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (value !== lastEmitted.current) {
      el.innerHTML = sanitizeHtml(value || '')
      lastEmitted.current = value
    }
  }, [value])

  useEffect(() => {
    try { document.execCommand('styleWithCSS', false, true) } catch { /* unsupported */ }
  }, [])

  const emit = () => {
    const el = ref.current
    if (!el) return
    let html = el.innerHTML
    if (html === '<br>' || html === '<p><br></p>') html = ''
    if (html.length > maxLength) return
    lastEmitted.current = html
    onChange?.(html)
  }

  const run = (tool) => {
    if (readOnly) return
    ref.current?.focus()
    document.execCommand(tool.cmd, false, tool.arg ? `<${tool.arg}>` : null)
    emit()
  }

  const onPaste = (e) => {
    e.preventDefault()
    const html = e.clipboardData.getData('text/html')
    const text = e.clipboardData.getData('text/plain')
    if (html) {
      document.execCommand('insertHTML', false, sanitizeHtml(html))
    } else {
      document.execCommand('insertText', false, text)
    }
    emit()
  }

  const length = (value || '').length

  return (
    <div className={`rte${readOnly ? ' rte-readonly' : ''}`}>
      {!readOnly && (
        <div className="rte-toolbar" role="toolbar" aria-label="Formatting">
          {TOOLS.map((tool, i) => tool.sep
            ? <span key={`sep-${i}`} className="rte-sep" aria-hidden="true" />
            : (
              <button
                key={`${tool.cmd}-${tool.arg || i}`}
                type="button"
                className="rte-btn"
                title={tool.title}
                aria-label={tool.title}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run(tool)}
              >
                {tool.icon ? <i className={`fa ${tool.icon}`}></i> : <span className="fw-bold small">{tool.label}</span>}
              </button>
            ))}
        </div>
      )}
      <div
        id={id}
        ref={ref}
        className="rte-content"
        contentEditable={!readOnly}
        suppressContentEditableWarning
        role="textbox"
        aria-multiline="true"
        data-placeholder={placeholder}
        style={{ minHeight }}
        onInput={emit}
        onBlur={emit}
        onPaste={onPaste}
      />
      {!readOnly && (
        <div className={`rte-count${length > maxLength * 0.9 ? ' warn' : ''}`}>{length.toLocaleString()} / {maxLength.toLocaleString()}</div>
      )}
    </div>
  )
}

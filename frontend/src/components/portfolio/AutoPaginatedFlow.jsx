import React from 'react'
import { AuthenticatedFileImage } from '../AuthenticatedFile'

/**
 * Total character-equivalent vertical budget per A4 page.
 * Standard A4 height is 297mm (~1123px). Subtracting 20mm top/bottom padding (~150px)
 * and header/footer space (~120px) leaves ~850px of content space.
 * At 11pt font (line height ~22px, ~100 chars per line), an A4 sheet holds ~4500 characters.
 * We set capacity at 4200 units to provide a safe buffer while ensuring pages are fully utilized.
 */
const PAGE_TEXT_CAPACITY = 2500

/**
 * Splits a long text string at a clean word boundary (space or punctuation)
 * before the specified character limit.
 */
function splitTextAtBoundary(text, limit) {
  if (text.length <= limit) return [text, '']
  
  // Look for the last punctuation or space before limit
  const slice = text.substring(0, limit)
  const lastSpace = Math.max(
    slice.lastIndexOf('. '),
    slice.lastIndexOf('! '),
    slice.lastIndexOf('? '),
    slice.lastIndexOf('; '),
    slice.lastIndexOf(', '),
    slice.lastIndexOf(' ')
  )
  
  if (lastSpace > limit * 0.4) {
    return [text.substring(0, lastSpace + 1).trim(), text.substring(lastSpace + 1).trim()]
  }
  // Fallback if no clean space found
  return [text.substring(0, limit), text.substring(limit)]
}

/**
 * PaginatedTextSection
 * Dynamically reflows headings, paragraphs, and long text essays across sequential A4 sheets.
 * Automatically splits overflowing paragraphs, prevents orphan headings, and resets
 * coordinate flow to top margin 0 on every generated sheet.
 */
export function PaginatedTextSection({
  sections = [],
  companyLogoPath = null,
  nextPg,
  pageHeaderComponent: PageHeader,
  pageTitle = null
}) {
  const pages = []
  let currentPage = { items: [], capacity: PAGE_TEXT_CAPACITY }

  const startNewPage = () => {
    if (currentPage.items.length > 0) {
      pages.push(currentPage)
    }
    currentPage = { items: [], capacity: PAGE_TEXT_CAPACITY }
  }

  // If a main pageTitle is provided for the top of sheet 1
  if (pageTitle) {
    if (typeof pageTitle === 'string') {
      currentPage.items.push({ type: 'main-title', text: pageTitle })
      currentPage.capacity -= 150
    } else if (typeof pageTitle === 'object') {
      currentPage.items.push({ type: 'main-title-obj', ...pageTitle })
      currentPage.capacity -= 180
    }
  }

  sections.forEach((section, secIdx) => {
    // 1. Process Section Heading (if not inline)
    if (section.title && !section.inlineTitle) {
      // Prevent orphan headings: if remaining budget is too small for heading + 3-4 lines of text,
      // close page and move heading to the top of the next page.
      if (currentPage.capacity < 250 && currentPage.items.length > 0) {
        startNewPage()
      }
      currentPage.items.push({
        type: 'heading',
        tag: section.heading || 'h4',
        text: section.title,
        style: section.style || {}
      })
      currentPage.capacity -= 100
    }

    // 2. Process Section Body / Paragraphs
    const rawBody = (section.body || '').trim()
    const paragraphs = rawBody ? rawBody.split(/\r?\n\r?\n|\r?\n/) : [section.placeholder || '___________________']

    paragraphs.forEach((para, pIdx) => {
      let remainingText = para.trim()
      let isFirstParaOfSection = (pIdx === 0)

      while (remainingText.length > 0) {
        const inlineLabel = (isFirstParaOfSection && section.inlineTitle) ? section.title : null
        const inlineCost = inlineLabel ? inlineLabel.length + 20 : 0
        const itemCost = remainingText.length + inlineCost + 20

        if (itemCost <= currentPage.capacity) {
          // Fits completely on current page
          currentPage.items.push({
            type: 'paragraph',
            text: remainingText,
            inlineLabel: inlineLabel,
            indent: section.indent,
            style: section.paraStyle || {}
          })
          currentPage.capacity -= itemCost
          remainingText = ''
        } else {
          // Does not fit completely. Can we fit a meaningful part on current page?
          if (currentPage.capacity > 200) {
            const availableChars = currentPage.capacity - inlineCost - 20
            const [chunk, rest] = splitTextAtBoundary(remainingText, availableChars)
            if (chunk.length > 0) {
              currentPage.items.push({
                type: 'paragraph',
                text: chunk,
                inlineLabel: inlineLabel,
                indent: section.indent,
                style: section.paraStyle || {}
              })
              remainingText = rest
              isFirstParaOfSection = false // label already used on first chunk
            }
          }
          // Close current sheet and start clean on next sheet
          startNewPage()
        }
      }
    })
  })

  if (currentPage.items.length > 0) {
    pages.push(currentPage)
  }
  if (pages.length === 0) {
    pages.push({ items: [{ type: 'paragraph', text: 'No content available.' }], capacity: PAGE_TEXT_CAPACITY })
  }

  return (
    <>
      {pages.map((page, pageIdx) => (
        <div key={`paginated-text-${pageIdx}`} className="a4-page page-break portfolio-document position-relative">
          {PageHeader && <PageHeader companyLogoPath={companyLogoPath} />}
          <div className="paginated-sheet-content" style={{ marginTop: '20px' }}>
            {page.items.map((item, iIdx) => {
              if (item.type === 'main-title') {
                return (
                  <h3 key={iIdx} style={{ fontWeight: 'bold', textAlign: 'left', marginBottom: '20px' }}>
                    {item.text}
                  </h3>
                )
              }
              if (item.type === 'main-title-obj') {
                return (
                  <div key={iIdx} style={{ marginBottom: '20px' }}>
                    {item.superTitle && <h6 className="text-muted mb-1">{item.superTitle}</h6>}
                    <h4 style={{ fontWeight: 'bold', textAlign: 'left', margin: 0 }}>{item.title}</h4>
                  </div>
                )
              }
              if (item.type === 'heading') {
                const Tag = item.tag
                return (
                  <Tag key={iIdx} style={{ fontWeight: 'bold', textAlign: 'left', marginTop: iIdx === 0 ? 0 : '24px', marginBottom: '10px', ...item.style }}>
                    {item.text}
                  </Tag>
                )
              }
              if (item.type === 'paragraph') {
                return (
                  <p key={iIdx} style={{ whiteSpace: 'pre-wrap', textAlign: 'justify', textIndent: item.indent ? '0.5in' : '0', marginBottom: '14px', ...item.style }}>
                    {item.inlineLabel && <strong style={{ marginRight: '6px' }}>{item.inlineLabel}:</strong>}
                    {item.text}
                  </p>
                )
              }
              return null
            })}
          </div>
          <div className="page-number">{nextPg()}</div>
        </div>
      ))}
    </>
  )
}

/**
 * PaginatedImageCollection
 * Distributes image cards, scanned forms, and OJT weekly photos across A4 sheets.
 * Enforces strict vertical budgeting (max 1-2 photos per page) to prevent bottom clipping
 * or overlapping. Pulls back content when items are deleted and pushes forward when items are added.
 */
export function PaginatedImageCollection({
  list = [],
  title = '',
  companyLogoPath = null,
  nextPg,
  pageHeaderComponent: PageHeader,
  emptyMessage = null,
  requiresWeek = false,
  renderCustomItem = null,
  tocId = null
}) {
  if (!list || list.length === 0) {
    return (
      <div className="a4-page page-break portfolio-document position-relative" data-toc-id={tocId}>
        {PageHeader && <PageHeader companyLogoPath={companyLogoPath} />}
        <h5 style={{ fontWeight: 'bold', marginTop: '16px', fontSize: '13pt', lineHeight: 1.3, textAlign: list.length === 0 && emptyMessage ? 'center' : 'left' }}>{title}</h5>
        <div style={{ textAlign: 'center', padding: '25px 20px', border: '2px dashed #bbb', background: '#f8f9fa', borderRadius: '12px', width: '85%', margin: '30px auto 0', pageBreakInside: 'avoid', breakInside: 'avoid' }}>
          <i className="fa fa-file-circle-exclamation fa-3x text-warning mb-2"></i>
          <h6 className="fw-bold text-dark" style={{ fontSize: '11pt', lineHeight: 1.4, margin: '8px auto', maxWidth: '90%' }}>{title}</h6>
          <div className="badge bg-warning text-dark mt-1 mb-2 px-3 py-1" style={{ fontSize: '9.5pt', fontWeight: 600 }}>Not Uploaded Yet</div>
          <p className="small text-muted mb-0" style={{ maxWidth: '450px', margin: '6px auto 0', fontSize: '9pt', lineHeight: 1.4 }}>
            {emptyMessage || '[ Draft Preview Mode: This section is currently empty. Upload required items in the Portfolio Builder when ready. ]'}
          </p>
        </div>
        <div className="page-number">{nextPg()}</div>
      </div>
    )
  }

  // Budgeting: Total capacity per A4 page = 100 units.
  // Standard photo card (image + 1-line caption) = 50 units (2 per sheet).
  // Document forms, certificates, evaluations, letters, and tall photos = 100 units (1 per sheet).
  const pages = []
  let currentPage = []
  let currentCap = 100

  list.forEach(photo => {
    const isDocType = !['ojt_photo'].includes(photo.type)
    const isPdf = photo.file_path && photo.file_path.endsWith('.pdf')
    const isTall = isDocType || photo.type === 'exam_documentation' || photo.type === 'training_documentation' || (photo.description && photo.description.length > 100) || isPdf
    const cost = isTall ? 100 : 50

    if (cost > currentCap && currentPage.length > 0) {
      pages.push(currentPage)
      currentPage = []
      currentCap = 100
    }
    currentPage.push(photo)
    currentCap -= cost
  })

  if (currentPage.length > 0) {
    pages.push(currentPage)
  }

  return (
    <>
      {pages.map((pagePhotos, pageIdx) => (
        <div key={`${title}-sheet-${pageIdx}`} className="a4-page page-break portfolio-document position-relative" data-toc-id={pageIdx === 0 ? tocId : undefined}>
          {PageHeader && <PageHeader companyLogoPath={companyLogoPath} />}
          <h5 style={{ fontWeight: 'bold', marginTop: '16px', fontSize: '13pt', lineHeight: 1.3, textAlign: 'center' }}>
            {title}{pageIdx > 0 ? ' (cont.)' : ''}
          </h5>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '24px', marginTop: '24px', alignItems: 'center' }}>
            {pagePhotos.map(photo => {
              if (renderCustomItem) {
                return renderCustomItem(photo)
              }
              const isRawFilename = photo.label && (
                /\.(png|jpe?g|webp|gif|bmp|pdf|docx?|txt|zip)$/i.test(photo.label.trim()) ||
                (photo.file_path && (photo.file_path.endsWith('/' + photo.label.trim()) || photo.file_path.endsWith('\\' + photo.label.trim())))
              );
              const labelText = isRawFilename ? '' : (photo.label || '').trim();
              const hasCaption = (requiresWeek && photo.week_number) || labelText;
              const isPdfDoc = photo.file_path && photo.file_path.endsWith('.pdf');
              return (
                <div key={photo.id} style={{ textAlign: 'center', width: '100%', marginBottom: pagePhotos.length > 1 ? '16px' : '0' }}>
                  {isPdfDoc ? (
                    <div style={{ padding: '30px 20px', border: '2px dashed #999', background: '#f9f9f9', width: '90%', margin: '0 auto', borderRadius: '8px' }}>
                      <i className="fa fa-file-pdf fa-3x text-danger mb-2"></i>
                      <p style={{ margin: 0, fontWeight: 'bold', fontSize: '12pt' }}>{labelText || 'PDF Document'}</p>
                      <p className="small text-muted m-0" style={{ fontSize: '9.5pt', marginTop: '6px' }}>[ PDF Document attached: Please insert physical PDF sheet here before final binding ]</p>
                    </div>
                  ) : (
                    <AuthenticatedFileImage
                      path={photo.file_path}
                      alt={labelText || 'Portfolio Photo'}
                      style={{
                        maxWidth: '98%',
                        maxHeight: (photo.type === 'exam_documentation' || photo.type === 'training_documentation' || photo.description) ? '480px' : (pagePhotos.length === 1 ? '560px' : '260px'),
                        objectFit: 'contain',
                        margin: '0 auto',
                        display: 'block'
                      }}
                    />
                  )}
                  {hasCaption ? (
                    <p style={{ fontWeight: 'bold', marginTop: '12px', textIndent: '0', fontSize: '11pt', marginBottom: '4px', color: '#111', textAlign: 'center' }}>
                      {requiresWeek && photo.week_number ? `Week ${photo.week_number}${labelText ? ' - ' + labelText : ''}` : labelText}
                    </p>
                  ) : null}
                  {photo.description && (
                    <p style={{ textIndent: '0.5in', fontSize: '10pt', marginTop: '4px', textAlign: 'justify', maxWidth: '90%', margin: '6px auto 0', lineHeight: 1.5 }}>
                      {photo.description}
                    </p>
                  )}
                </div>
              )
            })}
          </div>
          <div className="page-number">{nextPg()}</div>
        </div>
      ))}
    </>
  )
}

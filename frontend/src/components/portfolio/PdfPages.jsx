import { useEffect, useState } from 'react'
import { fetchBlobUrl } from '../AuthenticatedFile'

const MAX_PAGES = 40
const RENDER_SCALE = 1.6

let pdfjsPromise = null
function loadPdfjs() {
  if (!pdfjsPromise) {
    pdfjsPromise = Promise.all([
      import('pdfjs-dist'),
      import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]).then(([pdfjs, worker]) => {
      pdfjs.GlobalWorkerOptions.workerSrc = worker.default
      return pdfjs
    })
  }
  return pdfjsPromise
}

const pageCache = new Map()

async function renderPdf(path) {
  if (pageCache.has(path)) return pageCache.get(path)
  const job = (async () => {
    const [pdfjs, url] = await Promise.all([loadPdfjs(), fetchBlobUrl(path)])
    const data = await (await fetch(url)).arrayBuffer()
    const pdf = await pdfjs.getDocument({ data, isEvalSupported: false }).promise
    const count = Math.min(pdf.numPages, MAX_PAGES)
    const images = []
    for (let n = 1; n <= count; n += 1) {
      const page = await pdf.getPage(n)
      const viewport = page.getViewport({ scale: RENDER_SCALE })
      const canvas = document.createElement('canvas')
      canvas.width = Math.ceil(viewport.width)
      canvas.height = Math.ceil(viewport.height)
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise
      images.push(canvas.toDataURL('image/jpeg', 0.88))
      page.cleanup()
    }
    const truncated = pdf.numPages > MAX_PAGES ? pdf.numPages : 0
    pdf.destroy()
    return { images, truncated }
  })()
  pageCache.set(path, job)
  job.catch(() => pageCache.delete(path))
  return job
}

/**
 * Renders every page of a private PDF (fetched with the user's token) as
 * printable A4 sheets. Falls back to a clear placeholder if rendering fails.
 */
export default function PdfPages({ path, title, caption, renderPage }) {
  const [state, setState] = useState({ loading: true, images: [], error: false, truncated: 0 })

  useEffect(() => {
    let active = true
    setState({ loading: true, images: [], error: false, truncated: 0 })
    renderPdf(path)
      .then(({ images, truncated }) => { if (active) setState({ loading: false, images, error: false, truncated }) })
      .catch(() => { if (active) setState({ loading: false, images: [], error: true, truncated: 0 }) })
    return () => { active = false }
  }, [path])

  if (state.loading || state.error) {
    return renderPage(0, (
      <div className="cbaa-doc-placeholder">
        <i className={`fa ${state.error ? 'fa-file-circle-exclamation text-danger' : 'fa-spinner fa-spin text-muted'} fa-2x mb-2`}></i>
        <div className="fw-bold">{caption || title}</div>
        <div className="small text-muted">
          {state.error ? 'This PDF could not be rendered in the preview. Open it from the Builder to view it.' : 'Rendering PDF pages…'}
        </div>
      </div>
    ))
  }

  return (
    <>
      {state.images.map((src, i) => renderPage(i, (
        <img src={src} alt={`${caption || title} — page ${i + 1}`} className="cbaa-pdf-page-img" />
      )))}
      {state.truncated > 0 && renderPage(state.images.length, (
        <div className="cbaa-doc-placeholder">
          <div className="fw-bold">{caption || title}</div>
          <div className="small text-muted">Only the first {MAX_PAGES} of {state.truncated} pages are shown in the preview.</div>
        </div>
      ))}
    </>
  )
}

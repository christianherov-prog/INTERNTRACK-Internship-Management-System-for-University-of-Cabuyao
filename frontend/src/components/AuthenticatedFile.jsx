import { useEffect, useState } from 'react'
import api from '../services/api'

const urlCache = new Map()
const pendingRequests = new Map()

async function fetchBlobUrl(path) {
  if (!path) return ''
  if (urlCache.has(path)) {
    return urlCache.get(path)
  }
  if (pendingRequests.has(path)) {
    return pendingRequests.get(path)
  }
  const promise = api.get('/files/download', {
    params: { path },
    responseType: 'blob',
  }).then(async (res) => {
    const type = String(res.headers['content-type'] || '')
    if (type.includes('application/json')) {
      pendingRequests.delete(path)
      throw new Error('File download failed.')
    }
    const url = URL.createObjectURL(res.data)
    urlCache.set(path, url)
    pendingRequests.delete(path)
    return url
  }).catch((err) => {
    pendingRequests.delete(path)
    throw err
  })
  pendingRequests.set(path, promise)
  return promise
}

/** Opens a private storage file in a new tab using the Sanctum token. */
export function AuthenticatedFileLink({ path, children, className, style, title }) {
  const [busy, setBusy] = useState(false)

  const open = async (e) => {
    e.preventDefault()
    if (!path || busy) return
    setBusy(true)
    try {
      const url = await fetchBlobUrl(path)
      const opened = window.open(url, '_blank', 'noopener,noreferrer')
      if (!opened) {
        const link = document.createElement('a')
        link.href = url
        link.target = '_blank'
        link.rel = 'noopener noreferrer'
        document.body.appendChild(link)
        link.click()
        link.remove()
      }
    } catch {
      alert('Unable to open this file. You may not have access, or it was removed.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <a href="#" onClick={open} className={className} style={style} title={title} aria-busy={busy}>
      {children}
    </a>
  )
}

/** Loads a private image via authenticated download (Bearer token). */
export function AuthenticatedFileImage({ path, alt = '', className, style, fallback = null }) {
  const [src, setSrc] = useState(() => (path && urlCache.has(path) ? urlCache.get(path) : ''))

  useEffect(() => {
    let active = true
    if (!path) {
      setSrc('')
      return undefined
    }
    if (urlCache.has(path)) {
      setSrc(urlCache.get(path))
      return undefined
    }
    fetchBlobUrl(path)
      .then((url) => {
        if (!active) return
        setSrc(url)
      })
      .catch(() => {
        if (active) setSrc('')
      })
    return () => {
      active = false
    }
  }, [path])

  if (!src) return fallback || null
  return <img src={src} alt={alt} className={className} style={style} />
}

/** Image or PDF preview for private storage files (faculty review). */
export function AuthenticatedFilePreview({ path, mime, name, height = 480 }) {
  const [src, setSrc] = useState(() => (path && urlCache.has(path) ? urlCache.get(path) : ''))
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let active = true
    if (!path) {
      setSrc('')
      return undefined
    }
    if (urlCache.has(path)) {
      setSrc(urlCache.get(path))
      return undefined
    }
    fetchBlobUrl(path)
      .then((url) => {
        if (!active) return
        setSrc(url)
        setFailed(false)
      })
      .catch(() => {
        if (active) {
          setSrc('')
          setFailed(true)
        }
      })
    return () => {
      active = false
    }
  }, [path])

  const label = name || (path ? path.split('/').pop() : 'file')
  const isImage = (mime && String(mime).startsWith('image/')) || /\.(png|jpe?g|gif|webp)$/i.test(label)
  const isPdf = (mime && String(mime).includes('pdf')) || /\.pdf$/i.test(label)

  if (!path) return <p className="text-muted mb-0">No file.</p>
  if (failed) {
    return (
      <p className="text-danger mb-0">Unable to load this file. You may not have access, or it was removed.</p>
    )
  }
  if (!src) {
    return <div className="text-center text-muted py-4"><i className="fa fa-spinner fa-spin me-2"></i>Loading {label}…</div>
  }
  if (isImage) {
    return <img src={src} alt={label} style={{ maxWidth: '100%', maxHeight: height, objectFit: 'contain' }} />
  }
  if (isPdf) {
    return (
      <iframe
        title={label}
        src={src}
        style={{ width: '100%', height, border: '1px solid #dee2e6', borderRadius: 4, background: '#fff' }}
      />
    )
  }
  return (
    <AuthenticatedFileLink path={path} className="btn btn-sm btn-outline-primary">
      <i className="fa fa-download me-1"></i>Open {label}
    </AuthenticatedFileLink>
  )
}

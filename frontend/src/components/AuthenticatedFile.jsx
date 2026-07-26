import { useEffect, useState } from 'react'
import api from '../services/api'

async function fetchBlobUrl(path) {
  const res = await api.get('/files/download', {
    params: { path },
    responseType: 'blob',
  })
  return URL.createObjectURL(res.data)
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
      window.open(url, '_blank', 'noopener,noreferrer')
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
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
export function AuthenticatedFileImage({ path, alt = '', className, style }) {
  const [src, setSrc] = useState('')

  useEffect(() => {
    let active = true
    let objectUrl = ''
    if (!path) {
      setSrc('')
      return undefined
    }
    fetchBlobUrl(path)
      .then((url) => {
        if (!active) {
          URL.revokeObjectURL(url)
          return
        }
        objectUrl = url
        setSrc(url)
      })
      .catch(() => {
        if (active) setSrc('')
      })
    return () => {
      active = false
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [path])

  if (!src) return null
  return <img src={src} alt={alt} className={className} style={style} />
}

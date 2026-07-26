import { useState, useRef, useEffect, useCallback, useMemo } from 'react'
import ModalPortal from './modals/ModalPortal'

/**
 * Lightweight, zero-dependency avatar crop modal.
 * The user drags to pan and uses a slider (or wheel) to zoom. The visible
 * circular viewport defines the crop; on Save the selection is rendered to a
 * square canvas and returned as a base64 data URL.
 *
 * Props:
 *   imageSrc  – source data URL / object URL of the picked image
 *   onCancel  – called when the user cancels
 *   onSave    – called with the cropped image data URL
 */
const OUTPUT = 320     // exported image resolution (px)

function computeViewportSize() {
  if (typeof window === 'undefined') return 280
  // Leave room for modal padding + margins on narrow phones
  return Math.min(280, Math.max(180, window.innerWidth - 72))
}

function ImageCropModal({ imageSrc, onCancel, onSave }) {
  const [zoom, setZoom] = useState(1)
  const [offset, setOffset] = useState({ x: 0, y: 0 })
  const [natural, setNatural] = useState(null) // { w, h }
  const [viewport, setViewport] = useState(computeViewportSize)
  const imgRef = useRef(null)
  const dragState = useRef(null)

  useEffect(() => {
    const onResize = () => setViewport(computeViewportSize())
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

  // Load natural dimensions so we can compute a cover-fit base scale.
  useEffect(() => {
    const img = new Image()
    img.onload = () => {
      setNatural({ w: img.naturalWidth, h: img.naturalHeight })
      setOffset({ x: 0, y: 0 })
      setZoom(1)
    }
    img.src = imageSrc
    imgRef.current = img
  }, [imageSrc])

  // Base scale makes the smaller image dimension exactly cover the viewport.
  const baseScale = useMemo(
    () => (natural ? viewport / Math.min(natural.w, natural.h) : 1),
    [natural, viewport]
  )
  const scale = baseScale * zoom

  // Keep the image covering the viewport (no empty gaps) by clamping offset.
  const clampOffset = useCallback((x, y, s) => {
    if (!natural) return { x, y }
    const halfW = (natural.w * s) / 2
    const halfH = (natural.h * s) / 2
    const maxX = Math.max(0, halfW - viewport / 2)
    const maxY = Math.max(0, halfH - viewport / 2)
    return {
      x: Math.min(maxX, Math.max(-maxX, x)),
      y: Math.min(maxY, Math.max(-maxY, y)),
    }
  }, [natural, viewport])

  const onPointerDown = (e) => {
    e.preventDefault()
    dragState.current = { startX: e.clientX, startY: e.clientY, origin: offset }
    e.currentTarget.setPointerCapture?.(e.pointerId)
  }

  const onPointerMove = (e) => {
    if (!dragState.current) return
    const dx = e.clientX - dragState.current.startX
    const dy = e.clientY - dragState.current.startY
    setOffset(clampOffset(dragState.current.origin.x + dx, dragState.current.origin.y + dy, scale))
  }

  const onPointerUp = () => { dragState.current = null }

  const handleZoom = (value) => {
    const newZoom = Number(value)
    setOffset((prev) => clampOffset(prev.x, prev.y, baseScale * newZoom))
    setZoom(newZoom)
  }

  const handleSave = () => {
    if (!natural) return
    const canvas = document.createElement('canvas')
    canvas.width = OUTPUT
    canvas.height = OUTPUT
    const ctx = canvas.getContext('2d')
    const k = OUTPUT / viewport

    ctx.save()
    // Circular clip so exported avatar is round.
    ctx.beginPath()
    ctx.arc(OUTPUT / 2, OUTPUT / 2, OUTPUT / 2, 0, Math.PI * 2)
    ctx.closePath()
    ctx.clip()

    const drawW = natural.w * scale
    const drawH = natural.h * scale
    const topLeftX = (viewport / 2 + offset.x - drawW / 2) * k
    const topLeftY = (viewport / 2 + offset.y - drawH / 2) * k
    ctx.drawImage(imgRef.current, topLeftX, topLeftY, drawW * k, drawH * k)
    ctx.restore()

    onSave(canvas.toDataURL('image/png'))
  }

  const imgStyle = natural ? {
    width: natural.w * scale,
    height: natural.h * scale,
    transform: `translate(-50%, -50%) translate(${offset.x}px, ${offset.y}px)`,
  } : { opacity: 0 }

  return (
    <ModalPortal>
      <div className="crop-modal-overlay" onMouseDown={(e) => { if (e.target === e.currentTarget) onCancel() }}>
        <div className="crop-modal" role="dialog" aria-modal="true" aria-label="Crop profile photo">
          <div className="crop-modal-header">
            <h6><i className="fa fa-crop-simple"></i> Crop profile photo</h6>
            <button type="button" className="crop-modal-close" onClick={onCancel} aria-label="Close">
              <i className="fa fa-xmark"></i>
            </button>
          </div>

          <div
            className="crop-viewport"
            style={{ width: viewport, height: viewport }}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerLeave={onPointerUp}
          >
            <img src={imageSrc} alt="" className="crop-image" style={imgStyle} draggable={false} />
            <div className="crop-circle-mask"></div>
          </div>

          <div className="crop-zoom-row">
            <i className="fa fa-magnifying-glass-minus"></i>
            <input
              type="range"
              min="1"
              max="3"
              step="0.01"
              value={zoom}
              onChange={(e) => handleZoom(e.target.value)}
              className="crop-zoom-slider"
              aria-label="Zoom"
            />
            <i className="fa fa-magnifying-glass-plus"></i>
          </div>

          <p className="crop-hint">Drag to reposition · slide to zoom</p>

          <div className="crop-modal-actions">
            <button type="button" className="btn-green" onClick={handleSave}>Save</button>
            <button type="button" className="btn-outline-green" onClick={onCancel}>Cancel</button>
          </div>
        </div>
      </div>
    </ModalPortal>
  )
}

export default ImageCropModal

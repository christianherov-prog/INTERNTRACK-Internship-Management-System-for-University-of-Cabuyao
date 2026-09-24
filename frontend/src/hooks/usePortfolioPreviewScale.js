import { useEffect } from 'react'

/** A4 width (210mm) in CSS pixels at 96 dpi. */
export const A4_WIDTH_PX = 794
/** Horizontal breathing room kept around the page on small screens. */
const GUTTER_PX = 16

export function previewScaleFor(viewportWidth) {
  const width = Number(viewportWidth) || A4_WIDTH_PX
  return Math.max(0.3, Math.min(1, (width - GUTTER_PX) / A4_WIDTH_PX))
}

/**
 * Screen-only scaling for the fixed-size (A4) Portfolio preview.
 *
 * Publishes --portfolio-preview-scale on <html>; portfolio-print.css applies it
 * as `zoom` to each .a4-page inside @media screen only, so phones and tablets
 * see the whole sheet without horizontal cropping, while printing and PDF
 * output keep the official 210 × 297 mm dimensions.
 */
export default function usePortfolioPreviewScale() {
  useEffect(() => {
    const root = document.documentElement
    const apply = () => {
      root.style.setProperty('--portfolio-preview-scale', String(previewScaleFor(window.innerWidth)))
    }
    apply()
    window.addEventListener('resize', apply)
    window.addEventListener('orientationchange', apply)
    return () => {
      window.removeEventListener('resize', apply)
      window.removeEventListener('orientationchange', apply)
      root.style.removeProperty('--portfolio-preview-scale')
    }
  }, [])
}

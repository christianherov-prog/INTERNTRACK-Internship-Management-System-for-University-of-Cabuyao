import React, { useState, useCallback } from 'react'
import Cropper from 'react-easy-crop'
import getCroppedImg from '../../utils/cropImage'
import AppModal from '../modals/AppModal'

export default function ImageCropperModal({ imageFile, onClose, onCropComplete, isLogo = false }) {
  const [crop, setCrop] = useState({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [aspectRatio, setAspectRatio] = useState(isLogo ? 1 : null) // null = free crop
  const [croppedAreaPixels, setCroppedAreaPixels] = useState(null)
  const [processing, setProcessing] = useState(false)

  // Create an object URL for the selected image file
  const [imageSrc, setImageSrc] = useState(null)

  React.useEffect(() => {
    if (imageFile) {
      const url = URL.createObjectURL(imageFile)
      setImageSrc(url)
      return () => URL.revokeObjectURL(url)
    }
  }, [imageFile])

  const onCropCompleteHandler = useCallback((croppedArea, croppedAreaPixels) => {
    setCroppedAreaPixels(croppedAreaPixels)
  }, [])

  const handleCropAndUpload = async () => {
    try {
      setProcessing(true)
      const croppedBlob = await getCroppedImg(imageSrc, croppedAreaPixels)
      
      // We return the croppedBlob to the parent component
      // We also recreate a File object so it has a name and type similar to original
      const croppedFile = new File([croppedBlob], imageFile.name, {
        type: 'image/jpeg',
        lastModified: Date.now(),
      })

      onCropComplete(croppedFile)
    } catch (e) {
      console.error(e)
      alert("Failed to crop image.")
    } finally {
      setProcessing(false)
    }
  }

  if (!imageSrc) return null

  return (
    <AppModal
      onClose={onClose}
      size="lg"
      title="Resize & Position Image"
      icon="fa-crop-simple"
      busy={processing}
      bodyClassName="p-0"
      footerAlign="between"
      footer={(
        <>
          <div className="d-flex align-items-center flex-grow-1" style={{ minWidth: '10rem', maxWidth: '16rem' }}>
            <i className="fa fa-search-minus text-muted me-2" aria-hidden="true"></i>
            <input
              type="range"
              className="form-range flex-grow-1"
              aria-label="Zoom"
              min={1}
              max={3}
              step={0.1}
              value={zoom}
              onChange={(e) => setZoom(Number(e.target.value))}
            />
            <i className="fa fa-search-plus text-muted ms-2" aria-hidden="true"></i>
          </div>

          {!isLogo && (
            <div className="btn-group btn-group-sm flex-wrap" role="group" aria-label="Aspect ratio">
              <button type="button" className={`btn ${aspectRatio === null ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(null)}>Free</button>
              <button type="button" className={`btn ${aspectRatio === 1 ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(1)}>1:1</button>
              <button type="button" className={`btn ${aspectRatio === 4/3 ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(4/3)}>Landscape</button>
              <button type="button" className={`btn ${aspectRatio === 3/4 ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(3/4)}>Portrait</button>
            </div>
          )}

          <div className="d-flex gap-2">
            <button type="button" className="btn btn-secondary" onClick={onClose} disabled={processing}>
              Cancel
            </button>
            <button type="button" className="btn btn-primary" onClick={handleCropAndUpload} disabled={processing}>
              {processing ? (
                <><i className="fa fa-spinner fa-spin me-2"></i>Processing...</>
              ) : (
                <><i className="fa fa-check me-2"></i>Crop & Upload</>
              )}
            </button>
          </div>
        </>
      )}
    >
      <div className="position-relative" style={{ height: 'min(450px, 55dvh)', background: '#333' }}>
        <Cropper
          image={imageSrc}
          crop={crop}
          zoom={zoom}
          aspect={aspectRatio || undefined}
          onCropChange={setCrop}
          onCropComplete={onCropCompleteHandler}
          onZoomChange={setZoom}
        />
      </div>
    </AppModal>
  )
}

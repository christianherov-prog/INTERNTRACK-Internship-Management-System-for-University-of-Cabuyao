import React, { useState, useCallback } from 'react'
import Cropper from 'react-easy-crop'
import getCroppedImg from '../../utils/cropImage'

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
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.6)', zIndex: 1050 }}>
      <div className="modal-dialog modal-lg modal-dialog-centered">
        <div className="modal-content border-0 shadow-lg">
          <div className="modal-header bg-primary text-white border-bottom-0">
            <h5 className="modal-title">
              <i className="fa fa-crop-simple me-2"></i>Resize & Position Image
            </h5>
            <button className="btn-close btn-close-white" onClick={onClose} disabled={processing}></button>
          </div>
          <div className="modal-body p-0 position-relative" style={{ height: '450px', background: '#333' }}>
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
          <div className="modal-footer d-flex justify-content-between align-items-center bg-light">
            <div className="d-flex align-items-center" style={{ width: '30%' }}>
              <i className="fa fa-search-minus text-muted me-2"></i>
              <input
                type="range"
                className="form-range flex-grow-1"
                min={1}
                max={3}
                step={0.1}
                value={zoom}
                onChange={(e) => setZoom(Number(e.target.value))}
              />
              <i className="fa fa-search-plus text-muted ms-2"></i>
            </div>
            
            {!isLogo && (
              <div className="btn-group btn-group-sm mx-auto" role="group">
                <button type="button" className={`btn ${aspectRatio === null ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(null)}>Free</button>
                <button type="button" className={`btn ${aspectRatio === 1 ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(1)}>1:1</button>
                <button type="button" className={`btn ${aspectRatio === 4/3 ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(4/3)}>Landscape</button>
                <button type="button" className={`btn ${aspectRatio === 3/4 ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setAspectRatio(3/4)}>Portrait</button>
              </div>
            )}

            <div>
              <button className="btn btn-secondary me-2" onClick={onClose} disabled={processing}>
                Cancel
              </button>
              <button className="btn btn-primary" onClick={handleCropAndUpload} disabled={processing}>
                {processing ? (
                  <><i className="fa fa-spinner fa-spin me-2"></i>Processing...</>
                ) : (
                  <><i className="fa fa-check me-2"></i>Crop & Upload</>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

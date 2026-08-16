import { useEffect } from 'react'
import { downloadCsv } from '../../utils/csv'

/**
 * ReportExportModal
 * Reusable modal component for previewing live report data before exporting to CSV.
 * Ensures all roles and reports share the exact same UI and preview pattern.
 *
 * Props:
 *   preview: { title?: string, filename: string, rows: Array<Object> } | null
 *   onClose: () => void
 */
function ReportExportModal({ preview, onClose }) {
  useEffect(() => {
    if (!preview) return

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault()
        onClose?.()
      }
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [preview, onClose])

  if (!preview) return null

  const rows = preview.rows ?? []
  const filename = preview.filename || 'report-export'
  const title = preview.title || 'CSV Export Preview'
  const columns = rows.length > 0 ? Object.keys(rows[0]) : []

  const handleConfirmDownload = () => {
    downloadCsv(filename, rows)
    onClose?.()
  }

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
      onClose?.()
    }
  }

  return (
    <div
      className="modal fade show d-flex align-items-center justify-content-center"
      style={{
        position: 'fixed',
        inset: 0,
        width: '100vw',
        height: '100vh',
        backgroundColor: 'rgba(15, 23, 42, 0.65)',
        backdropFilter: 'blur(4px)',
        WebkitBackdropFilter: 'blur(4px)',
        zIndex: 10050,
      }}
      role="dialog"
      aria-modal="true"
      onClick={handleBackdropClick}
    >
      <div className="modal-dialog modal-xl modal-dialog-scrollable w-100 m-3" style={{ pointerEvents: 'auto', maxWidth: '1140px' }}>
        <div className="modal-content shadow-lg border-0" style={{ maxWidth: 'none', width: '100%' }}>
          <div className="modal-header bg-light py-3">
            <h5 className="modal-title d-flex align-items-center mb-0">
              <i className="fa fa-file-csv text-success me-2"></i>
              <span>{title}</span>
            </h5>
            <button
              type="button"
              className="btn-close"
              onClick={onClose}
              aria-label="Close"
            ></button>
          </div>

          <div className="modal-body p-4">
            <div className="alert alert-interntrack py-2 px-3 mb-3 d-flex flex-wrap justify-content-between align-items-center">
              <small className="mb-1 mb-sm-0">
                <i className="fa fa-info-circle me-1"></i>
                You are previewing the exact data (<strong>{rows.length}</strong> {rows.length === 1 ? 'row' : 'rows'}) that will be downloaded as <strong>{filename.endsWith('.csv') ? filename : `${filename}.csv`}</strong>.
              </small>
              {columns.length > 0 && (
                <span className="badge bg-success ms-auto">{columns.length} Columns</span>
              )}
            </div>

            {rows.length === 0 ? (
              <div className="text-center py-5 text-muted">
                <i className="fa fa-folder-open fa-2x mb-2 d-block"></i>
                No data rows available for this report export.
              </div>
            ) : (
              <div className="table-responsive border rounded" style={{ maxHeight: '55vh', overflowY: 'auto' }}>
                <table className="table table-sm table-hover table-striped table-bordered mb-0 text-nowrap" style={{ fontSize: '0.875rem' }}>
                  <thead className="table-dark sticky-top" style={{ zIndex: 1 }}>
                    <tr>
                      <th className="text-center" style={{ width: '40px' }}>#</th>
                      {columns.map((col) => (
                        <th key={col}>{col}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row, idx) => (
                      <tr key={idx}>
                        <td className="text-center text-muted fw-bold">{idx + 1}</td>
                        {columns.map((col) => {
                          const val = row[col]
                          const displayVal = val !== null && val !== undefined ? String(val) : '—'
                          return <td key={col}>{displayVal}</td>
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div className="modal-footer bg-light py-2 d-flex justify-content-between">
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={onClose}
            >
              <i className="fa fa-times me-1"></i>Cancel
            </button>
            <button
              type="button"
              className="btn btn-success btn-sm px-3"
              onClick={handleConfirmDownload}
              disabled={rows.length === 0}
            >
              <i className="fa fa-download me-1"></i>Confirm & Download CSV
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default ReportExportModal

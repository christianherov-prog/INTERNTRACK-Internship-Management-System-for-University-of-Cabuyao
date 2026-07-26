import { useEffect } from 'react'
import { createPortal } from 'react-dom'
import { downloadCsv } from '../../utils/csv'

/**
 * ReportExportModal
 * Reusable modal for previewing live report data before exporting to CSV.
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
  const downloadName = filename.endsWith('.csv') ? filename : `${filename}.csv`
  const needsHorizontalScroll = columns.length > 4

  const handleConfirmDownload = () => {
    downloadCsv(filename, rows)
    onClose?.()
  }

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
      onClose?.()
    }
  }

  return createPortal(
    <div
      className="modal fade show d-block report-export-modal"
      style={{ backgroundColor: 'rgba(0, 0, 0, 0.55)', zIndex: 1060 }}
      role="dialog"
      aria-modal="true"
      aria-labelledby="report-export-modal-title"
      onClick={handleBackdropClick}
    >
      <div className="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable report-export-dialog">
        <div className="modal-content shadow-lg border-0">
          <div className="modal-header bg-light py-3">
            <h5 id="report-export-modal-title" className="modal-title d-flex align-items-center mb-0">
              <i className="fa fa-file-csv text-success me-2" aria-hidden="true" />
              <span>{title}</span>
            </h5>
            <button
              type="button"
              className="btn-close"
              onClick={onClose}
              aria-label="Close"
            />
          </div>

          <div className="modal-body p-4">
            <div className="alert alert-interntrack py-2 px-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <small className="mb-0">
                <i className="fa fa-info-circle me-1" aria-hidden="true" />
                You are previewing the exact data (
                <strong>{rows.length}</strong>{' '}
                {rows.length === 1 ? 'row' : 'rows'}
                ) that will be downloaded as <strong>{downloadName}</strong>.
              </small>
              {columns.length > 0 && (
                <span className="badge bg-success">{columns.length} Columns</span>
              )}
            </div>

            {rows.length === 0 ? (
              <div className="text-center py-5 text-muted">
                <i className="fa fa-folder-open fa-2x mb-2 d-block" aria-hidden="true" />
                No data rows available for this report export.
              </div>
            ) : (
              <>
                {needsHorizontalScroll && (
                  <div className="report-export-scroll-hint" role="note">
                    <i className="fa fa-arrows-alt-h" aria-hidden="true" />
                    Scroll sideways to see all columns
                    <span aria-hidden="true"> →</span>
                  </div>
                )}
                <div className="report-export-table-wrap table-responsive border rounded">
                  <table className="table table-sm table-hover table-striped table-bordered mb-0 report-export-table">
                    <thead className="sticky-top">
                      <tr>
                        <th className="text-center report-export-rownum">#</th>
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
              </>
            )}
          </div>

          <div className="modal-footer bg-light py-2 d-flex justify-content-between">
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={onClose}
            >
              <i className="fa fa-times me-1" aria-hidden="true" />
              Cancel
            </button>
            <button
              type="button"
              className="btn btn-success btn-sm px-3"
              onClick={handleConfirmDownload}
              disabled={rows.length === 0}
            >
              <i className="fa fa-download me-1" aria-hidden="true" />
              Confirm & Download CSV
            </button>
          </div>
        </div>
      </div>
    </div>,
    document.body
  )
}

export default ReportExportModal

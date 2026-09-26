import { useMemo } from 'react'
import { downloadCsv } from '../../utils/csv'
import AppModal from './AppModal'
import StatusChip from '../StatusChip'
import { RequirementChipList, parseRequirementsStatusCsv } from '../ComplianceRequirementsStatus'

// Columns whose values run longer than this wrap inside a readable width
// instead of stretching the table; everything else stays on one line.
const LONG_VALUE = 48

/**
 * ReportExportModal
 * Reusable modal component for previewing live report data before exporting to CSV.
 * Ensures all roles and reports share the exact same UI and preview pattern.
 *
 * Props:
 *   preview: {
 *     title?: string, filename: string, rows: Array<Object>,
 *     statusColumns?: string[]       // columns rendered as one status chip (text unchanged)
 *     requirementColumns?: string[]  // "Name: Status; …" columns rendered as requirement chips
 *   } | null
 *   onClose: () => void
 *
 * Chips are on-screen presentation only — the downloaded CSV is built from the
 * same plain `rows`, so it never contains markup.
 */
function ReportExportModal({ preview, onClose }) {
  const rows = preview?.rows ?? []
  const columns = useMemo(() => (rows.length > 0 ? Object.keys(rows[0]) : []), [rows])
  const longColumns = useMemo(() => new Set(
    columns.filter((col) => rows.some((row) => String(row[col] ?? '').length > LONG_VALUE))
  ), [columns, rows])

  if (!preview) return null

  const statusColumns = new Set(preview.statusColumns ?? [])
  const requirementColumns = new Set(preview.requirementColumns ?? [])

  const renderCell = (col, val) => {
    if (val === null || val === undefined || val === '') return '—'
    if (statusColumns.has(col)) return <StatusChip status={val} label={String(val)} />
    if (requirementColumns.has(col)) {
      const items = parseRequirementsStatusCsv(val)
      if (items.length > 0) return <RequirementChipList items={items} />
      return <StatusChip status="approved" label={String(val)} />
    }
    return String(val)
  }

  const filename = preview.filename || 'report-export'
  const title = preview.title || 'CSV Export Preview'

  const handleConfirmDownload = () => {
    downloadCsv(filename, rows)
    onClose?.()
  }

  return (
    <AppModal
      onClose={onClose}
      size="data"
      title={title}
      icon="fa-file-csv"
      closeOnBackdrop
      fillBody
      testId="report-export-modal"
      footerAlign="between"
      footer={(
        <>
          <button type="button" className="btn btn-secondary btn-sm" onClick={onClose}>
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
        </>
      )}
    >
      <div className="alert alert-interntrack py-2 px-3 mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small>
          <i className="fa fa-info-circle me-1"></i>
          You are previewing the exact data (<strong>{rows.length}</strong> {rows.length === 1 ? 'row' : 'rows'}) that will be downloaded as <strong>{filename.endsWith('.csv') ? filename : `${filename}.csv`}</strong>.
        </small>
        {columns.length > 0 && (
          <span className="badge bg-success">{columns.length} Columns</span>
        )}
      </div>

      {rows.length === 0 ? (
        <div className="it-modal__empty border rounded">
          <i className="fa fa-folder-open fa-2x"></i>
          No data rows available for this report export.
        </div>
      ) : (
        <div className="it-modal__table-wrap">
          <table className="table table-sm table-hover table-striped table-bordered mb-0 text-nowrap it-report-preview" style={{ fontSize: '0.875rem' }}>
            <thead className="it-report-preview__head">
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
                    const cellClass = requirementColumns.has(col) ? 'it-cell-chips' : (longColumns.has(col) ? 'it-cell-long' : undefined)
                    return <td key={col} className={cellClass}>{renderCell(col, row[col])}</td>
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppModal>
  )
}

export default ReportExportModal

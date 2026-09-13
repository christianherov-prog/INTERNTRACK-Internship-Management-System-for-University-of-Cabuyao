/**
 * Approved/satisfied compliance progress — always success/green fill.
 * Red is reserved for missing/rejected lists, not for low approved %.
 */
export default function ComplianceApprovedProgress({
  pct = 0,
  approved = null,
  required = null,
  caption = 'approved',
}) {
  const safePct = Math.min(100, Math.max(0, Number(pct) || 0))
  const showCounts = approved != null && required != null

  return (
    <div className="compliance-approved-progress">
      <div className="d-flex align-items-center gap-2">
        <div
          className="progress flex-grow-1"
          style={{ height: '8px', backgroundColor: '#e8eef3' }}
          role="progressbar"
          aria-valuenow={safePct}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label="Approved requirements progress"
        >
          <div
            className="progress-bar bg-success"
            style={{ width: `${safePct}%` }}
          />
        </div>
        <small className="text-muted">{safePct}%</small>
      </div>
      {showCounts && (
        <small className="text-muted">
          {approved}/{required} {caption}
        </small>
      )}
    </div>
  )
}

/**
 * Approved/satisfied compliance progress. The percentage is shown exactly as
 * calculated by the compliance resolver; only its presentation changes:
 *   100%            → green bar
 *   below 100%      → amber bar (still in progress)
 *   missing/rejected present → an extra red indicator with the counts
 * `requirements` (optional) is the normalized requirement list for the row.
 */
export default function ComplianceApprovedProgress({
  pct = 0,
  approved = null,
  required = null,
  caption = 'approved',
  requirements = null,
}) {
  const safePct = Math.min(100, Math.max(0, Number(pct) || 0))
  const showCounts = approved != null && required != null
  const complete = safePct >= 100
  const missing = (requirements || []).filter((r) => r.status === 'missing').length
  const rejected = (requirements || []).filter((r) => r.status === 'rejected').length
  const problems = [missing && `${missing} missing`, rejected && `${rejected} rejected`].filter(Boolean)

  return (
    <div className={`compliance-approved-progress ${complete ? 'is-complete' : 'is-partial'}`}>
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
            className={`progress-bar ${complete ? 'bg-success' : 'compliance-progress-partial'}`}
            style={{ width: `${safePct}%` }}
          />
        </div>
        <small className={`fw-semibold ${complete ? 'text-success' : 'compliance-pct-partial'}`}>{safePct}%</small>
      </div>
      {showCounts && (
        <small className="text-muted d-block">
          {approved}/{required} {caption}
        </small>
      )}
      {!complete && problems.length > 0 && (
        <small className="compliance-problem-indicator d-block">
          <i className="fa fa-circle-exclamation me-1" aria-hidden="true"></i>{problems.join(' · ')}
        </small>
      )}
    </div>
  )
}

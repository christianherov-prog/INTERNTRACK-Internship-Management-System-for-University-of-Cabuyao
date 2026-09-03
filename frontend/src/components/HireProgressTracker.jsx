import { computeHireProgress, HIRE_MILESTONES } from '../utils/hireProgress'

/**
 * Visual hire-path milestones: 0% → 25% → 50% → 75% → 100% (hired / not hired).
 * @param {{ internship?: object|null, compact?: boolean, showLegend?: boolean }} props
 */
function HireProgressTracker({ internship = null, compact = false, showLegend = false }) {
  const progress = computeHireProgress(internship)
  const outcomeClass =
    progress.outcome === 'absorbed'
      ? 'hire-progress--absorbed'
      : progress.outcome === 'not_hired'
        ? 'hire-progress--not-hired'
        : ''

  return (
    <div className={`hire-progress ${compact ? 'hire-progress--compact' : ''} ${outcomeClass}`.trim()}>
      {!compact && (
        <div className="hire-progress__header">
          <span className="hire-progress__title">Hire / Absorption Progress</span>
          <span className="hire-progress__badge">{progress.percent}%</span>
        </div>
      )}

      <div className="hire-progress__track" role="list" aria-label="Hire progress milestones">
        {HIRE_MILESTONES.map((m, idx) => {
          const reached = progress.percent >= m.percent
          const current = progress.percent === m.percent
          return (
            <div
              key={m.key}
              className={[
                'hire-progress__step',
                reached ? 'is-reached' : '',
                current ? 'is-current' : '',
              ].filter(Boolean).join(' ')}
              role="listitem"
            >
              {idx > 0 && <span className={`hire-progress__connector ${progress.percent >= m.percent ? 'is-filled' : ''}`} />}
              <span className="hire-progress__dot" />
              <span className="hire-progress__pct">{m.short}</span>
              {!compact && <span className="hire-progress__hint">{m.label}</span>}
            </div>
          )
        })}
      </div>

      <div className="hire-progress__status">
        {compact ? (
          <span className="hire-progress__badge">{progress.percent}% — {progress.label}</span>
        ) : (
          <>
            <strong>{progress.label}</strong>
            {progress.hoursPercent > 0 && progress.percent < 75 && (
              <span className="text-muted ms-2" style={{ fontSize: '0.85rem' }}>
                (hours: {progress.hoursPercent}%)
              </span>
            )}
          </>
        )}
      </div>

      {showLegend && (
        <ul className="hire-progress__legend">
          <li><strong>0%</strong> Just placed</li>
          <li><strong>25% / 50%</strong> Hours progressing</li>
          <li><strong>75%</strong> Completed — confirm hire</li>
          <li><strong>100%</strong> Absorbed or Not Hired</li>
        </ul>
      )}

      <style>{`
        .hire-progress {
          --hp-accent: #1a7a3f;
          --hp-muted: #c5cdd6;
          --hp-text: #1f2937;
          padding: 0.85rem 1rem;
          border: 1px solid #e5e7eb;
          border-radius: 10px;
          background: linear-gradient(180deg, #f8faf8 0%, #ffffff 100%);
        }
        .hire-progress--compact {
          padding: 0.45rem 0.6rem;
          background: #fff;
        }
        .hire-progress--absorbed { --hp-accent: #15803d; }
        .hire-progress--not-hired { --hp-accent: #b45309; }
        .hire-progress__header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 0.75rem;
          gap: 0.5rem;
        }
        .hire-progress__title {
          font-weight: 600;
          color: var(--hp-text);
          font-size: 0.95rem;
        }
        .hire-progress__badge {
          font-size: 0.78rem;
          font-weight: 600;
          color: var(--hp-accent);
          background: color-mix(in srgb, var(--hp-accent) 12%, white);
          border-radius: 6px;
          padding: 0.2rem 0.55rem;
          white-space: nowrap;
        }
        .hire-progress__track {
          display: flex;
          align-items: flex-start;
          justify-content: space-between;
          gap: 0.15rem;
          position: relative;
        }
        .hire-progress__step {
          flex: 1;
          display: flex;
          flex-direction: column;
          align-items: center;
          position: relative;
          text-align: center;
          min-width: 0;
        }
        .hire-progress__connector {
          position: absolute;
          left: -50%;
          right: 50%;
          top: 7px;
          height: 3px;
          background: var(--hp-muted);
          z-index: 0;
        }
        .hire-progress__connector.is-filled { background: var(--hp-accent); }
        .hire-progress__dot {
          width: 14px;
          height: 14px;
          border-radius: 50%;
          border: 2px solid var(--hp-muted);
          background: #fff;
          z-index: 1;
          margin-bottom: 0.35rem;
        }
        .hire-progress__step.is-reached .hire-progress__dot {
          border-color: var(--hp-accent);
          background: var(--hp-accent);
        }
        .hire-progress__step.is-current .hire-progress__dot {
          box-shadow: 0 0 0 3px color-mix(in srgb, var(--hp-accent) 25%, transparent);
        }
        .hire-progress__pct {
          font-size: 0.72rem;
          font-weight: 700;
          color: #6b7280;
        }
        .hire-progress__step.is-reached .hire-progress__pct { color: var(--hp-accent); }
        .hire-progress__hint {
          font-size: 0.65rem;
          color: #9ca3af;
          line-height: 1.25;
          margin-top: 0.15rem;
          max-width: 5.5rem;
        }
        .hire-progress__status {
          margin-top: 0.75rem;
          font-size: 0.9rem;
          color: var(--hp-text);
        }
        .hire-progress--compact .hire-progress__status { margin-top: 0.4rem; }
        .hire-progress__legend {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem 1rem;
          list-style: none;
          padding: 0.65rem 0 0;
          margin: 0.65rem 0 0;
          border-top: 1px dashed #e5e7eb;
          font-size: 0.78rem;
          color: #4b5563;
        }
        @media (max-width: 576px) {
          .hire-progress__hint { display: none; }
        }
      `}</style>
    </div>
  )
}

export default HireProgressTracker

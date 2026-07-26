import { Link } from 'react-router-dom'

/**
 * Shared dashboard Quick Actions panel.
 * Matches the Faculty dashboard pattern: colored icon circle, title, subtitle, chevron,
 * and a left-border accent that matches each action's tone.
 *
 * @param {{ actions: Array<{ to: string, title: string, description: string, icon: string, tone?: 'blue'|'green'|'amber'|'gray'|'teal' }>, footer?: import('react').ReactNode, className?: string }} props
 */
function QuickActionsPanel({ actions = [], footer = null, className = '' }) {
  if (!actions.length) return null

  return (
    <div className={`content-card h-100 quick-actions-panel ${className}`.trim()}>
      <div className="content-card-header bg-light">
        <i className="fa fa-bolt" aria-hidden="true"></i>
        <h6 className="mb-0">Quick Actions</h6>
      </div>
      <div className="p-4">
        <div className="d-grid gap-3 quick-actions-list">
          {actions.map((action) => {
            const tone = action.tone || 'green'
            return (
              <Link
                key={`${action.to}-${action.title}`}
                to={action.to}
                className={`quick-action-card quick-action-${tone} text-start p-3 d-flex align-items-center text-decoration-none`}
              >
                <div className="quick-action-icon me-3" aria-hidden="true">
                  <i className={`fa ${action.icon}`}></i>
                </div>
                <div className="quick-action-copy flex-grow-1 min-w-0">
                  <h6 className="mb-1 fw-bold quick-action-title">{action.title}</h6>
                  <small className="text-muted d-block">{action.description}</small>
                </div>
                <i className="fa fa-chevron-right ms-2 text-muted flex-shrink-0" aria-hidden="true"></i>
              </Link>
            )
          })}
        </div>
      </div>
      {footer}
    </div>
  )
}

export default QuickActionsPanel

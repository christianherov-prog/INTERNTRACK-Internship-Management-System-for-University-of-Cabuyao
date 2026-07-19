/**
 * Shared KPI tile — uses the same .stat-card styles as Student/Coordinator dashboards
 * (green theme tokens), not the director-only blue card variant.
 */
function StatCard({ value, label, icon = 'fa-chart-bar', tone = 'green' }) {
  return (
    <div className="stat-card">
      <div className={`stat-icon ${tone}`}>
        <i className={`fa ${icon}`} aria-hidden="true" />
      </div>
      <div>
        <div className="stat-value">{value}</div>
        <div className="stat-label">{label}</div>
      </div>
    </div>
  )
}

export default StatCard

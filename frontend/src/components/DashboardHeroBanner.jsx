/**
 * Shared green welcome banner used on role dashboards
 * (extracted from the Student dashboard hero).
 *
 * Props:
 *   label   – small uppercase eyebrow (e.g. "DIRECTOR DASHBOARD")
 *   title   – main greeting
 *   meta    – array of strings joined with " | "
 *   badges  – array of { text, variant?: 'ongoing' | 'term' | 'muted' }
 *   loading – shows a compact skeleton state
 *   error   – optional error string under the title
 */
function DashboardHeroBanner({
  label = 'DASHBOARD',
  title = 'Welcome back',
  meta = [],
  badges = [],
  loading = false,
  error = null,
}) {
  if (loading) {
    return (
      <div className="dashboard-hero-banner mb-4 dashboard-hero-banner--loading" aria-busy="true">
        <div className="hero-banner-content">
          <div className="hero-banner-label">{label}</div>
          <h1 className="hero-banner-title">Loading summary…</h1>
          <div className="hero-banner-meta">
            <span><i className="fa fa-spinner fa-spin" aria-hidden="true" /> Fetching live data</span>
          </div>
        </div>
      </div>
    )
  }

  const metaItems = (meta || []).filter(Boolean)

  return (
    <div className="dashboard-hero-banner mb-4">
      <div className="hero-banner-content">
        <div className="hero-banner-label">{label}</div>
        <h1 className="hero-banner-title">{title}</h1>
        {error ? (
          <div className="hero-banner-meta text-warning">
            <span><i className="fa fa-exclamation-triangle me-1" aria-hidden="true" />{error}</span>
          </div>
        ) : metaItems.length > 0 ? (
          <div className="hero-banner-meta">
            {metaItems.map((item, i) => (
              <span key={`${item}-${i}`} className="hero-banner-meta-item">
                {i > 0 && <span className="meta-separator" aria-hidden="true">|</span>}
                {item}
              </span>
            ))}
          </div>
        ) : null}
      </div>
      {badges?.length > 0 && (
        <div className="hero-banner-badges">
          {badges.map((badge, i) => (
            <span
              key={`${badge.text}-${i}`}
              className={`badge-hero badge-${badge.variant || 'term'}`}
            >
              {badge.text}
            </span>
          ))}
        </div>
      )}
    </div>
  )
}

export default DashboardHeroBanner

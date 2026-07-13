import { useEffect, useState } from 'react';
import client from '../api/client';
import LoadingCard from '../components/LoadingCard.jsx';
import HoursChart from '../components/HoursChart.jsx';

const ANNOUNCEMENT_ICONS = {
  info: 'fa-info-circle',
  warning: 'fa-exclamation-triangle',
  general: 'fa-file-upload',
};

const STATUS_BADGES = {
  validated: 'badge-active',
  reviewed: 'badge-active',
  approved: 'badge-active',
  received: 'badge-active',
  submitted: 'badge-completed',
  pending: 'badge-pending',
  rejected: 'badge-overdue',
};

function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function scoreLabel(score) {
  if (score >= 90) return 'Excellent';
  if (score >= 85) return 'Very Good';
  if (score >= 75) return 'Good';
  if (score >= 60) return 'Fair';
  return 'Needs Improvement';
}

export default function Dashboard() {
  const [summary, setSummary] = useState(null);
  const [announcements, setAnnouncements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    Promise.all([client.get('/student/dashboard'), client.get('/announcements')])
      .then(([dashRes, annRes]) => {
        if (cancelled) return;
        setSummary(dashRes.data);
        setAnnouncements(annRes.data.announcements);
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load dashboard data. Please try again.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (loading) {
    return (
      <main className="main-content">
        <div className="dashboard-shell">
          <LoadingCard label="Loading your dashboard..." />
        </div>
      </main>
    );
  }

  if (error || !summary) {
    return (
      <main className="main-content">
        <div className="dashboard-shell">
          <div className="alert-interntrack"><i className="fa fa-circle-info me-2"></i>{error || 'No data available.'}</div>
        </div>
      </main>
    );
  }

  const student = summary.student;
  const completion = summary.completion_percent;
  const compliance = summary.document_compliance_percent;
  const evalScore = summary.evaluation_score;

  return (
    <main className="main-content">
      <div className="dashboard-shell">

        <div className="content-card dashboard-hero mb-4">
          <div className="row align-items-center g-3">
            <div className="col-lg">
              <div className="dashboard-kicker">Internship Dashboard</div>
              <h4 className="dashboard-hero-title">Welcome back, {student.full_name}</h4>
              <p className="dashboard-hero-meta">
                {student.course_year_section} &nbsp;|&nbsp; Student No. {student.student_number} &nbsp;|&nbsp; {student.company_name}
              </p>
            </div>
            <div className="col-lg-auto">
              <div className="hero-chip-group">
                <span className="hero-chip">Ongoing Internship</span>
                <span className="hero-chip hero-chip-muted">AY 2024-2025, Sem 2</span>
              </div>
            </div>
          </div>
        </div>

        <div className="row g-3 mb-4">
          <div className="col-sm-6 col-xl-3">
            <div className="stat-card">
              <div className="stat-icon green"><i className="fa fa-clock"></i></div>
              <div>
                <div className="stat-value">{summary.hours_rendered}</div>
                <div className="stat-label">Hours Rendered</div>
              </div>
            </div>
          </div>
          <div className="col-sm-6 col-xl-3">
            <div className="stat-card it-inline-022">
              <div className="stat-icon teal"><i className="fa fa-calendar-check"></i></div>
              <div>
                <div className="stat-value">{summary.days_present}</div>
                <div className="stat-label">Days Present</div>
              </div>
            </div>
          </div>
          <div className="col-sm-6 col-xl-3">
            <div className="stat-card it-inline-026">
              <div className="stat-icon amber"><i className="fa fa-book-open"></i></div>
              <div>
                <div className="stat-value">{summary.journal_entries}</div>
                <div className="stat-label">Journal Entries</div>
              </div>
            </div>
          </div>
          <div className="col-sm-6 col-xl-3">
            <div className="stat-card it-inline-023">
              <div className="stat-icon blue"><i className="fa fa-file-alt"></i></div>
              <div>
                <div className="stat-value">{summary.docs_submitted}/{summary.docs_required}</div>
                <div className="stat-label">Docs Submitted</div>
              </div>
            </div>
          </div>
        </div>

        <div className="row g-3 mb-4">
          <div className="col-lg-7">
            <div className="content-card h-100">
              <div className="content-card-header">
                <i className="fa fa-bolt"></i>
                <h6>Quick Overview</h6>
              </div>
              <div className="overview-grid">
                <div className="overview-block">
                  <span className="overview-label">Completed Hours</span>
                  <div className="overview-value-row">
                    <strong>{summary.hours_rendered} / {summary.required_hours} hrs</strong>
                    <span>{completion}%</span>
                  </div>
                  <div className="progress-track mb-3">
                    <div className="progress-fill" style={{ width: `${completion}%` }}></div>
                  </div>
                </div>
                <div className="overview-block">
                  <span className="overview-label">Document Compliance</span>
                  <div className="overview-value-row">
                    <strong>{compliance}%</strong>
                    <span>{summary.docs_submitted} of {summary.docs_required} files</span>
                  </div>
                  <div className="progress-track mb-3">
                    <div className="progress-fill" style={{ width: `${compliance}%`, background: 'linear-gradient(90deg,#0d8e80,#4bc97a)' }}></div>
                  </div>
                </div>
                <div className="overview-block">
                  <span className="overview-label">Evaluation Score</span>
                  <div className="overview-value-row">
                    <strong>{evalScore !== null ? `${evalScore} / ${summary.evaluation_max_score}` : 'No evaluation yet'}</strong>
                    <span>{evalScore !== null ? scoreLabel(evalScore) : '—'}</span>
                  </div>
                  <div className="progress-track">
                    <div className="progress-fill" style={{ width: `${evalScore !== null ? Math.round((evalScore / summary.evaluation_max_score) * 100) : 0}%`, background: 'linear-gradient(90deg,#d4a017,#f4c842)' }}></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="col-lg-5">
            <div className="content-card h-100">
              <div className="content-card-header">
                <i className="fa fa-bell"></i>
                <h6>Announcements</h6>
              </div>
              <div className="announcement-stack">
                {announcements.length === 0 && (
                  <div className="announcement-item info">
                    <div className="announcement-icon"><i className="fa fa-info-circle"></i></div>
                    <div><strong>No announcements yet</strong><p>Updates from your coordinator will appear here.</p></div>
                  </div>
                )}
                {announcements.slice(0, 3).map((a) => (
                  <div key={a.id} className={`announcement-item ${a.type !== 'general' ? a.type : ''}`.trim()}>
                    <div className="announcement-icon">
                      <i className={`fa ${ANNOUNCEMENT_ICONS[a.type] || 'fa-info-circle'}`}></i>
                    </div>
                    <div><strong>{a.title}</strong><p>{a.body}</p></div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="content-card recent-activity-card mb-4">
          <div className="content-card-header">
            <i className="fa fa-history"></i>
            <h6>Recent Activity</h6>
          </div>
          <div className="table-card table-card-soft">
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0 recent-activity-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Activity</th>
                    <th>Type</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {summary.recent_activity.length === 0 && (
                    <tr>
                      <td colSpan="4" className="text-center">No activity yet. Log attendance, submit a journal entry, or upload a document to get started.</td>
                    </tr>
                  )}
                  {summary.recent_activity.map((item, i) => (
                    <tr key={i}>
                      <td>{formatDate(item.date)}</td>
                      <td>{item.activity}</td>
                      <td>{item.type}</td>
                      <td>
                        <span className={`badge-status ${STATUS_BADGES[item.status.toLowerCase()] || 'badge-pending'}`}>
                          {item.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <section id="monitoring-section" className="monitoring-section">
          <div className="section-header monitoring-header">
            <div>
              <div className="section-title">Dashboard Monitoring</div>
              <p className="monitoring-subtitle">Weekly performance and hours completion in one view.</p>
            </div>
          </div>

          <div className="monitoring-grid">
            <div className="content-card monitoring-card chart-panel">
              <div className="monitoring-card-head">
                <div>
                  <h6>Weekly OJT Hours</h6>
                  <p>Dashboard view with a 20 hrs/week target baseline</p>
                </div>
                <div className="monitoring-meta">Target: 20 hrs/week</div>
              </div>
              <div className="chart-wrap">
                {summary.weekly_hours.length === 0 ? (
                  <div className="alert-interntrack"><i className="fa fa-circle-info me-2"></i>No attendance logged yet. Your weekly hours will appear here.</div>
                ) : (
                  <HoursChart weeklyHours={summary.weekly_hours} />
                )}
              </div>
            </div>
          </div>
        </section>

      </div>

      <footer className="app-footer">&copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span></footer>
    </main>
  );
}

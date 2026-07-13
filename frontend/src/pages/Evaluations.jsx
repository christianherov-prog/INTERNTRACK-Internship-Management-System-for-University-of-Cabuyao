import { useEffect, useState } from 'react';
import client from '../api/client';
import LoadingCard from '../components/LoadingCard.jsx';

const BREAKDOWN_STYLES = {
  work_quality: { label: 'Work Quality', background: undefined },
  punctuality: { label: 'Punctuality', background: 'linear-gradient(90deg,#0d8e80,#4bc97a)' },
  communication: { label: 'Communication', background: 'linear-gradient(90deg,#1a73e8,#67a8ff)' },
  initiative: { label: 'Initiative', background: 'linear-gradient(90deg,#d4a017,#f4c842)' },
};

function formatDate(value) {
  if (!value) return 'Pending';
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export default function Evaluations() {
  const [evaluations, setEvaluations] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    client
      .get('/student/evaluations')
      .then(({ data }) => {
        if (cancelled) return;
        setEvaluations(data.evaluations);
        setStats(data.stats);
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load evaluations.');
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
      <main className="main-content evaluation-page">
        <div className="page-shell">
          <LoadingCard label="Loading evaluations..." />
        </div>
      </main>
    );
  }

  if (error) {
    return (
      <main className="main-content evaluation-page">
        <div className="page-shell">
          <div className="alert-interntrack"><i className="fa fa-circle-info me-2"></i>{error}</div>
        </div>
      </main>
    );
  }

  const currentScore = stats?.current_score;
  const maxScore = stats?.max_score ?? 100;
  const breakdown = stats?.breakdown;

  return (
    <main className="main-content evaluation-page">
      <div className="page-shell">
        <div className="row g-4 mb-4">
          <div className="col-sm-6 col-xl-3"><div className="stat-card evaluation-stat evaluation-stat-score it-inline-024"><div className="stat-icon green"><i className="fa fa-star"></i></div><div><div className="stat-value">{currentScore ?? '—'}</div><div className="stat-label">Current Score</div></div></div></div>
          <div className="col-sm-6 col-xl-3"><div className="stat-card evaluation-stat evaluation-stat-trend it-inline-022"><div className="stat-icon teal"><i className="fa fa-clipboard-check"></i></div><div><div className="stat-value">{stats?.forms_received ?? 0}</div><div className="stat-label">Forms Received</div></div></div></div>
          <div className="col-sm-6 col-xl-3"><div className="stat-card evaluation-stat evaluation-stat-forms it-inline-026"><div className="stat-icon amber"><i className="fa fa-list-check"></i></div><div><div className="stat-value">{stats?.forms_received ?? 0}/{stats?.forms_total ?? 0}</div><div className="stat-label">Forms Submitted</div></div></div></div>
          <div className="col-sm-6 col-xl-3"><div className="stat-card evaluation-stat evaluation-stat-pending it-inline-023"><div className="stat-icon blue"><i className="fa fa-user-tie"></i></div><div><div className="stat-value">{stats?.pending ?? 0}</div><div className="stat-label">Supervisor Pending</div></div></div></div>
        </div>

        <div className="row g-4 mb-4">
          <div className="col-lg-7">
            <div className="content-card evaluation-panel h-100">
              <div className="content-card-header evaluation-panel-header">
                <div className="evaluation-header-copy"><i className="fa fa-award"></i><div><h6>Evaluation Breakdown</h6><p className="panel-subtitle">Performance indicators based on submitted supervisor and coordinator assessments.</p></div></div>
                <span className="hero-chip hero-chip-muted evaluation-chip">
                  {currentScore !== null && currentScore !== undefined ? `Overall ${currentScore} / ${maxScore}` : 'No score yet'}
                </span>
              </div>
              <div className="evaluation-breakdown">
                {!breakdown && (
                  <div className="alert-interntrack"><i className="fa fa-circle-info me-2"></i>No evaluation has been submitted by your supervisor yet.</div>
                )}
                {breakdown &&
                  Object.entries(BREAKDOWN_STYLES).map(([key, meta]) => {
                    const value = breakdown[key];
                    if (value === null || value === undefined) return null;
                    return (
                      <div className="evaluation-metric" key={key}>
                        <div className="evaluation-metric-head"><span>{meta.label}</span><strong>{value}%</strong></div>
                        <div className="progress-track progress-track-thick">
                          <div className="progress-fill" style={{ width: `${value}%`, background: meta.background }}></div>
                        </div>
                      </div>
                    );
                  })}
              </div>
            </div>
          </div>

          <div className="col-lg-5">
            <div className="content-card evaluation-panel h-100">
              <div className="content-card-header evaluation-panel-header">
                <div className="evaluation-header-copy"><i className="fa fa-list-timeline"></i><div><h6>Evaluation Schedule</h6><p className="panel-subtitle">Track completed milestones and pending assessments.</p></div></div>
              </div>
              <div className="evaluation-timeline">
                {evaluations.length === 0 && (
                  <div className="alert-interntrack"><i className="fa fa-circle-info me-2"></i>No evaluations scheduled yet.</div>
                )}
                {evaluations.map((ev) => (
                  <div key={ev.id} className={`evaluation-timeline-item ${ev.status === 'received' ? 'is-done' : 'is-pending'}`}>
                    <div className="timeline-dot"></div>
                    <div className="timeline-copy">
                      <strong>{ev.evaluation_type}</strong>
                      <span>{ev.status === 'received' ? `Completed on ${formatDate(ev.evaluated_at)}` : 'Awaiting evaluator'}</span>
                    </div>
                    <span className={`badge-status ${ev.status === 'received' ? 'badge-active' : 'badge-pending'}`}>
                      {ev.status === 'received' ? 'Completed' : 'Upcoming'}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="content-card evaluation-table-panel">
          <div className="content-card-header evaluation-panel-header">
            <div className="evaluation-header-copy"><i className="fa fa-clipboard-list"></i><div><h6>Evaluation Forms</h6><p className="panel-subtitle">Submitted forms, assigned evaluators, and follow-up actions for pending documents.</p></div></div>
          </div>
          <div className="table-card table-card-soft">
            <table className="table table-hover align-middle evaluation-table">
              <thead><tr><th>Form</th><th>Evaluator</th><th>Date</th><th>Status</th></tr></thead>
              <tbody>
                {evaluations.length === 0 && (
                  <tr><td colSpan="4" className="text-center">No evaluation forms yet. Evaluations are submitted by your supervisors.</td></tr>
                )}
                {evaluations.map((ev) => (
                  <tr key={ev.id}>
                    <td><div className="table-primary-text">{ev.evaluation_type}</div>{ev.remarks && <div className="table-secondary-text">{ev.remarks}</div>}</td>
                    <td><div className="table-primary-text">{ev.evaluator_name || 'To be assigned'}</div><div className="table-secondary-text">{ev.evaluator_role || ''}</div></td>
                    <td><div className="table-primary-text">{formatDate(ev.evaluated_at)}</div></td>
                    <td>
                      <span className={`badge-status ${ev.status === 'received' ? 'badge-active' : 'badge-pending'}`}>
                        {ev.status === 'received' ? 'Received' : 'Waiting'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <footer className="app-footer">&copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span></footer>
    </main>
  );
}

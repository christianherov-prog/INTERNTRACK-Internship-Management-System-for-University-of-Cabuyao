import { useCallback, useEffect, useState } from 'react';
import client from '../api/client';
import LoadingCard from '../components/LoadingCard.jsx';

function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

const EMPTY_FORM = { entry_date: '', hours_rendered: '', tasks_completed: '', learning_reflection: '' };

export default function Logbook() {
  const [entries, setEntries] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState(EMPTY_FORM);
  const [editingId, setEditingId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async () => {
    try {
      const { data } = await client.get('/student/logbook');
      setEntries(data.entries);
      setStats(data.stats);
      setError('');
    } catch {
      setError('Unable to load logbook entries.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const setField = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  const resetForm = () => {
    setForm(EMPTY_FORM);
    setEditingId(null);
    setFormError('');
  };

  const startEdit = (entry) => {
    setEditingId(entry.id);
    setForm({
      entry_date: entry.entry_date.slice(0, 10),
      hours_rendered: String(entry.hours_rendered),
      tasks_completed: entry.tasks_completed,
      learning_reflection: entry.learning_reflection || '',
    });
    setFormError('');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    setNotice('');
    setBusy(true);
    try {
      const payload = {
        entry_date: form.entry_date,
        hours_rendered: Number(form.hours_rendered),
        tasks_completed: form.tasks_completed,
        learning_reflection: form.learning_reflection || null,
      };
      if (editingId) {
        await client.put(`/student/logbook/${editingId}`, payload);
        setNotice('Entry updated successfully.');
      } else {
        await client.post('/student/logbook', payload);
        setNotice('Entry submitted successfully.');
      }
      resetForm();
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setFormError(
        errors ? Object.values(errors)[0][0] : err.response?.data?.message || 'Unable to save the entry.'
      );
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (entry) => {
    if (!window.confirm(`Delete the journal entry for ${formatDate(entry.entry_date)}?`)) return;
    try {
      await client.delete(`/student/logbook/${entry.id}`);
      if (editingId === entry.id) resetForm();
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to delete this entry.');
    }
  };

  if (loading) {
    return (
      <main className="main-content">
        <LoadingCard label="Loading logbook..." />
      </main>
    );
  }

  const recentEntries = entries.slice(0, 3);

  return (
    <main className="main-content">
      {error && <div className="alert-interntrack mb-3"><i className="fa fa-circle-info me-2"></i>{error}</div>}

      <div className="row g-3 mb-4">
        <div className="col-sm-6 col-xl-3"><div className="stat-card"><div className="stat-icon green"><i className="fa fa-book-open"></i></div><div><div className="stat-value">{stats?.submitted ?? 0}</div><div className="stat-label">Entries Submitted</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-022"><div className="stat-icon teal"><i className="fa fa-check-circle"></i></div><div><div className="stat-value">{stats?.reviewed ?? 0}</div><div className="stat-label">Reviewed</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-026"><div className="stat-icon amber"><i className="fa fa-pen"></i></div><div><div className="stat-value">{(stats?.submitted ?? 0) - (stats?.reviewed ?? 0)}</div><div className="stat-label">Awaiting Review</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-023"><div className="stat-icon blue"><i className="fa fa-calendar-week"></i></div><div><div className="stat-value">{entries.length ? formatDate(entries[0].entry_date) : '—'}</div><div className="stat-label">Latest Entry</div></div></div></div>
      </div>

      <div className="row g-3 mb-4">
        <div className="col-lg-5">
          <div className="content-card h-100">
            <div className="content-card-header"><i className="fa fa-pen-to-square"></i><h6>{editingId ? 'Edit Logbook Entry' : 'New Logbook Entry'}</h6></div>
            <form onSubmit={handleSubmit}>
              <div className="mb-3"><label className="form-label form-label-subtle">Entry Date</label><input type="date" className="form-control" value={form.entry_date} onChange={setField('entry_date')} required /></div>
              <div className="mb-3"><label className="form-label form-label-subtle">Hours Rendered</label><input type="number" min="0" max="24" step="0.5" className="form-control" value={form.hours_rendered} onChange={setField('hours_rendered')} required /></div>
              <div className="mb-3"><label className="form-label form-label-subtle">Tasks Completed</label><textarea className="form-control" rows="4" placeholder="Summarize duties, systems used, and deliverables completed." value={form.tasks_completed} onChange={setField('tasks_completed')} required></textarea></div>
              <div className="mb-3"><label className="form-label form-label-subtle">Learning Reflection</label><textarea className="form-control" rows="4" placeholder="What did you learn today?" value={form.learning_reflection} onChange={setField('learning_reflection')}></textarea></div>
              <div className="d-flex gap-2">
                <button type="submit" className="btn-green w-100" disabled={busy}>
                  <i className="fa fa-paper-plane me-1"></i>{busy ? 'Saving...' : editingId ? 'Update Entry' : 'Submit Entry'}
                </button>
                {editingId && (
                  <button type="button" className="btn-outline-green" onClick={resetForm}>Cancel</button>
                )}
              </div>
            </form>
            {formError && <div className="alert-interntrack mt-3"><i className="fa fa-circle-info me-2"></i>{formError}</div>}
            {notice && !formError && <div className="alert-interntrack mt-3"><i className="fa fa-circle-check me-2"></i>{notice}</div>}
          </div>
        </div>
        <div className="col-lg-7">
          <div className="content-card h-100 recent-entries-card">
            <div className="content-card-header recent-entries-header">
              <div className="recent-entries-title-wrap">
                <i className="fa fa-list-check"></i>
                <div>
                  <h6>Recent Entries</h6>
                  <span className="recent-entries-subtitle">Latest documented internship accomplishments and daily logbook updates</span>
                </div>
              </div>
              <span className="recent-entries-count">{recentEntries.length} {recentEntries.length === 1 ? 'entry' : 'entries'}</span>
            </div>
            <div className="recent-entries-list">
              {recentEntries.length === 0 && (
                <div className="alert-interntrack"><i className="fa fa-circle-info me-2"></i>No entries yet. Submit your first journal entry using the form.</div>
              )}
              {recentEntries.map((entry, idx) => (
                <article key={entry.id} className={`journal-entry-card${idx === 0 ? ' featured-entry' : ''}`}>
                  <div className="journal-entry-accent"></div>
                  <div className="journal-entry-content">
                    <div className="journal-entry-head">
                      <div className="journal-entry-date-block">
                        <span className="journal-entry-label">Entry Date</span>
                        <strong>{formatDate(entry.entry_date)}</strong>
                      </div>
                      <span className={`badge-status ${entry.status === 'reviewed' ? 'badge-reviewed' : 'badge-submitted'}`}>
                        {entry.status.charAt(0).toUpperCase() + entry.status.slice(1)}
                      </span>
                    </div>
                    <p>{entry.tasks_completed}</p>
                    <div className="journal-entry-meta">
                      <span><i className="fa-regular fa-clock"></i>{entry.hours_rendered} hours rendered</span>
                      {entry.learning_reflection && (
                        <span><i className="fa-solid fa-circle-check"></i>{entry.learning_reflection}</span>
                      )}
                    </div>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-book-bookmark"></i><h6>Logbook Timeline</h6></div>
        <div className="table-card">
          <table className="table table-hover">
            <thead><tr><th>Date</th><th>Summary</th><th>Hours</th><th>Reviewed By</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              {entries.length === 0 && (
                <tr><td colSpan="6" className="text-center">No logbook entries yet.</td></tr>
              )}
              {entries.map((entry) => (
                <tr key={entry.id}>
                  <td>{formatDate(entry.entry_date)}</td>
                  <td>{entry.tasks_completed}</td>
                  <td>{entry.hours_rendered}</td>
                  <td>{entry.reviewed_by || 'Pending'}</td>
                  <td><span className={`badge-status ${entry.status === 'reviewed' ? 'badge-active' : 'badge-completed'}`}>{entry.status.charAt(0).toUpperCase() + entry.status.slice(1)}</span></td>
                  <td>
                    {entry.status !== 'reviewed' ? (
                      <>
                        <button className="btn-outline-green btn-sm me-1" onClick={() => startEdit(entry)}>Edit</button>
                        <button className="btn-outline-green btn-sm" onClick={() => handleDelete(entry)}>Delete</button>
                      </>
                    ) : (
                      <span className="table-secondary-text">Locked</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <footer className="app-footer">&copy; 2024-2025 INTERNTRACK <span>AY 2024-2025 | 50m2</span></footer>
    </main>
  );
}

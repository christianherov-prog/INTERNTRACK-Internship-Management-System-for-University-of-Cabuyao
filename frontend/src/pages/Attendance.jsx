import { useCallback, useEffect, useMemo, useState } from 'react';
import client from '../api/client';
import LoadingCard from '../components/LoadingCard.jsx';

const STATUS_BADGES = {
  validated: 'badge-active',
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

function formatTime(value) {
  if (!value) return '—';
  const [h, m] = value.split(':');
  const hour = parseInt(h, 10);
  const suffix = hour >= 12 ? 'PM' : 'AM';
  const display = hour % 12 === 0 ? 12 : hour % 12;
  return `${display}:${m} ${suffix}`;
}

function isoWeekKey(dateStr) {
  const date = new Date(dateStr);
  const day = (date.getDay() + 6) % 7;
  const monday = new Date(date);
  monday.setDate(date.getDate() - day);
  return monday.toISOString().slice(0, 10);
}

const EMPTY_FORM = { date: '', time_in: '', time_out: '', remarks: '' };

export default function Attendance() {
  const [records, setRecords] = useState([]);
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
      const { data } = await client.get('/student/attendance');
      setRecords(data.attendance);
      setStats(data.stats);
      setError('');
    } catch {
      setError('Unable to load attendance records.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const weeklySummary = useMemo(() => {
    const groups = new Map();
    for (const rec of records) {
      const key = isoWeekKey(rec.date);
      groups.set(key, (groups.get(key) || 0) + Number(rec.hours));
    }
    return [...groups.entries()]
      .sort((a, b) => (a[0] < b[0] ? 1 : -1))
      .slice(0, 8)
      .map(([weekStart, hours], idx, arr) => ({
        label: `Week of ${formatDate(weekStart)}`,
        hours: Math.round(hours * 100) / 100,
        featured: idx === 0,
        compact: idx >= 3,
        percent: Math.min(100, Math.round((hours / 40) * 100)),
      }));
  }, [records]);

  const setField = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  const resetForm = () => {
    setForm(EMPTY_FORM);
    setEditingId(null);
    setFormError('');
  };

  const startEdit = (rec) => {
    setEditingId(rec.id);
    setForm({
      date: rec.date.slice(0, 10),
      time_in: rec.time_in.slice(0, 5),
      time_out: rec.time_out ? rec.time_out.slice(0, 5) : '',
      remarks: rec.remarks || '',
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
        date: form.date,
        time_in: form.time_in,
        time_out: form.time_out || null,
        remarks: form.remarks || null,
      };
      if (editingId) {
        await client.put(`/student/attendance/${editingId}`, payload);
        setNotice('Attendance record updated.');
      } else {
        await client.post('/student/attendance', payload);
        setNotice('Attendance logged successfully.');
      }
      resetForm();
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setFormError(
        errors ? Object.values(errors)[0][0] : err.response?.data?.message || 'Unable to save attendance.'
      );
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (rec) => {
    if (!window.confirm(`Delete attendance for ${formatDate(rec.date)}?`)) return;
    try {
      await client.delete(`/student/attendance/${rec.id}`);
      if (editingId === rec.id) resetForm();
      await load();
    } catch (err) {
      setNotice('');
      setError(err.response?.data?.message || 'Unable to delete this record.');
    }
  };

  if (loading) {
    return (
      <main className="main-content">
        <LoadingCard label="Loading attendance..." />
      </main>
    );
  }

  return (
    <main className="main-content">
      {error && <div className="alert-interntrack mb-3"><i className="fa fa-circle-info me-2"></i>{error}</div>}

      <div className="row g-3 mb-4">
        <div className="col-sm-6 col-xl-3"><div className="stat-card"><div className="stat-icon green"><i className="fa fa-calendar-check"></i></div><div><div className="stat-value">{stats?.validated_days ?? 0}</div><div className="stat-label">Validated Days</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-022"><div className="stat-icon teal"><i className="fa fa-business-time"></i></div><div><div className="stat-value">{stats?.hours_logged ?? 0}</div><div className="stat-label">Hours Logged</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-026"><div className="stat-icon amber"><i className="fa fa-file-circle-check"></i></div><div><div className="stat-value">{stats?.days_present ?? 0}</div><div className="stat-label">Days Present</div></div></div></div>
        <div className="col-sm-6 col-xl-3"><div className="stat-card it-inline-023"><div className="stat-icon blue"><i className="fa fa-hourglass-half"></i></div><div><div className="stat-value">{stats?.hours_remaining ?? 0}</div><div className="stat-label">Hours Remaining</div></div></div></div>
      </div>

      <div className="row g-3 mb-4">
        <div className="col-lg-5">
          <div className="content-card h-100">
            <div className="content-card-header"><i className="fa fa-upload"></i><h6>{editingId ? 'Edit Attendance Log' : 'Log Daily Attendance'}</h6></div>
            <form onSubmit={handleSubmit}>
              <div className="row g-3">
                <div className="col-md-6"><label className="form-label form-label-subtle">Date</label><input className="form-control" type="date" value={form.date} onChange={setField('date')} required /></div>
                <div className="col-md-3"><label className="form-label form-label-subtle">Time In</label><input className="form-control" type="time" value={form.time_in} onChange={setField('time_in')} required /></div>
                <div className="col-md-3"><label className="form-label form-label-subtle">Time Out</label><input className="form-control" type="time" value={form.time_out} onChange={setField('time_out')} /></div>
                <div className="col-12"><label className="form-label form-label-subtle">Supervisor Note</label><textarea className="form-control" rows="4" placeholder="Optional remarks for coordinator review..." value={form.remarks} onChange={setField('remarks')}></textarea></div>
                <div className="col-12 d-flex gap-2">
                  <button className="btn-green" type="submit" disabled={busy}>
                    <i className="fa fa-paper-plane me-1"></i>{busy ? 'Saving...' : editingId ? 'Update Log' : 'Submit Log'}
                  </button>
                  {editingId && (
                    <button className="btn-outline-green" type="button" onClick={resetForm}>Cancel</button>
                  )}
                </div>
              </div>
            </form>
            {formError && <div className="alert-interntrack mt-3"><i className="fa fa-circle-info me-2"></i>{formError}</div>}
            {notice && !formError && <div className="alert-interntrack mt-3"><i className="fa fa-circle-check me-2"></i>{notice}</div>}
          </div>
        </div>
        <div className="col-lg-7">
          <div className="content-card h-100 attendance-summary-card">
            <div className="content-card-header"><i className="fa fa-clock-rotate-left"></i><h6>Weekly Attendance Summary</h6></div>
            <div className="attendance-summary-stack">
              {weeklySummary.length === 0 ? (
                <div className="alert-interntrack attendance-summary-alert"><i className="fa fa-circle-info me-2"></i>No attendance logged yet. Your weekly totals will appear here.</div>
              ) : (
                <div className="attendance-week-list">
                  {weeklySummary.map((week) => (
                    <div
                      key={week.label}
                      className={`attendance-week-item${week.featured ? ' attendance-week-item-featured' : ''}${week.compact ? ' attendance-week-item-compact' : ''}`}
                    >
                      <div className="d-flex justify-content-between mb-1 attendance-meta"><span>{week.label}</span><strong>{week.hours} / 40 hrs</strong></div>
                      <div className="progress-track"><div className="progress-fill" style={{ width: `${week.percent}%` }}></div></div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-table-list"></i><h6>Attendance History</h6></div>
        <div className="table-card">
          <table className="table table-hover">
            <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              {records.length === 0 && (
                <tr><td colSpan="6" className="text-center">No attendance records yet.</td></tr>
              )}
              {records.map((rec) => (
                <tr key={rec.id}>
                  <td>{formatDate(rec.date)}</td>
                  <td>{formatTime(rec.time_in)}</td>
                  <td>{formatTime(rec.time_out)}</td>
                  <td>{rec.hours} hrs</td>
                  <td><span className={`badge-status ${STATUS_BADGES[rec.status] || 'badge-pending'}`}>{rec.status.charAt(0).toUpperCase() + rec.status.slice(1)}</span></td>
                  <td>
                    <button className="btn-outline-green btn-sm me-1" onClick={() => startEdit(rec)}>Edit</button>
                    <button className="btn-outline-green btn-sm" onClick={() => handleDelete(rec)}>Delete</button>
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

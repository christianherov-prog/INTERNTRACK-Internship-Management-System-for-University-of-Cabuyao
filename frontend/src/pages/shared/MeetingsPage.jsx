import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'

const TYPES = [
  { value: 'orientation', label: 'Orientation' },
  { value: 'check_in', label: 'Check-in' },
  { value: 'defense_prep', label: 'Defense prep' },
  { value: 'other', label: 'Other' },
]

function MeetingsPage({ bodyClass = '', canCreate = false }) {
  const { user } = useAuth()
  const [meetings, setMeetings] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [form, setForm] = useState({
    title: '',
    type: 'orientation',
    description: '',
    starts_at: '',
    ends_at: '',
    location: '',
    meeting_url: '',
    internship_id: '',
  })
  const [saving, setSaving] = useState(false)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/meetings')
      .then((res) => setMeetings(res.data.meetings ?? []))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load meetings.')
        setMeetings([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const create = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    try {
      await api.post('/meetings', {
        ...form,
        internship_id: form.internship_id ? Number(form.internship_id) : null,
        ends_at: form.ends_at || null,
        meeting_url: form.meeting_url || null,
        location: form.location || null,
      })
      setMessage({ type: 'success', text: 'Meeting scheduled.' })
      setForm({
        title: '', type: 'orientation', description: '', starts_at: '', ends_at: '',
        location: '', meeting_url: '', internship_id: '',
      })
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Failed to create meeting.' })
    } finally {
      setSaving(false)
    }
  }

  const rsvp = async (id, value) => {
    try {
      await api.patch(`/meetings/${id}/rsvp`, { rsvp: value })
      load()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'RSVP failed.' })
    }
  }

  return (
    <Layout title="Meetings" subtitle="Orientation & check-ins" icon="fa-calendar" bodyClass={bodyClass}>
      {error && <PageError message={error} onRetry={load} />}
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible`}>
          {message.text}
          <button type="button" className="btn-close" onClick={() => setMessage(null)} />
        </div>
      )}

      {canCreate && (
        <div className="content-card mb-4">
          <div className="content-card-header"><i className="fa fa-plus"></i><h6>Schedule meeting</h6></div>
          <form className="p-3" onSubmit={create}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label">Title</label>
                <input className="form-control" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
              </div>
              <div className="col-md-3">
                <label className="form-label">Type</label>
                <select className="form-select" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                  {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
              </div>
              <div className="col-md-3">
                <label className="form-label">Internship ID (optional)</label>
                <input className="form-control" value={form.internship_id} onChange={(e) => setForm({ ...form, internship_id: e.target.value })} placeholder="Auto-invite parties" />
              </div>
              <div className="col-md-4">
                <label className="form-label">Starts</label>
                <input type="datetime-local" className="form-control" required value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Ends</label>
                <input type="datetime-local" className="form-control" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Location</label>
                <input className="form-control" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} />
              </div>
              <div className="col-md-6">
                <label className="form-label">Meeting URL</label>
                <input className="form-control" value={form.meeting_url} onChange={(e) => setForm({ ...form, meeting_url: e.target.value })} placeholder="https://…" />
              </div>
              <div className="col-md-6">
                <label className="form-label">Description</label>
                <input className="form-control" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
              </div>
            </div>
            <button className="btn btn-primary mt-3" disabled={saving}>
              {saving ? 'Saving…' : 'Create meeting'}
            </button>
          </form>
        </div>
      )}

      <div className="content-card">
        <div className="content-card-header"><i className="fa fa-calendar-days"></i><h6>Upcoming</h6></div>
        {loading ? (
          <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
        ) : meetings.length === 0 ? (
          <EmptyState icon="fa-calendar" title="No meetings" message="Scheduled orientations and check-ins will appear here." />
        ) : (
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Title</th>
                  <th>Type</th>
                  <th>Location</th>
                  <th>Your RSVP</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {meetings.map((m) => (
                  <tr key={m.id}>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {m.starts_at ? new Date(m.starts_at).toLocaleString() : '—'}
                    </td>
                    <td>
                      <div className="fw-semibold">{m.title}</div>
                      {m.description && <small className="text-muted">{m.description}</small>}
                    </td>
                    <td><span className="badge bg-secondary text-capitalize">{m.type?.replace('_', ' ')}</span></td>
                    <td>
                      {m.location || '—'}
                      {m.meeting_url && (
                        <div><a href={m.meeting_url} target="_blank" rel="noreferrer">Join link</a></div>
                      )}
                    </td>
                    <td className="text-capitalize">{m.my_rsvp || '—'}</td>
                    <td>
                      {m.my_rsvp && (
                        <div className="btn-group btn-group-sm">
                          {['accepted', 'maybe', 'declined'].map((v) => (
                            <button
                              key={v}
                              type="button"
                              className={`btn btn-outline-primary ${m.my_rsvp === v ? 'active' : ''}`}
                              onClick={() => rsvp(m.id, v)}
                            >
                              {v}
                            </button>
                          ))}
                        </div>
                      )}
                      {!m.my_rsvp && user?.id === m.created_by && <span className="text-muted small">Organizer</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </Layout>
  )
}

export default MeetingsPage

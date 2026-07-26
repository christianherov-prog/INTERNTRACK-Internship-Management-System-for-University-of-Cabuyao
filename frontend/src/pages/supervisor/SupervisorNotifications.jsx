import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'

function timeAgo(iso) {
  if (!iso) return '—'
  const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function SupervisorNotifications() {
  const navigate = useNavigate()
  const [items, setItems] = useState([])
  const [unread, setUnread] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/notifications')
      .then((res) => {
        setItems(unwrapList(res.data).items)
        setUnread(res.data.unread_count ?? 0)
      })
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load notifications.')
        setItems([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const markAllRead = async () => {
    try {
      await api.post('/notifications/mark-read')
      setUnread(0)
      setItems((prev) => prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() })))
    } catch {
      setError('Failed to mark notifications as read.')
    }
  }

  const handleNotifClick = (n) => {
    if (!n.read_at) {
      api.post(`/notifications/${n.id}/read`).catch(() => {})
      setItems(prev => prev.map(item => item.id === n.id ? { ...item, read_at: new Date().toISOString() } : item))
      setUnread(u => Math.max(0, u - 1))
    }
    if (n.link) {
      if (n.link.startsWith('http://') || n.link.startsWith('https://')) {
        window.location.href = n.link
      } else {
        navigate(n.link)
      }
    }
  }

  const getIcon = (type) => {
    const map = {
      document_approved: 'fa-circle-check text-success',
      document_coordinator_approved: 'fa-circle-check text-success',
      document_rejected: 'fa-circle-xmark text-danger',
      document_pending_faculty: 'fa-file-signature text-warning',
      journal_reviewed:  'fa-book text-primary',
      supervisor_feedback: 'fa-comment-dots text-info',
      supervisor_feedback_submitted: 'fa-comment-dots text-info',
      supervisor_evaluation_submitted: 'fa-clipboard-check text-success',
      meeting_invite: 'fa-calendar-plus text-primary',
      meeting_updated: 'fa-calendar-check text-info',
      placement_assigned: 'fa-briefcase text-success',
      new_message:       'fa-comments text-primary',
    }
    return map[type] ?? 'fa-bell text-secondary'
  }

  return (
    <Layout title="Notifications" subtitle="Supervisor" icon="fa-bell" bodyClass="supervisor-page">
      {error && <PageError message={error} onRetry={load} />}

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-bell"></i>
          <h6>Notifications</h6>
          <div className="ms-auto d-flex align-items-center gap-2">
            {unread > 0 && <span className="badge bg-danger">{unread} unread</span>}
            <button type="button" className="btn btn-sm btn-outline-success" onClick={markAllRead} disabled={unread === 0}>
              Mark all read
            </button>
          </div>
        </div>
        <div className="table-card">
          {loading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
          ) : items.length === 0 ? (
            <div className="text-center text-muted py-5">
              <i className="fa fa-bell-slash fa-2x mb-2 d-block text-secondary opacity-50"></i>
              <span className="fw-medium text-dark d-block mb-1">No notifications yet</span>
              <span className="text-muted d-block mb-3" style={{ fontSize: '0.85rem' }}>When you receive system updates or feedback, they will appear here.</span>
              <div>
                <Link to="/supervisor/dashboard" className="btn btn-sm btn-outline-success">Back to dashboard</Link>
              </div>
            </div>
          ) : (
            <div className="list-group list-group-flush">
              {[...items].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).map((n) => (
                <div
                  key={n.id}
                  onClick={() => handleNotifClick(n)}
                  className="list-group-item d-flex align-items-start gap-3 px-4 py-3 transition-all"
                  style={{
                    background: n.read_at ? '#ffffff' : '#f0fdf4',
                    borderLeft: n.read_at ? '4px solid transparent' : '4px solid #16a34a',
                    cursor: 'pointer'
                  }}
                >
                  <i className={`fa ${getIcon(n.type)} mt-1`} style={{ fontSize: '1.2rem', minWidth: '22px' }}></i>
                  <div className="flex-grow-1">
                    <div className={n.read_at ? "fw-medium text-secondary" : "fw-bold text-dark"} style={{ fontSize: '0.92rem' }}>
                      {n.title || n.type || 'Notification'}
                    </div>
                    <div className="text-muted mt-1" style={{ fontSize: '0.85rem', lineHeight: 1.4 }}>{n.message || n.body || ''}</div>
                  </div>
                  <div className="text-end flex-shrink-0">
                    <small className="text-muted d-block">{timeAgo(n.created_at)}</small>
                    {!n.read_at && <span className="badge bg-success mt-1" style={{ fontSize: '0.65rem' }}>New</span>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

export default SupervisorNotifications

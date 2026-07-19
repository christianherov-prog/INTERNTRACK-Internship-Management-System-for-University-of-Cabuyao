import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
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
              <i className="fa fa-inbox fa-2x mb-2 d-block"></i>
              No notifications yet.
              <div className="mt-3">
                <Link to="/supervisor/dashboard" className="btn btn-sm btn-outline-success">Back to dashboard</Link>
              </div>
            </div>
          ) : (
            <div className="list-group list-group-flush">
              {items.map((n) => (
                <div
                  key={n.id}
                  className={`list-group-item d-flex align-items-start gap-3 px-4 py-3 ${n.read_at ? '' : 'bg-light'}`}
                >
                  <div className="flex-grow-1">
                    <div className="fw-semibold" style={{ fontSize: '0.9rem' }}>{n.title || n.type || 'Notification'}</div>
                    <div className="text-muted" style={{ fontSize: '0.85rem' }}>{n.message || n.body || ''}</div>
                  </div>
                  <small className="text-muted flex-shrink-0">{timeAgo(n.created_at)}</small>
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

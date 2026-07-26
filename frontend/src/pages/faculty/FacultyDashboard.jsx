import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import RoleSummaryPanel from '../../components/RoleSummaryPanel'
import QuickActionsPanel from '../../components/QuickActionsPanel'
import PageError from '../../components/PageError'
import { AnnouncementAttachmentView } from '../../components/AnnouncementAttachment'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'

const FACULTY_QUICK_ACTIONS = [
  {
    to: '/faculty/assigned-students',
    title: 'View Assigned Students',
    description: 'See progress and status of all your students',
    icon: 'fa-users',
    tone: 'blue',
  },
  {
    to: '/faculty/journals',
    title: 'Review Weekly Journals',
    description: 'Read and approve student narrative reports',
    icon: 'fa-book-open',
    tone: 'green',
  },
  {
    to: '/faculty/evaluations',
    title: 'Submit Evaluations',
    description: 'Midterm and final performance evaluations',
    icon: 'fa-star',
    tone: 'amber',
  },
  {
    to: '/faculty/feedback',
    title: 'Give Feedback',
    description: 'Send remarks and guidance to students',
    icon: 'fa-comment-dots',
    tone: 'gray',
  },
]

function FacultyDashboard() {
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/faculty/dashboard')
      .then(res => setData(res.data))
      .catch((err) => {
        setError(err.response?.data?.message || 'Failed to load faculty dashboard.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const activity = data?.recent_activity ?? []
  const announcements = data?.announcements ?? []

  const timeAgo = (iso) => {
    if (!iso) return '—'
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
    if (diff < 60)    return `${diff}s ago`
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    return `${Math.floor(diff / 86400)}d ago`
  }

  return (
    <Layout title="Faculty Dashboard" subtitle={CURRENT_TERM} icon="fa-chalkboard-teacher" bodyClass="faculty-page">
      <RoleSummaryPanel />
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row">
            <div className="col-lg-6 mb-4">
              <QuickActionsPanel actions={FACULTY_QUICK_ACTIONS} />
            </div>

            {/* Recent Activity Panel */}
            <div className="col-lg-6 mb-4">
              <div className="content-card h-100">
                <div className="content-card-header bg-light">
                  <i className="fa fa-clock-rotate-left"></i>
                  <h6 className="mb-0">Recent Activity</h6>
                </div>
                <div className="p-0">
                  {activity.length === 0 ? (
                    <div className="text-center text-muted py-5">
                      <i className="fa fa-inbox fa-2x mb-2 d-block"></i>
                      <p className="mb-0">No recent activity yet.</p>
                    </div>
                  ) : (
                    <div className="list-group list-group-flush">
                      {activity.map((a, idx) => (
                        <div key={idx} className="list-group-item d-flex align-items-start gap-3 px-4 py-3">
                          <div
                            className={`rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ${a.action === 'approved' ? 'bg-success' : 'bg-warning'} text-white`}
                            style={{ width: '32px', height: '32px', fontSize: '0.8rem' }}
                          >
                            <i className={`fa ${a.action === 'approved' ? 'fa-check' : 'fa-rotate'}`}></i>
                          </div>
                          <div className="flex-grow-1">
                            <div className="fw-semibold" style={{ fontSize: '0.88rem' }}>
                              {a.student} — Week {a.week}
                            </div>
                            <div className="text-muted" style={{ fontSize: '0.8rem' }}>
                              Journal {a.action === 'approved' ? 'approved' : 'sent back for revision'}
                            </div>
                          </div>
                          <small className="text-muted flex-shrink-0">{timeAgo(a.action_at)}</small>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="row">
            <div className="col-12 mb-4">
              <div className="content-card">
                <div className="content-card-header bg-light">
                  <i className="fa fa-bullhorn"></i>
                  <h6 className="mb-0">Announcements</h6>
                </div>
                <div className="px-3 pb-3">
                  {announcements.length > 0 ? (
                    announcements.slice(0, 5).map((a) => (
                      <div key={a.id} className="announcement-item">
                        <div className="announcement-icon">
                          <i className={`fa ${a.is_pinned ? 'fa-thumbtack' : 'fa-info-circle'}`}></i>
                        </div>
                        <div className="announcement-content">
                          <div className="announcement-title">
                            {a.title}
                            {a.category === 'policy_update' && (
                              <span className="badge bg-danger ms-2" style={{ fontSize: '0.65rem', verticalAlign: 'middle' }}>Policy Update</span>
                            )}
                          </div>
                          <div className="announcement-text">{a.content}</div>
                          {a.attachment && (
                            <div className="ann-attach-block">
                              <AnnouncementAttachmentView attachment={a.attachment} />
                            </div>
                          )}
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="text-center py-4 text-muted">
                      <i className="fa fa-inbox fa-2x mb-2"></i>
                      <p className="mb-0">No announcements at this time</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </Layout>
  )
}

export default FacultyDashboard

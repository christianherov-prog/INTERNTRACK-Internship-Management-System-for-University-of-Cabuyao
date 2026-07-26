import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'

function FacultyStudentProgress() {
  const { userId } = useParams()
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)
  const [generating, setGenerating] = useState(null)

  useEffect(() => {
    setLoading(true)
    api.get(`/faculty/students/${userId}/progress`)
      .then(res => setData(res.data))
      .catch(err => setError(err.response?.data?.message || 'Failed to load student progress.'))
      .finally(() => setLoading(false))
  }, [userId])

  const generateJournalPdf = async (weekNumber) => {
    setGenerating(weekNumber)
    try {
      const resp = await api.get('/faculty/journal/generate', {
        params: { internship_id: data.internship.id, week_number: weekNumber },
        responseType: 'blob',
      })
      const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href  = url
      link.download = `Journal_${data.student.name}_Week${weekNumber}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch {
      alert('Failed to generate journal PDF.')
    } finally {
      setGenerating(null)
    }
  }

  const generateDtrPdf = async () => {
    const month = new Date().toISOString().slice(0, 7)
    setGenerating('dtr')
    try {
      const resp = await api.get('/faculty/dtr/generate', {
        params: { internship_id: data.internship.id, month },
        responseType: 'blob',
      })
      const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href  = url
      link.download = `DTR_${data.student.name}_${month}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch {
      alert('Failed to generate DTR PDF.')
    } finally {
      setGenerating(null)
    }
  }

  const progressColor = (pct) => {
    if (pct >= 75) return '#14b8a6'
    if (pct >= 40) return '#f59e0b'
    return '#ef4444'
  }

  const docStatusBadge = (status) => {
    const map = { approved: 'badge-active', rejected: 'badge-inactive', pending: 'badge-pending', submitted: 'badge-pending' }
    return <span className={`badge-status ${map[status] ?? 'badge-pending'}`}>{status || 'pending'}</span>
  }

  const journalStatusBadge = (status) => {
    const map = { approved: 'badge-active', needs_revision: 'badge-inactive', submitted: 'badge-pending' }
    return <span className={`badge-status ${map[status] ?? 'badge-pending'}`}>{(status || '—').replace(/_/g, ' ')}</span>
  }

  if (loading) return (
    <Layout title="Student Progress" icon="fa-chart-line" bodyClass="faculty-page">
      <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
    </Layout>
  )

  if (error) return (
    <Layout title="Student Progress" icon="fa-chart-line" bodyClass="faculty-page">
      <PageError message={error} />
    </Layout>
  )

  const { student, internship, progress, documents, journals } = data

  return (
    <Layout
      title={student?.name || 'Student Progress'}
      subtitle={`${student?.program || ''} — ${student?.section || ''}`}
      icon="fa-chart-line"
      bodyClass="faculty-page"
    >
      {/* Back link */}
      <Link to="/faculty/students" className="btn btn-sm btn-outline-secondary mb-3">
        <i className="fa fa-arrow-left me-2"></i>Back to Students
      </Link>

      {/* ── Top Info Cards ── */}
      <div className="row g-3 mb-4">
        <div className="col-md-4">
          <div className="stat-card">
            <div className="stat-icon blue"><i className="fa fa-building"></i></div>
            <div>
              <div className="stat-label">Company</div>
              <div className="stat-value" style={{ fontSize: '1rem' }}>
                {internship?.company || <span className="text-muted fst-italic">Not yet placed</span>}
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-4">
          <div className="stat-card">
            <div className="stat-icon green"><i className="fa fa-user-tie"></i></div>
            <div>
              <div className="stat-label">Supervisor</div>
              <div className="stat-value" style={{ fontSize: '1rem' }}>
                {internship?.supervisor || <span className="text-muted fst-italic">Not assigned</span>}
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-4">
          <div className="stat-card">
            <div className="stat-icon teal"><i className="fa fa-id-card"></i></div>
            <div>
              <div className="stat-label">Student No.</div>
              <div className="stat-value" style={{ fontSize: '1rem' }}>{student?.student_number || '—'}</div>
            </div>
          </div>
        </div>
      </div>

      {/* ── Hours Progress ── */}
      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-clock"></i>
          <h6>OJT Hours Progress</h6>
          <button
            className="btn btn-sm btn-outline-danger ms-auto"
            onClick={generateDtrPdf}
            disabled={generating === 'dtr'}
          >
            <i className={`fa fa-${generating === 'dtr' ? 'spinner fa-spin' : 'file-pdf'} me-1`}></i>
            Download DTR (FO-30)
          </button>
        </div>
        <div className="p-4">
          <div className="d-flex justify-content-between mb-2">
            <span className="fw-semibold">{progress?.hours_rendered ?? 0} / {progress?.target_hours ?? 0} hours</span>
            <span className="fw-bold" style={{ color: progressColor(progress?.progress_pct ?? 0) }}>
              {progress?.progress_pct ?? 0}%
            </span>
          </div>
          <div className="progress mb-1" style={{ height: '16px', borderRadius: 8 }}>
            <div
              className="progress-bar"
              role="progressbar"
              style={{
                width: `${progress?.progress_pct ?? 0}%`,
                background: progressColor(progress?.progress_pct ?? 0),
                borderRadius: 8,
              }}
            ></div>
          </div>
          <small className="text-muted">
            {(progress?.target_hours ?? 0) - (progress?.hours_rendered ?? 0) > 0
              ? `${(progress?.target_hours ?? 0) - (progress?.hours_rendered ?? 0)} hours remaining`
              : 'OJT hours requirement completed!'}
          </small>
        </div>
      </div>

      <div className="row g-4">
        {/* ── Documents Checklist ── */}
        <div className="col-md-5">
          <div className="content-card h-100">
            <div className="content-card-header">
              <i className="fa fa-file-alt"></i>
              <h6>Documents</h6>
              <span className="ms-auto badge bg-secondary">{documents?.approved}/{documents?.total} approved</span>
            </div>
            <div className="table-card">
              {documents?.items?.length === 0 ? (
                <div className="text-center py-4 text-muted">No documents submitted yet.</div>
              ) : documents?.items?.map(doc => (
                <div key={doc.id} className="d-flex justify-content-between align-items-center p-2 border-bottom">
                  <span style={{ fontSize: '0.88rem' }}>{doc.name}</span>
                  {docStatusBadge(doc.status)}
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* ── Journal Entries ── */}
        <div className="col-md-7">
          <div className="content-card h-100">
            <div className="content-card-header">
              <i className="fa fa-book-open"></i>
              <h6>Weekly Journals</h6>
              <span className="ms-auto badge bg-secondary">{journals?.count} entries</span>
            </div>
            <div className="table-card">
              {journals?.items?.length === 0 ? (
                <div className="text-center py-4 text-muted">No journal entries yet.</div>
              ) : journals?.items?.map(j => (
                <div key={j.id} className="p-3 border-bottom">
                  <div className="d-flex justify-content-between align-items-start">
                    <div style={{ flex: 1 }}>
                      <div className="fw-semibold mb-1">
                        <i className="fa fa-calendar-week me-2 text-primary"></i>
                        Week {j.week}
                        {j.date && <span className="text-muted fw-normal ms-2" style={{ fontSize: '0.82rem' }}>
                          — {new Date(j.date).toLocaleDateString()}
                        </span>}
                      </div>
                      {j.accomplishment && (
                        <p className="mb-1 text-truncate text-muted" style={{ fontSize: '0.82rem', maxWidth: 320 }}>
                          <strong className="text-success">Accomplishment:</strong> {j.accomplishment}
                        </p>
                      )}
                    </div>
                    <div className="d-flex flex-column align-items-end gap-1">
                      {journalStatusBadge(j.status)}
                      <button
                        className="btn btn-xs btn-outline-danger"
                        style={{ fontSize: '0.78rem', padding: '2px 8px' }}
                        onClick={() => generateJournalPdf(j.week)}
                        disabled={generating === j.week}
                      >
                        <i className={`fa fa-${generating === j.week ? 'spinner fa-spin' : 'file-pdf'} me-1`}></i>
                        FO-31
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  )
}

export default FacultyStudentProgress

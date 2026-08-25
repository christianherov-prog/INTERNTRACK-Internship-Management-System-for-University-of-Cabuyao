import { useState, useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import Chart from 'chart.js/auto'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import DashboardHeroBanner from '../../components/DashboardHeroBanner'
import { AnnouncementAttachmentView } from '../../components/AnnouncementAttachment'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import { DEFAULT_TARGET_HOURS } from '../../config/hours'
import { formatYearSection } from '../../utils/formatSection'

function StudentDashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [certData, setCertData] = useState(null)
  const chartRef = useRef(null)
  const chartInstance = useRef(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/student/dashboard')
      .then(res => setData(res.data))
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to load dashboard.')
        setData(null)
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  // Fetch certificate eligibility whenever internship status changes
  useEffect(() => {
    if (!data) return
    api.get('/student/certificate/eligibility')
      .then(res => setCertData(res.data))
      .catch(() => setCertData(null))
  }, [data?.internship?.status])

  // Render Chart.js weekly hours bar chart
  useEffect(() => {
    if (!data?.weekly_chart || !chartRef.current) return

    if (chartInstance.current) chartInstance.current.destroy()

    const { labels, hours } = data.weekly_chart
    chartInstance.current = new Chart(chartRef.current, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Hours Logged', data: hours, backgroundColor: 'rgba(20,184,166,0.75)', borderRadius: 6 },
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: true },
          title: { display: true, text: 'Weekly hours logged (no fixed weekly quota)' },
        },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } } },
      }
    })
    return () => chartInstance.current?.destroy()
  }, [data])

  if (loading) return (
    <Layout title="Dashboard" subtitle="Loading…" icon="fa-gauge-high" bodyClass="student-page">
      <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
    </Layout>
  )

  if (error) return (
    <Layout title="Dashboard" subtitle="Student" icon="fa-gauge-high" bodyClass="student-page">
      <PageError message={error} onRetry={load} />
    </Layout>
  )

  const s = data?.stats ?? {}
  const internship = data?.internship
  const announcements = data?.announcements ?? []
  const student = data?.student

  return (
    <Layout title="Dashboard" subtitle="Student" icon="fa-gauge-high" bodyClass="student-page">

      <DashboardHeroBanner
        label="INTERNSHIP DASHBOARD"
        title={`Welcome back, ${student?.name ?? 'Student'}`}
        meta={[
          formatYearSection(student?.section, student?.year_level),
          `Student No. ${student?.student_number ?? '—'}`,
          internship?.company_name || 'No company assigned',
        ]}
        badges={[
          {
            text: internship?.status_label
              ? String(internship.status_label).toUpperCase()
              : internship?.status
                ? String(internship.status).replace(/_/g, ' ').toUpperCase()
                : 'NO ACTIVE INTERNSHIP',
            variant: internship?.status === 'completed' ? 'term' : 'ongoing',
          },
          { text: internship?.term ?? CURRENT_TERM, variant: 'term' },
        ]}
      />

      {internship?.status_reason && (
        <div className="alert alert-light border mb-3" style={{ fontSize: '0.88rem' }}>
          <strong>Status note:</strong> {internship.status_reason}
        </div>
      )}



      {internship?.status === 'completed' && (
        <div className="mb-3">
          {certData?.eligible ? (
            <div>
              <button
                type="button"
                className="btn btn-success me-2"
                onClick={async () => {
                  try {
                    const res = await api.get('/student/certificates/completion', { responseType: 'blob' })
                    const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
                    const a = document.createElement('a')
                    a.href = url
                    a.download = 'completion-certificate.pdf'
                    a.click()
                    window.URL.revokeObjectURL(url)
                  } catch {
                    alert('Certificate download failed. Please try again.')
                  }
                }}
              >
                <i className="fa fa-certificate me-2"></i>Download Completion Certificate
              </button>
              {certData?.issued_at && (
                <small className="text-muted ms-2">Last downloaded: {new Date(certData.issued_at).toLocaleDateString()}</small>
              )}
            </div>
          ) : (
            <div className="alert alert-warning" style={{fontSize:'0.9rem'}}>
              <div className="fw-semibold mb-2"><i className="fa fa-exclamation-triangle me-2"></i>Certificate Not Yet Available</div>
              <ul className="mb-0 ps-3">
                {(certData?.checklist || []).map((item, i) => (
                  <li key={i} style={{color: item.passed ? '#16a34a' : '#dc2626'}}>
                    <i className={`fa ${item.passed ? 'fa-check-circle' : 'fa-times-circle'} me-1`}></i>
                    {item.label}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {/* ── Stat Cards ── */}
      <div className="row g-3 mb-4">
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card">
            <div className="stat-icon teal"><i className="fa fa-clock"></i></div>
            <div>
              <div className="stat-value">{s.hours_rendered ?? 0}<span style={{ fontSize: '0.7em', fontWeight: 400 }}>/{s.target_hours ?? DEFAULT_TARGET_HOURS}</span></div>
              <div className="stat-label">Hours Rendered</div>
            </div>
          </div>
        </div>
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card">
            <div className="stat-icon green"><i className="fa fa-calendar-check"></i></div>
            <div>
              <div className="stat-value">{s.days_present ?? 0}</div>
              <div className="stat-label">Days Present</div>
            </div>
          </div>
        </div>
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card">
            <div className="stat-icon amber"><i className="fa fa-book"></i></div>
            <div>
              <div className="stat-value">{s.journal_count ?? 0}</div>
              <div className="stat-label">Journal Entries</div>
            </div>
          </div>
        </div>
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card">
            <div className="stat-icon blue"><i className="fa fa-file-alt"></i></div>
            <div>
              <div className="stat-value">{s.docs_submitted ?? 0}<span style={{ fontSize: '0.7em', fontWeight: 400 }}>/{s.docs_total ?? 13}</span></div>
              <div className="stat-label">Docs Submitted</div>
            </div>
          </div>
        </div>
      </div>


      <div className="row g-3 mb-4">
        {/* ── Quick Overview ── */}
        <div className="col-lg-6">
          <div className="content-card h-100">
            <div className="content-card-header">
              <i className="fa fa-chart-line"></i>
              <h6>Overall Progress Overview</h6>
            </div>
            <div className="px-3 pb-3 pt-2">
              {(() => {
                const targetHours = s.target_hours ?? DEFAULT_TARGET_HOURS
                const hoursRendered = s.hours_rendered ?? 0
                const hoursPct = targetHours > 0 ? Math.min(100, Math.max(0, Math.round((hoursRendered / targetHours) * 100))) : 0

                const docsTotal = s.docs_total ?? 13
                const docsSubmitted = s.docs_submitted ?? 0
                const docCompliance = Math.min(100, Math.max(0, s.doc_compliance ?? (docsTotal > 0 ? Math.round((docsSubmitted / docsTotal) * 100) : 0)))

                const evalScore = s.evaluation_score != null ? Math.min(100, Math.max(0, Math.round(s.evaluation_score))) : null
                
                // Calculate Overall Progress
                // Placement: 10%, Hours: 40%, Docs: 30%, Evaluation: 20%
                const isPlaced = !['unplaced', 'pending_placement'].includes(s.status)
                const placementScore = isPlaced ? 10 : 0
                const hoursScore = hoursPct * 0.40
                const docsScore = docCompliance * 0.30
                const evalScoreVal = evalScore != null ? evalScore * 0.20 : 0
                const overallProgress = Math.round(placementScore + hoursScore + docsScore + evalScoreVal)

                return (
                  <>
                    <div className="overall-progress-box mb-4 p-3 rounded" style={{ backgroundColor: '#f8fafc', border: '1px solid #e2e8f0' }}>
                      <div className="d-flex justify-content-between align-items-center mb-2">
                        <h6 className="mb-0 text-dark fw-bold">Overall Internship Progress</h6>
                        <span className="badge bg-primary fs-6">{overallProgress}%</span>
                      </div>
                      <div className="progress" style={{ height: '14px', borderRadius: '8px' }}>
                        <div
                          className="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                          style={{ width: `${overallProgress}%`, borderRadius: '8px' }}
                        ></div>
                      </div>
                      <div className="text-muted small mt-2 d-flex justify-content-between">
                        <span><i className={`fa fa-check-circle me-1 ${isPlaced ? 'text-success' : ''}`}></i>Placement (10%)</span>
                        <span><i className={`fa fa-check-circle me-1 ${hoursPct >= 100 ? 'text-success' : ''}`}></i>Hours (40%)</span>
                        <span><i className={`fa fa-check-circle me-1 ${docCompliance >= 100 ? 'text-success' : ''}`}></i>Docs (30%)</span>
                        <span><i className={`fa fa-check-circle me-1 ${evalScore != null ? 'text-success' : ''}`}></i>Eval (20%)</span>
                      </div>
                    </div>

                    <div className="overview-item mb-3">
                      <div className="overview-label">Completed Hours</div>
                      <div className="overview-value-row">
                        <span className="overview-value">{hoursRendered}/{targetHours} hrs</span>
                        <span className="overview-percent">{hoursPct}%</span>
                      </div>
                      <div className="progress" style={{ height: '10px', borderRadius: '6px', marginTop: '8px' }}>
                        <div
                          className="progress-bar"
                          style={{ width: `${hoursPct}%`, background: 'linear-gradient(90deg,#1a7a3f,#2da058)', borderRadius: '6px' }}
                        ></div>
                      </div>
                    </div>

                    <div className="overview-item mb-3">
                      <div className="overview-label">Document Compliance</div>
                      <div className="overview-value-row">
                        <span className="overview-value">{docsSubmitted} of {docsTotal} files</span>
                        <span className="overview-percent">{docCompliance}%</span>
                      </div>
                      <div className="progress" style={{ height: '10px', borderRadius: '6px', marginTop: '8px' }}>
                        <div
                          className="progress-bar"
                          style={{ width: `${docCompliance}%`, background: 'linear-gradient(90deg,#1a7a3f,#2da058)', borderRadius: '6px' }}
                        ></div>
                      </div>
                    </div>

                    <div className="overview-item">
                      <div className="overview-label">Evaluation Score</div>
                      <div className="overview-value-row">
                        <span className="overview-value">
                          {evalScore != null ? `${evalScore}%` : 'Pending Review'}
                        </span>
                        <span className="overview-percent">
                          {evalScore != null ? `${evalScore}%` : '—'}
                        </span>
                      </div>
                      <div className="progress" style={{ height: '10px', borderRadius: '6px', marginTop: '8px', background: '#e5e7eb' }}>
                        <div
                          className="progress-bar"
                          style={{ width: `${evalScore ?? 0}%`, background: 'linear-gradient(90deg,#1a7a3f,#2da058)', borderRadius: '6px' }}
                        ></div>
                      </div>
                    </div>
                  </>
                )
              })()}
            </div>
          </div>
        </div>

        {/* ── Announcements ── */}
        <div className="col-lg-6">
          <div className="content-card h-100">
            <div className="content-card-header">
              <i className="fa fa-bell"></i>
              <h6>Announcements</h6>
            </div>
            <div className="px-3 pb-3">
              {announcements.length > 0 ? (
                announcements.slice(0, 3).map(a => (
                  <div key={a.id} className="announcement-item">
                    <div className="announcement-icon">
                      <i className={`fa ${a.is_pinned ? 'fa-thumbtack' : a.title.includes('Deadline') ? 'fa-file-alt' : a.title.includes('Evaluation') ? 'fa-exclamation-triangle' : 'fa-info-circle'}`}></i>
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
    </Layout>
  )
}

export default StudentDashboard

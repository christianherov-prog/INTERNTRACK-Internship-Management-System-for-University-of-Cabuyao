import { useState, useEffect, useRef } from 'react'
import Layout from '../../components/Layout'
import DashboardHeroBanner from '../../components/DashboardHeroBanner'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import { DEFAULT_TARGET_HOURS } from '../../config/hours'

function StudentDashboard() {
  const [data, setData]     = useState(null)
  const [loading, setLoading] = useState(true)
  const chartRef            = useRef(null)
  const chartInstance       = useRef(null)

  useEffect(() => {
    api.get('/student/dashboard')
      .then(res => setData(res.data))
      .catch(console.error)
      .finally(() => setLoading(false))
  }, [])

  // Render Chart.js weekly hours bar chart
  useEffect(() => {
    if (!data?.weekly_chart || !chartRef.current) return
    if (typeof window.Chart === 'undefined') return

    if (chartInstance.current) chartInstance.current.destroy()

    const { labels, hours, target } = data.weekly_chart
    chartInstance.current = new window.Chart(chartRef.current, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Hours Rendered', data: hours,  backgroundColor: 'rgba(20,184,166,0.75)', borderRadius: 6 },
          { label: 'Target/Week',    data: target, backgroundColor: 'rgba(99,102,241,0.3)',  borderRadius: 6, type: 'line', borderColor: 'rgba(99,102,241,0.8)', borderWidth: 2, pointBackgroundColor: '#6366f1', fill: false },
        ]
      },
      options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } } } }
    })
    return () => chartInstance.current?.destroy()
  }, [data])

  if (loading) return (
    <Layout title="Dashboard" subtitle="Loading…" icon="fa-gauge-high" bodyClass="student-page">
      <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
    </Layout>
  )

  const s = data?.stats ?? {}
  const internship = data?.internship
  const announcements = data?.announcements ?? []
  const student = data?.student

  // Format a raw section code like "4ITD" into "4 IT - D" for display.
  const formatSection = (section) => {
    if (!section) return null
    const match = /^(\d+)([A-Za-z]+)([A-Za-z])$/.exec(section)
    if (!match) return section
    const [, year, program, letter] = match
    return `${year} ${program} - ${letter}`
  }

  return (
    <Layout title="Dashboard" subtitle="Student" icon="fa-gauge-high" bodyClass="student-page">

      <DashboardHeroBanner
        label="INTERNSHIP DASHBOARD"
        title={`Welcome back, ${student?.name ?? 'Student'}`}
        meta={[
          formatSection(student?.section),
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
          <button
            type="button"
            className="btn btn-success"
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
                alert('Certificate is available only after your internship status is set to Completed.')
              }
            }}
          >
            <i className="fa fa-certificate me-2"></i>Download Completion Certificate
          </button>
        </div>
      )}

      {/* ── Stat Cards ── */}
      <div className="row g-3 mb-4">
        <div className="col-sm-6 col-xl-3">
          <div className="stat-card">
            <div className="stat-icon teal"><i className="fa fa-clock"></i></div>
            <div>
              <div className="stat-value">{s.hours_rendered ?? 0}<span style={{fontSize:'0.7em',fontWeight:400}}>/{s.target_hours ?? DEFAULT_TARGET_HOURS}</span></div>
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
              <div className="stat-value">{s.docs_submitted ?? 0}<span style={{fontSize:'0.7em',fontWeight:400}}>/{s.docs_total ?? 9}</span></div>
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
              <h6>Quick Overview</h6>
            </div>
            <div className="px-3 pb-3">
              <div className="overview-item mb-3">
                <div className="overview-label">Completed Hours</div>
                <div className="overview-value-row">
                  <span className="overview-value">{s.hours_rendered ?? 0}/{s.target_hours ?? DEFAULT_TARGET_HOURS} hrs</span>
                  <span className="overview-percent">{Math.round(((s.hours_rendered ?? 0) / (s.target_hours ?? DEFAULT_TARGET_HOURS)) * 100)}%</span>
                </div>
                <div className="progress" style={{height:'10px',borderRadius:'6px',marginTop:'8px'}}>
                  <div
                    className="progress-bar"
                    style={{width:`${Math.round(((s.hours_rendered ?? 0) / (s.target_hours ?? DEFAULT_TARGET_HOURS)) * 100)}%`, background:'linear-gradient(90deg,#1a7a3f,#2da058)', borderRadius:'6px'}}
                  ></div>
                </div>
              </div>
              
              <div className="overview-item mb-3">
                <div className="overview-label">Document Compliance</div>
                <div className="overview-value-row">
                  <span className="overview-value">{s.docs_submitted ?? 0} of {s.docs_total ?? 9} files</span>
                  <span className="overview-percent">{s.doc_compliance ?? 0}%</span>
                </div>
                <div className="progress" style={{height:'10px',borderRadius:'6px',marginTop:'8px'}}>
                  <div
                    className="progress-bar"
                    style={{width:`${s.doc_compliance ?? 0}%`, background:'linear-gradient(90deg,#1a7a3f,#2da058)', borderRadius:'6px'}}
                  ></div>
                </div>
              </div>
              
              <div className="overview-item">
                <div className="overview-label">Evaluation Score</div>
                <div className="overview-value-row">
                  <span className="overview-value">Pending Review</span>
                  <span className="overview-percent">—</span>
                </div>
                <div className="progress" style={{height:'10px',borderRadius:'6px',marginTop:'8px',background:'#e5e7eb'}}>
                  <div
                    className="progress-bar"
                    style={{width:'0%', background:'linear-gradient(90deg,#1a7a3f,#2da058)', borderRadius:'6px'}}
                  ></div>
                </div>
              </div>
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
                      <div className="announcement-title">{a.title}</div>
                      <div className="announcement-text">{a.content}</div>
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

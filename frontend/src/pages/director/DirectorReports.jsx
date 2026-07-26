import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import { downloadCsv } from '../../utils/csv'

const REPORT_TYPES = [
  {
    key: 'internship-summary',
    title: 'Internship Summary',
    icon: 'fa-graduation-cap',
    color: 'green',
    desc: 'Interns by academic program — ongoing vs completed from live analytics.',
  },
  {
    key: 'company-partnerships',
    title: 'Company Partnerships',
    icon: 'fa-building',
    color: 'teal',
    desc: 'Top partner companies ranked by current intern placement count.',
  },
  {
    key: 'moa-status',
    title: 'MOA Status Report',
    icon: 'fa-file-signature',
    color: 'amber',
    desc: 'Counts of partner companies grouped by MOA status.',
  },
  {
    key: 'placement-trends',
    title: '3-Year Placement Trends',
    icon: 'fa-chart-line',
    color: 'blue',
    desc: 'Company placements aggregated across the last three academic years.',
  },
]

function formatGeneratedAt(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

function InternshipSummaryTable({ rows }) {
  if (!rows.length) {
    return <EmptyState icon="fa-graduation-cap" title="No program data" message="Internship summary will appear once placements exist." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle dir-reports-table">
        <thead className="table-light">
          <tr>
            <th>#</th>
            <th>Program</th>
            <th className="text-center">Ongoing</th>
            <th className="text-center">Completed</th>
            <th className="text-center">Total</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={r.Program} className={i % 2 === 1 ? 'dir-reports-row-alt' : undefined}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.Program}</td>
              <td className="text-center">{r.Ongoing}</td>
              <td className="text-center">{r.Completed}</td>
              <td className="text-center fw-semibold">{r.Total}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function CompanyPartnershipsTable({ rows }) {
  if (!rows.length) {
    return <EmptyState icon="fa-building" title="No company data" message="Partnerships appear once companies have placements." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle dir-reports-table">
        <thead className="table-light">
          <tr>
            <th>#</th>
            <th>Company</th>
            <th>Industry</th>
            <th>MOA Status</th>
            <th className="text-center">Interns</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={`${r.Company}-${i}`} className={i % 2 === 1 ? 'dir-reports-row-alt' : undefined}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.Company}</td>
              <td>{r.Industry}</td>
              <td>
                <span className="badge bg-secondary text-capitalize">{r['MOA Status']}</span>
              </td>
              <td className="text-center fw-semibold">{r.Interns}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function MoaStatusTable({ rows }) {
  if (!rows.length) {
    return <EmptyState icon="fa-file-signature" title="No MOA data" message="MOA status counts appear once partner companies are recorded." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle dir-reports-table">
        <thead className="table-light">
          <tr>
            <th>#</th>
            <th>Status</th>
            <th className="text-center">Count</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={r.Status} className={i % 2 === 1 ? 'dir-reports-row-alt' : undefined}>
              <td>{i + 1}</td>
              <td className="fw-semibold text-capitalize">{r.Status}</td>
              <td className="text-center fw-semibold">{r.Count}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function PlacementTrendsTable({ rows, years }) {
  if (!rows.length) {
    return <EmptyState icon="fa-chart-line" title="No placement trends" message="No placement data for the last three academic years." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle dir-reports-table">
        <thead className="table-light">
          <tr>
            <th>#</th>
            <th>Company</th>
            <th>Industry</th>
            {years.map((y) => (
              <th key={y} className="text-center">{y}</th>
            ))}
            <th className="text-center">Total</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={`${r.Company}-${i}`} className={i % 2 === 1 ? 'dir-reports-row-alt' : undefined}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.Company}</td>
              <td>{r.Industry}</td>
              {years.map((y) => (
                <td key={y} className="text-center">{r[y] ?? 0}</td>
              ))}
              <td className="text-center fw-semibold">{r.Total}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {years.length > 0 && (
        <p className="dir-reports-trends-note mb-0 mt-2">
          Aggregated via SQL GROUP BY company + academic year. Years: {years.join(', ')}.
        </p>
      )}
    </div>
  )
}

function buildInternshipSummaryRows(byProgram) {
  return (byProgram ?? []).map((p) => ({
    Program: p.program ?? 'Unknown',
    Ongoing: p.ongoing ?? 0,
    Completed: p.completed ?? 0,
    Total: p.count ?? 0,
  }))
}

function buildCompanyRows(topCompanies) {
  return (topCompanies ?? []).map((c) => ({
    Company: c.company_name,
    Industry: c.industry ?? '—',
    'MOA Status': c.moa_status ?? '—',
    Interns: c.internships_count ?? 0,
  }))
}

function buildMoaRows(moaByStatus) {
  return Object.entries(moaByStatus ?? {}).map(([status, count]) => ({
    Status: status,
    Count: count,
  }))
}

function buildTrendRows(trendCompanies, years) {
  return (trendCompanies ?? []).map((c) => {
    const row = {
      Company: c.company_name,
      Industry: c.industry ?? '—',
    }
    years.forEach((y) => { row[y] = c.years?.[y] ?? 0 })
    row.Total = c.total
    return row
  })
}

function DirectorReports() {
  const [stats, setStats] = useState({})
  const [overviewLoading, setOverviewLoading] = useState(true)
  const [overviewError, setOverviewError] = useState(null)

  const [activeReport, setActiveReport] = useState(null)
  const [reportRows, setReportRows] = useState([])
  const [trendYears, setTrendYears] = useState([])
  const [generatedAt, setGeneratedAt] = useState(null)
  const [reportLoading, setReportLoading] = useState(false)
  const [reportError, setReportError] = useState(null)

  const loadOverview = () => {
    setOverviewLoading(true)
    setOverviewError(null)
    api.get('/director/dashboard')
      .then((res) => {
        setStats(res.data?.stats ?? {})
      })
      .catch((err) => {
        setOverviewError(err.response?.data?.message || 'Failed to load overview.')
        setStats({})
      })
      .finally(() => setOverviewLoading(false))
  }

  useEffect(() => { loadOverview() }, [])

  const generateReport = async (key) => {
    setActiveReport(key)
    setReportLoading(true)
    setReportError(null)
    setReportRows([])
    setTrendYears([])
    setGeneratedAt(null)

    try {
      if (key === 'placement-trends') {
        const res = await api.get('/director/reports/placement-trends')
        const years = res.data?.academic_years ?? []
        const rows = buildTrendRows(res.data?.by_company ?? [], years)
        setTrendYears(years)
        setReportRows(rows)
      } else {
        const res = await api.get('/director/dashboard')
        const data = res.data ?? {}
        setStats(data.stats ?? {})

        if (key === 'internship-summary') {
          setReportRows(buildInternshipSummaryRows(data.by_program))
        } else if (key === 'company-partnerships') {
          setReportRows(buildCompanyRows(data.top_companies))
        } else if (key === 'moa-status') {
          setReportRows(buildMoaRows(data.moa_by_status))
        }
      }
      setGeneratedAt(formatGeneratedAt())
    } catch (err) {
      setReportError(err.response?.data?.message || 'Failed to generate report.')
      setReportRows([])
      setTrendYears([])
      setGeneratedAt(null)
    } finally {
      setReportLoading(false)
    }
  }

  const handleExportCsv = () => {
    if (!activeReport || !reportRows.length) return
    const meta = REPORT_TYPES.find((r) => r.key === activeReport)
    const filenames = {
      'internship-summary': 'internship-summary-by-program',
      'company-partnerships': 'company-partnerships',
      'moa-status': 'moa-status-summary',
      'placement-trends': 'placement-trends-3yr',
    }
    downloadCsv(filenames[activeReport] || meta?.title || 'director-report', reportRows)
  }

  const handlePrint = () => {
    window.print()
  }

  const activeMeta = REPORT_TYPES.find((r) => r.key === activeReport)

  return (
    <Layout title="Reports" subtitle={CURRENT_TERM} icon="fa-chart-bar" bodyClass="director-page dir-reports-page">
      {(overviewError || reportError) && (
        <PageError
          message={reportError || overviewError}
          onRetry={() => {
            if (activeReport) generateReport(activeReport)
            else loadOverview()
          }}
        />
      )}

      <div className="dir-reports">
        <section className="dir-reports-section dir-reports-overview" aria-labelledby="dir-reports-overview">
          <div className="dir-reports-section-head">
            <h2 id="dir-reports-overview" className="dir-reports-section-title">Overview</h2>
            <p className="dir-reports-section-sub">Live placement and partnership snapshot for the current term.</p>
          </div>

          {overviewLoading ? (
            <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted" /></div>
          ) : (
            <div className="row g-3">
              <div className="col-sm-6 col-xl-3">
                <div className="stat-card dir-reports-stat">
                  <div className="stat-icon green"><i className="fa fa-users" aria-hidden="true" /></div>
                  <div>
                    <div className="stat-value">{stats.active_interns ?? 0}</div>
                    <div className="stat-label">Active Interns</div>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-xl-3">
                <div className="stat-card dir-reports-stat">
                  <div className="stat-icon teal"><i className="fa fa-building" aria-hidden="true" /></div>
                  <div>
                    <div className="stat-value">{stats.partner_companies ?? 0}</div>
                    <div className="stat-label">Partner Companies</div>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-xl-3">
                <div className="stat-card dir-reports-stat">
                  <div className="stat-icon blue"><i className="fa fa-trophy" aria-hidden="true" /></div>
                  <div>
                    <div className="stat-value">{stats.completed ?? 0}</div>
                    <div className="stat-label">Completed</div>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-xl-3">
                <div className="stat-card dir-reports-stat">
                  <div className="stat-icon amber"><i className="fa fa-chart-pie" aria-hidden="true" /></div>
                  <div>
                    <div className="stat-value">{stats.placement_rate ?? 0}%</div>
                    <div className="stat-label">Placement Rate</div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </section>

        <section className="dir-reports-section dir-reports-cards-section" aria-labelledby="dir-reports-generate">
          <div className="dir-reports-section-head">
            <h2 id="dir-reports-generate" className="dir-reports-section-title">Generate a report</h2>
            <p className="dir-reports-section-sub">Select a report type to view it inline, then export or print.</p>
          </div>

          <div className="row g-3 dir-reports-card-row">
            {REPORT_TYPES.map((r) => {
              const selected = activeReport === r.key
              return (
                <div key={r.key} className="col-sm-6 col-xl-3">
                  <div
                    className={`content-card h-100 dir-reports-gen-card ${selected ? 'is-selected' : ''}`}
                    role="button"
                    tabIndex={0}
                    onClick={() => generateReport(r.key)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault()
                        generateReport(r.key)
                      }
                    }}
                  >
                    <div className="p-3 text-center h-100 d-flex flex-column">
                      <div className={`stat-icon ${r.color} mx-auto mb-2 dir-reports-gen-icon`}>
                        <i className={`fa ${r.icon}`} aria-hidden="true" />
                      </div>
                      <div className="fw-semibold mb-1">{r.title}</div>
                      <p className="text-muted mb-3 flex-grow-1 dir-reports-gen-desc">{r.desc}</p>
                      <button
                        type="button"
                        className={`btn btn-sm ${selected ? 'btn-green' : 'btn-outline-green'}`}
                        onClick={(e) => { e.stopPropagation(); generateReport(r.key) }}
                        disabled={reportLoading && selected}
                      >
                        {reportLoading && selected
                          ? <><i className="fa fa-spinner fa-spin me-1" aria-hidden="true" />Generating…</>
                          : <><i className="fa fa-play me-1" aria-hidden="true" />Generate</>}
                      </button>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        </section>

        {activeReport && (
          <section className="dir-reports-section" aria-labelledby="dir-report-output-title">
            <div className="content-card" id="report-output">
              <div className="content-card-header d-print-none dir-reports-output-header">
                <i className={`fa ${activeMeta?.icon}`} aria-hidden="true" />
                <h6 id="dir-report-output-title">{activeMeta?.title}</h6>
                {generatedAt && <small className="ms-auto text-muted">Generated: {generatedAt}</small>}
                <button
                  type="button"
                  className="btn btn-sm btn-outline-success ms-2"
                  onClick={handleExportCsv}
                  disabled={reportLoading || !reportRows.length}
                >
                  <i className="fa fa-file-csv me-1" aria-hidden="true" />
                  Export CSV
                </button>
                <button
                  type="button"
                  className="btn btn-sm btn-outline-secondary ms-2"
                  onClick={handlePrint}
                  disabled={reportLoading || !reportRows.length}
                >
                  <i className="fa fa-print me-1" aria-hidden="true" />
                  Print / Save PDF
                </button>
              </div>

              <div className="d-none d-print-block p-3 mb-3 border-bottom dir-reports-print-banner">
                <h5 className="mb-0">INTERNTRACK — {activeMeta?.title}</h5>
                <small className="text-muted">University of Cabuyao · {CURRENT_TERM} · Generated: {generatedAt}</small>
              </div>

              <div className="p-3">
                {reportLoading ? (
                  <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted" /></div>
                ) : reportError ? (
                  <EmptyState icon="fa-exclamation-triangle" title="Could not generate report" message={reportError} />
                ) : (
                  <>
                    {activeReport === 'internship-summary' && <InternshipSummaryTable rows={reportRows} />}
                    {activeReport === 'company-partnerships' && <CompanyPartnershipsTable rows={reportRows} />}
                    {activeReport === 'moa-status' && <MoaStatusTable rows={reportRows} />}
                    {activeReport === 'placement-trends' && (
                      <PlacementTrendsTable rows={reportRows} years={trendYears} />
                    )}
                  </>
                )}
              </div>
            </div>
          </section>
        )}
      </div>
    </Layout>
  )
}

export default DirectorReports

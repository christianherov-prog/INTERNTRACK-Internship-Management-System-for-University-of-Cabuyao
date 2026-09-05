import { useEffect, useState, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import { displayLabel } from '../../utils/displayLabel'

const REPORT_TYPES = [
  {
    key: 'internship-summary',
    title: 'Internship Summary Report',
    icon: 'fa-graduation-cap',
    color: 'blue',
    desc: 'Interns by program (ongoing vs completed) from live analytics.',
  },
  {
    key: 'company-partnerships',
    title: 'Company Partnerships Report',
    icon: 'fa-building',
    color: 'teal',
    desc: 'Top partner companies by intern count and industry.',
  },
  {
    key: 'moa-status',
    title: 'MOA Status Report',
    icon: 'fa-file-signature',
    color: 'amber',
    desc: 'Counts of partner companies by Memorandum of Agreement status.',
  },
  {
    key: 'ched-annual',
    title: 'CHED Annual Report',
    icon: 'fa-file-contract',
    color: 'green',
    desc: 'Aggregated HTE data (Total Interns, Completed, Ongoing, MOA) for CHED compliance.',
  },
]

function InternshipSummaryTable({ data }) {
  if (!data || data.length === 0) {
    return <EmptyState icon="fa-graduation-cap" title="No internship data" message="No ongoing or completed internships recorded yet." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr><th>Program</th><th>Ongoing</th><th>Completed</th><th>Total</th></tr>
        </thead>
        <tbody>
          {data.map((r, i) => (
            <tr key={i}>
              <td className="fw-semibold">{displayLabel(r.program, 'Unknown')}</td>
              <td>{r.ongoing ?? 0}</td>
              <td>{r.completed ?? 0}</td>
              <td>{r.count ?? 0}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function CompanyPartnershipsTable({ data }) {
  if (!data || data.length === 0) {
    return <EmptyState icon="fa-building" title="No company data" message="No partner companies with interns found." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr><th>#</th><th>Company</th><th>Industry</th><th>MOA Status</th><th>Interns</th></tr>
        </thead>
        <tbody>
          {data.map((r, i) => (
            <tr key={i}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.company_name}</td>
              <td>{r.industry ?? '—'}</td>
              <td>
                <span className={`badge ${r.moa_status === 'active' ? 'bg-success' : r.moa_status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'}`}>
                  {r.moa_status ? r.moa_status.toUpperCase() : '—'}
                </span>
              </td>
              <td>{r.internships_count ?? 0}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function MoaStatusTable({ data }) {
  const entries = Object.entries(data || {})
  if (entries.length === 0) {
    return <EmptyState icon="fa-file-signature" title="No MOA data" message="No MOA records available." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr><th>MOA Status</th><th>Count</th></tr>
        </thead>
        <tbody>
          {entries.map(([status, count], i) => (
            <tr key={i}>
              <td className="fw-semibold text-uppercase">{status}</td>
              <td>{count}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ChedAnnualTable({ data }) {
  if (!data || data.length === 0) {
    return <EmptyState icon="fa-file-contract" title="No CHED data" message="No valid CHED report records found." />
  }
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr>
            <th>#</th>
            <th>Company / HTE</th>
            <th>Address</th>
            <th>Industry</th>
            <th>MOA Status</th>
            <th>Total Interns</th>
            <th>Ongoing</th>
            <th>Completed</th>
          </tr>
        </thead>
        <tbody>
          {data.map((r, i) => (
            <tr key={i}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.company_name}</td>
              <td className="text-muted text-truncate" style={{ maxWidth: '200px' }} title={r.address}>{r.address || '—'}</td>
              <td>{r.industry || '—'}</td>
              <td>
                <span className={`badge ${r.moa_status === 'active' ? 'bg-success' : r.moa_status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'}`}>
                  {r.moa_status ? r.moa_status.toUpperCase() : '—'}
                </span>
              </td>
              <td className="fw-bold">{r.total_interns ?? 0}</td>
              <td>{r.ongoing ?? 0}</td>
              <td>{r.completed ?? 0}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function DirectorReports({ embedded = false }) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [activeReport, setActiveReport] = useState(null)
  const [generating, setGenerating] = useState(false)
  const [chedData, setChedData] = useState(null)

  const load = () => {
    setLoading(true)
    setError(null)
    api.get('/director/dashboard')
      .then((dash) => setData(dash.data))
      .catch((err) => setError(err.response?.data?.message || 'Failed to load reports overview.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const stats = data?.stats ?? {}
  const byProgram = data?.by_program ?? []
  const topCompanies = data?.top_companies ?? []
  const moaByStatus = data?.moa_by_status ?? {}

  const generateReport = async (key) => {
    setActiveReport(key)
    if (key === 'ched-annual') {
      if (chedData) return // Already loaded
      setGenerating(true)
      try {
        const res = await api.get('/director/reports/ched-data')
        setChedData(res.data.rows ?? [])
      } catch (err) {
        alert(err.response?.data?.message || 'Failed to fetch CHED data')
        setActiveReport(null)
      } finally {
        setGenerating(false)
      }
    }
  }

  // Generate CSV manually instead of using modal
  const handleExportCsv = () => {
    let csvContent = "data:text/csv;charset=utf-8,"
    let rows = []

    if (activeReport === 'internship-summary') {
      rows = [['Program', 'Ongoing', 'Completed', 'Total']]
      byProgram.forEach(r => {
        rows.push([r.program ?? 'Unknown', r.ongoing ?? 0, r.completed ?? 0, r.count ?? 0])
      })
    } else if (activeReport === 'company-partnerships') {
      rows = [['Company', 'Industry', 'MOA Status', 'Interns']]
      topCompanies.forEach(r => {
        rows.push([r.company_name, r.industry ?? '-', r.moa_status ?? '-', r.internships_count ?? 0])
      })
    } else if (activeReport === 'moa-status') {
      rows = [['Status', 'Count']]
      Object.entries(moaByStatus).forEach(([status, count]) => {
        rows.push([status, count])
      })
    } else if (activeReport === 'ched-annual') {
      rows = [['Company / HTE', 'Address', 'Industry', 'MOA Status', 'Total Interns', 'Ongoing', 'Completed']]
      ;(chedData || []).forEach(r => {
        rows.push([
          r.company_name, r.address, r.industry, r.moa_status,
          r.total_interns, r.ongoing, r.completed
        ])
      })
    }

    if (rows.length === 0) return

    // Escape CSV values
    csvContent += rows.map(e => e.map(item => {
      let str = String(item).replace(/"/g, '""')
      if (str.includes(',') || str.includes('"') || str.includes('\n')) {
        str = `"${str}"`
      }
      return str
    }).join(",")).join("\n")

    const encodedUri = encodeURI(csvContent)
    const link = document.createElement("a")
    link.setAttribute("href", encodedUri)
    link.setAttribute("download", `${activeReport}-export.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const printRef = useRef(null)
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: 'Director_Report'
  })

  const Wrapper = embedded ? 'div' : Layout
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "Reports", subtitle: CURRENT_TERM, icon: "fa-chart-bar", bodyClass: "director-page" }

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : !error && (
        <>
          <div className="row g-3 mb-4">
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon green"><i className="fa fa-users"></i></div><div><div className="stat-value">{stats.active_interns ?? 0}</div><div className="stat-label">Active Interns</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon teal"><i className="fa fa-building"></i></div><div><div className="stat-value">{stats.partner_companies ?? 0}</div><div className="stat-label">Partner Companies</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon blue"><i className="fa fa-trophy"></i></div><div><div className="stat-value">{stats.completed ?? 0}</div><div className="stat-label">Completed</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-icon amber"><i className="fa fa-chart-pie"></i></div><div><div className="stat-value">{stats.placement_rate ?? 0}%</div><div className="stat-label">Placement Rate</div></div></div>
            </div>
          </div>

          <h6 className="fw-semibold mb-3">Available Reports</h6>
          <div className="row g-3 mb-4">
            {REPORT_TYPES.map((r) => (
              <div key={r.key} className="col-md-6 col-lg-3">
                <div 
                  className={`content-card h-100 d-flex flex-column ${activeReport === r.key ? 'border-2 border-primary' : ''}`}
                  style={{ cursor: 'pointer' }} 
                  onClick={() => generateReport(r.key)}
                >
                  <div className="p-3 text-center d-flex flex-column h-100">
                    <div className={`stat-icon ${r.color} mx-auto mb-2`} style={{ width: 48, height: 48, fontSize: '1.3rem' }}>
                      <i className={`fa ${r.icon}`}></i>
                    </div>
                    <div className="fw-semibold mb-1">{r.title}</div>
                    <p className="text-muted mb-3 flex-grow-1" style={{ fontSize: '0.82rem' }}>{r.desc}</p>
                    <button
                      className={`btn btn-sm mt-auto ${activeReport === r.key ? 'btn-primary' : 'btn-outline-primary'}`}
                      onClick={(e) => { e.stopPropagation(); generateReport(r.key) }}
                      disabled={generating && activeReport === r.key}
                    >
                      {generating && activeReport === r.key 
                        ? <><i className="fa fa-spinner fa-spin me-1"></i>Generating…</> 
                        : <><i className="fa fa-play me-1"></i>Generate</>}
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {activeReport && (
            <div className="content-card" id="report-output" ref={printRef}>
              <div className="content-card-header d-print-none">
                <i className={`fa ${REPORT_TYPES.find((r) => r.key === activeReport)?.icon}`}></i>
                <h6>{REPORT_TYPES.find((r) => r.key === activeReport)?.title}</h6>
                <small className="ms-auto text-muted d-none d-md-block">Generated live</small>
                <button className="btn btn-sm btn-outline-success ms-2" onClick={handleExportCsv} disabled={activeReport === 'ched-annual' && !chedData}>
                  <i className="fa fa-file-csv me-1"></i>Export CSV
                </button>
                <button className="btn btn-sm btn-outline-secondary ms-2" onClick={handlePrint} disabled={activeReport === 'ched-annual' && !chedData}>
                  <i className="fa fa-print me-1"></i>Print / Save PDF
                </button>
              </div>

              {/* Print-only header */}
              <div className="d-none d-print-block p-4 border-bottom text-center">
                <img src="/interntrack-mark.png" alt="INTERNTRACK" className="print-app-mark mx-auto" />
                <h4 className="fw-bold mb-1">{REPORT_TYPES.find((r) => r.key === activeReport)?.title}</h4>
                <div className="text-muted">Orb-BIT InternTrack | Director Dashboard | Term: {CURRENT_TERM}</div>
              </div>

              <div className="p-3">
                {generating && activeReport === 'ched-annual' ? (
                  <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
                ) : activeReport === 'internship-summary' ? (
                  <InternshipSummaryTable data={byProgram} />
                ) : activeReport === 'company-partnerships' ? (
                  <CompanyPartnershipsTable data={topCompanies} />
                ) : activeReport === 'moa-status' ? (
                  <MoaStatusTable data={moaByStatus} />
                ) : activeReport === 'ched-annual' ? (
                  <ChedAnnualTable data={chedData} />
                ) : null}
              </div>
            </div>
          )}
        </>
      )}
    </Wrapper>
  )
}

export default DirectorReports

import { useEffect, useState, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import { displayLabel } from '../../utils/displayLabel'
import ReportExportModal from '../../components/modals/ReportExportModal'
import StatusChip from '../../components/StatusChip'
import { reportPrintOptions } from '../../utils/reportPrint'
import { useCachedPage } from '../../hooks/useCachedPage'
import InternTrackLoader from '../../components/InternTrackLoader'

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
          <tr><th>Program</th><th className="it-col-num">Ongoing</th><th className="it-col-num">Completed</th><th className="it-col-num">Other</th><th className="it-col-num">Total</th></tr>
        </thead>
        <tbody>
          {data.map((r, i) => (
            <tr key={i}>
              <td className="fw-semibold">{displayLabel(r.program, 'Unknown')}</td>
              <td>{r.ongoing ?? 0}</td>
              <td>{r.completed ?? 0}</td>
              <td>{r.other ?? Math.max(0, (r.total ?? r.count ?? 0) - (r.ongoing ?? 0) - (r.completed ?? 0))}</td>
              <td>{r.total ?? r.count ?? ((r.ongoing ?? 0) + (r.completed ?? 0) + (r.other ?? 0))}</td>
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
          <tr><th className="it-col-num">#</th><th>Company</th><th>Industry</th><th className="it-col-status">MOA Status</th><th className="it-col-num">Interns</th></tr>
        </thead>
        <tbody>
          {data.map((r, i) => (
            <tr key={i}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.company_name}</td>
              <td>{r.industry ?? '—'}</td>
              <td>
                <StatusChip status={r.moa_status} />
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
          <tr><th>MOA Status</th><th className="it-col-num">Count</th></tr>
        </thead>
        <tbody>
          {entries.map(([status, count], i) => (
            <tr key={i}>
              <td><StatusChip status={status} /></td>
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
            <th className="it-col-num">#</th>
            <th>Company / HTE</th>
            <th>Address</th>
            <th>Industry</th>
            <th className="it-col-status">MOA Status</th>
            <th className="it-col-num">Total Interns</th>
            <th className="it-col-num">Ongoing</th>
            <th className="it-col-num">Completed</th>
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
                <StatusChip status={r.moa_status} />
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
  const { loading, seed, run } = useCachedPage('director:reports-overview')
  const [data, setData] = useState(seed ?? null)
  const [error, setError] = useState(null)

  const [activeReport, setActiveReport] = useState(null)
  const [generating, setGenerating] = useState(false)
  const [chedData, setChedData] = useState(null)
  const [exportPreview, setExportPreview] = useState(null)

  const load = () => {
    setError(null)
    run(() => api.get('/director/dashboard').then((dash) => dash.data))
      .then((next) => { if (next) setData(next) })
      .catch((err) => setError(err.response?.data?.message || 'Failed to load reports overview.'))
  }

  useEffect(() => { load() }, [])

  const stats = data?.stats ?? {}
  const byProgram = data?.by_program ?? []
  const topCompanies = data?.top_companies ?? []
  const leastUsedHte = data?.least_used_hte ?? []
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

  const handleExportCsv = () => {
    let rows = []

    if (activeReport === 'internship-summary') {
      rows = byProgram.map(r => ({
        Program: r.program ?? 'Unknown',
        Ongoing: r.ongoing ?? 0,
        Completed: r.completed ?? 0,
        Other: r.other ?? Math.max(0, (r.total ?? r.count ?? 0) - (r.ongoing ?? 0) - (r.completed ?? 0)),
        Total: r.total ?? r.count ?? 0,
      }))
    } else if (activeReport === 'company-partnerships') {
      rows = topCompanies.map(r => ({
        Company: r.company_name,
        Industry: r.industry ?? '-',
        'MOA Status': r.moa_status ?? '-',
        Interns: r.internships_count ?? 0,
      }))
    } else if (activeReport === 'moa-status') {
      rows = Object.entries(moaByStatus).map(([status, count]) => ({
        Status: status,
        Count: count,
      }))
    } else if (activeReport === 'ched-annual') {
      rows = (chedData || []).map(r => ({
        'Company / HTE': r.company_name,
        Address: r.address,
        Industry: r.industry,
        'MOA Status': r.moa_status,
        'Total Interns': r.total_interns,
        Ongoing: r.ongoing,
        Completed: r.completed,
      }))
    }

    if (rows.length === 0) return
    setExportPreview({
      title: REPORT_TYPES.find((r) => r.key === activeReport)?.title || 'Director report',
      filename: `${activeReport}-export`,
      statusColumns: ['MOA Status', 'Status'],
      rows,
    })
  }

  const printRef = useRef(null)
  const handlePrint = useReactToPrint(reportPrintOptions(printRef, 'Director_Report'))

  const Wrapper = embedded ? 'div' : Layout
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "Reports", subtitle: CURRENT_TERM, icon: "fa-chart-bar", bodyClass: "director-page reports-page" }

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={load} />}

      {loading ? (
        <div className="text-center py-5"><InternTrackLoader /></div>
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
                  className={`content-card h-100 d-flex flex-column ${activeReport === r.key ? 'report-card--active' : ''}`}
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
                      className={`btn btn-sm mt-auto ${activeReport === r.key ? 'btn-success' : 'btn-outline-success'}`}
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
            <div className="content-card interntrack-report-print" id="report-output" ref={printRef}>
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

               <div style={{ position: 'relative', textAlign: 'center', marginBottom: '12px', fontFamily: 'Arial, sans-serif' }}>
        <div style={{ position: 'absolute', top: '50%', left: '200px', transform: 'translateY(-50%)', width: '75px', height: '75px' }}>
          <img src="/images/pnc-logo.png" alt="University of Cabuyao Logo" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
        </div>
        <div style={{ fontSize: '9.5pt', lineHeight: '1' }}>Republic of the Philippines</div>
        <div style={{ fontSize: '22pt', fontWeight: 'bold', fontFamily: '"Old English Text MT", serif', color: '#004d00', lineHeight: '1', margin: '2px 0' }}>
          University of Cabuyao
        </div>
        <div style={{ fontSize: '11.5pt', fontFamily: 'Arial, sans-serif', margin: '2px 0', lineHeight: '1' }}>(PAMANTASAN NG CABUYAO)</div>
        <div style={{ fontSize: '10.5pt', fontWeight: 'bold', fontStyle: 'italic', margin: '2px 0', lineHeight: '1' }}>Placement, Alumni, & Linkages Department</div>
        <div style={{ fontSize: '8.5pt', lineHeight: '1' }}>Katapatan Mutual Homes, Brgy. Banay-banay, City of Cabuyao, Laguna, Phillippines 4025</div>
      </div>
      

              <div className="p-3">
                {generating && activeReport === 'ched-annual' ? (
                  <div className="text-center py-5"><InternTrackLoader /></div>
                ) : activeReport === 'internship-summary' ? (
                  <InternshipSummaryTable data={byProgram} />
                ) : activeReport === 'company-partnerships' ? (
                  <>
                    <h6 className="fw-semibold mb-2">Most-used HTEs</h6>
                    <CompanyPartnershipsTable data={topCompanies} />
                    <h6 className="fw-semibold mt-4 mb-2">Least-used HTEs</h6>
                    <CompanyPartnershipsTable data={leastUsedHte} />
                  </>
                ) : activeReport === 'moa-status' ? (
                  <MoaStatusTable data={moaByStatus} />
                ) : activeReport === 'ched-annual' ? (
                  <ChedAnnualTable data={chedData} />
                ) : null}
              </div>
            </div>
          )}
          <ReportExportModal preview={exportPreview} onClose={() => setExportPreview(null)} />
        </>
      )}
    </Wrapper>
  )
}

export default DirectorReports

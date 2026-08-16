import { useState, useEffect, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import Layout from '../../components/Layout'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import ReportExportModal from '../../components/modals/ReportExportModal'

const REPORT_TYPES = [
  {
    key: 'student-summary',
    title: 'Student Summary Report',
    icon: 'fa-users',
    color: 'blue',
    desc: 'Comprehensive overview of all student internship activities, hours, and document compliance.',
  },
  {
    key: 'compliance',
    title: 'Document Compliance Report',
    icon: 'fa-folder-open',
    color: 'green',
    desc: 'Shows which students have submitted all required documents and identifies missing requirements.',
  },
  {
    key: 'performance',
    title: 'Performance Analytics Report',
    icon: 'fa-chart-bar',
    color: 'amber',
    desc: 'Aggregated evaluation scores and performance metrics across all programs.',
  },
]

function StatusBadge({ status }) {
  const map = {
    ongoing:          'badge bg-success',
    completed:        'badge bg-primary',
    pending_placement:'badge bg-warning text-dark',
    for_evaluation:   'badge bg-info',
    terminated:       'badge bg-danger',
  }
  return <span className={map[status] ?? 'badge bg-secondary'}>{status}</span>
}

function StudentSummaryTable({ data }) {
  const rows = data.students ?? []
  if (rows.length === 0) return <p className="text-muted">No data available.</p>
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr>
            <th>#</th><th>Student</th><th>Program</th><th>Company</th>
            <th>Status</th><th>Hours</th><th>Progress</th>
            <th>Days</th><th>Journals ✓</th><th>Docs ✓</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              <td>{i + 1}</td>
              <td>
                <div className="fw-semibold">{r.student_name}</div>
                <div className="text-muted">{r.student_number}</div>
              </td>
              <td>{r.program}</td>
              <td>{r.company}</td>
              <td><StatusBadge status={r.status} /></td>
              <td>{r.hours_rendered}/{r.target_hours}</td>
              <td>
                <div className="progress" style={{ height: '6px', minWidth: '80px' }}>
                  <div className="progress-bar bg-success" style={{ width: `${r.progress_pct}%` }}></div>
                </div>
                <small>{r.progress_pct}%</small>
              </td>
              <td>{r.validated_days}</td>
              <td>{r.approved_journals}</td>
              <td>{r.approved_docs}/9</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ComplianceTable({ data }) {
  const rows = data.rows ?? []
  if (rows.length === 0) return <p className="text-muted">No data available.</p>
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr><th>#</th><th>Student</th><th>Program</th><th>Compliance</th><th>Missing Documents</th></tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              <td>{i + 1}</td>
              <td className="fw-semibold">{r.student_name}</td>
              <td>{r.program}</td>
              <td>
                <div className="d-flex align-items-center gap-2">
                  <div className="progress flex-grow-1" style={{ height: '8px' }}>
                    <div
                      className={`progress-bar ${r.compliance_pct >= 80 ? 'bg-success' : r.compliance_pct >= 50 ? 'bg-warning' : 'bg-danger'}`}
                      style={{ width: `${r.compliance_pct}%` }}
                    ></div>
                  </div>
                  <small>{r.compliance_pct}%</small>
                </div>
                <small className="text-muted">{r.approved_docs}/{r.required_docs} submitted</small>
              </td>
              <td>
                {r.missing_docs?.length > 0 ? (
                  <ul className="mb-0 ps-3" style={{ fontSize: '0.78rem' }}>
                    {r.missing_docs.map(d => <li key={d} className="text-danger">{d}</li>)}
                  </ul>
                ) : <span className="text-success fw-semibold">Complete ✓</span>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function PerformanceTable({ data }) {
  const byProgram = data.by_program ?? []
  const evalAvg   = data.eval_averages ?? []

  return (
    <>
      <h6 className="fw-semibold mb-2">By Program</h6>
      <div className="table-responsive mb-4">
        <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
          <thead className="table-light">
            <tr><th>Program</th><th>Total</th><th>Completed</th><th>Avg Hours</th><th>Completion Rate</th></tr>
          </thead>
          <tbody>
            {byProgram.map((p, i) => (
              <tr key={i}>
                <td className="fw-semibold">{p.program}</td>
                <td>{p.total}</td>
                <td>{p.completed}</td>
                <td>{parseFloat(p.avg_hours ?? 0).toFixed(1)}</td>
                <td>{p.total > 0 ? Math.round(p.completed / p.total * 100) : 0}%</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {evalAvg.length > 0 && (
        <>
          <h6 className="fw-semibold mb-2">Evaluation Averages</h6>
          <div className="table-responsive">
            <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
              <thead className="table-light">
                <tr>
                  <th>Evaluator Type</th>
                  <th>Overall Avg</th>
                </tr>
              </thead>
              <tbody>
                {evalAvg.map((e, i) => (
                  <tr key={i}>
                    <td className="text-capitalize fw-semibold">{e.evaluator_type}</td>
                    <td><strong>{parseFloat(e.avg_overall ?? 0).toFixed(2)}</strong></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </>
  )
}

function CoordReports() {
  const [activeReport, setActiveReport] = useState(null)
  const [reportData, setReportData]     = useState(null)
  const [loading, setLoading]           = useState(false)
  const [generatedAt, setGeneratedAt]   = useState(null)
  const [programFilter, setProgramFilter] = useState('')
  const [industryFilter, setIndustryFilter] = useState('')
  const [filterOptions, setFilterOptions] = useState({ programs: [], industries: [] })
  const [exportPreview, setExportPreview] = useState(null)

  const generateReport = async (key) => {
    setLoading(true)
    setActiveReport(key)
    setReportData(null)
    try {
      const params = {}
      if (programFilter) params.program = programFilter
      if (industryFilter) params.industry = industryFilter
      const res = await api.get(`/coordinator/reports/${key}`, { params })
      setReportData(res.data)
      setGeneratedAt(res.data.generated_at)
      if (res.data.filters) setFilterOptions(res.data.filters)
    } catch (err) {
      console.error(err)
    } finally { setLoading(false) }
  }

  // Prefetch filter options via student-summary (lightweight enough)
  useEffect(() => {
    api.get('/coordinator/reports/student-summary')
      .then((res) => {
        if (res.data.filters) setFilterOptions(res.data.filters)
      })
      .catch(() => {})
  }, [])

  const printRef = useRef(null)
  const handlePrint = useReactToPrint({
    contentRef: printRef,
    documentTitle: 'Coord_Report'
  })

  const handleExportCsv = () => {
    if (!reportData) return

    if (activeReport === 'student-summary') {
      setExportPreview({
        title: 'Student Summary Report',
        filename: 'student-summary-report',
        rows: (reportData.students ?? []).map(r => ({
          Student: r.student_name, 'Student No.': r.student_number, Program: r.program, Company: r.company,
          Industry: r.industry, Status: r.status, 'Hours Rendered': r.hours_rendered, 'Target Hours': r.target_hours,
          'Progress %': r.progress_pct, 'Validated Days': r.validated_days,
          'Approved Journals': r.approved_journals, 'Approved Docs': r.approved_docs,
        })),
      })
    } else if (activeReport === 'compliance') {
      setExportPreview({
        title: 'Document Compliance Report',
        filename: 'document-compliance-report',
        rows: (reportData.rows ?? []).map(r => ({
          Student: r.student_name, Program: r.program, Industry: r.industry, 'Compliance %': r.compliance_pct,
          'Approved Docs': r.approved_docs, 'Required Docs': r.required_docs,
          'Missing Documents': (r.missing_docs ?? []).join('; '),
        })),
      })
    } else if (activeReport === 'performance') {
      setExportPreview({
        title: 'Performance Analytics Report',
        filename: 'performance-report',
        rows: (reportData.by_program ?? []).map(p => ({
          Program: p.program, Total: p.total, Completed: p.completed,
          'Avg Hours': parseFloat(p.avg_hours ?? 0).toFixed(1),
          'Completion Rate %': p.total > 0 ? Math.round(p.completed / p.total * 100) : 0,
        })),
      })
    }
  }

  return (
    <Layout title="Reports" subtitle={CURRENT_TERM} icon="fa-chart-bar" bodyClass="coordinator-page reports-page">
      <div className="content-card mb-3">
        <div className="content-card-header"><i className="fa fa-filter"></i><h6>Report Filters</h6></div>
        <div className="p-3 row g-3 align-items-end">
          <div className="col-md-4">
            <label className="form-label fw-semibold">Academic Program</label>
            <select className="form-select" value={programFilter} onChange={(e) => setProgramFilter(e.target.value)}>
              <option value="">All programs</option>
              {(filterOptions.programs ?? []).map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>
          <div className="col-md-4">
            <label className="form-label fw-semibold">Company Industry</label>
            <select className="form-select" value={industryFilter} onChange={(e) => setIndustryFilter(e.target.value)}>
              <option value="">All industries</option>
              {(filterOptions.industries ?? []).map((ind) => (
                <option key={ind} value={ind}>{ind}</option>
              ))}
            </select>
          </div>
          <div className="col-md-4">
            <button
              type="button"
              className="btn btn-outline-secondary"
              onClick={() => { setProgramFilter(''); setIndustryFilter('') }}
            >
              Clear filters
            </button>
            {activeReport && (
              <button
                type="button"
                className="btn btn-outline-primary ms-2"
                onClick={() => generateReport(activeReport)}
                disabled={loading}
              >
                Re-apply
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Report Type Selector */}
      <div className="row g-3 mb-4">
        {REPORT_TYPES.map(r => (
          <div key={r.key} className="col-md-4">
            <div
              className={`content-card h-100 cursor-pointer ${activeReport === r.key ? 'border-2 border-primary' : ''}`}
              style={{ cursor: 'pointer', borderColor: activeReport === r.key ? '#6366f1' : undefined }}
              onClick={() => generateReport(r.key)}
            >
              <div className="p-3 text-center">
                <div className={`stat-icon ${r.color} mx-auto mb-2`} style={{ width: '48px', height: '48px', fontSize: '1.3rem' }}>
                  <i className={`fa ${r.icon}`}></i>
                </div>
                <div className="fw-semibold mb-1">{r.title}</div>
                <p className="text-muted mb-3" style={{ fontSize: '0.82rem' }}>{r.desc}</p>
                <button
                  className={`btn btn-sm ${activeReport === r.key ? 'btn-primary' : 'btn-outline-primary'}`}
                  onClick={e => { e.stopPropagation(); generateReport(r.key) }}
                  disabled={loading && activeReport === r.key}
                >
                  {loading && activeReport === r.key
                    ? <><i className="fa fa-spinner fa-spin me-1"></i>Generating…</>
                    : <><i className="fa fa-play me-1"></i>Generate</>}
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Report Output */}
      {activeReport && (
        <div className="content-card" id="report-output" ref={printRef}>
          <div className="content-card-header d-print-none">
            <i className={`fa ${REPORT_TYPES.find(r => r.key === activeReport)?.icon}`}></i>
            <h6>{REPORT_TYPES.find(r => r.key === activeReport)?.title}</h6>
            {generatedAt && <small className="ms-auto text-muted">Generated: {generatedAt}</small>}
            <button className="btn btn-sm btn-outline-success ms-2" onClick={handleExportCsv} disabled={!reportData}>
              <i className="fa fa-file-csv me-1"></i>Export CSV
            </button>
            <button className="btn btn-sm btn-outline-secondary ms-2" onClick={handlePrint}>
              <i className="fa fa-print me-1"></i>Print / Save PDF
            </button>
          </div>

          {/* Print Header */}
          <div className="d-none d-print-block p-3 mb-3 border-bottom">
            <h5 className="mb-0">INTERNTRACK — {REPORT_TYPES.find(r => r.key === activeReport)?.title}</h5>
            <small className="text-muted">{`University of Cabuyao · ${CURRENT_TERM} · Generated: ${generatedAt}`}</small>
          </div>

          <div className="p-3">
            {loading ? (
              <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            ) : reportData ? (
              <>
                {activeReport === 'student-summary' && <StudentSummaryTable data={reportData} />}
                {activeReport === 'compliance'      && <ComplianceTable      data={reportData} />}
                {activeReport === 'performance'     && <PerformanceTable     data={reportData} />}
              </>
            ) : (
              <p className="text-muted text-center py-3">Click "Generate" to load the report.</p>
            )}
          </div>
        </div>
      )}
      <ReportExportModal preview={exportPreview} onClose={() => setExportPreview(null)} />
    </Layout>
  )
}

export default CoordReports

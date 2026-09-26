import { useState, useEffect, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import Layout from '../../components/Layout'
import api from '../../services/api'
import { CURRENT_TERM } from '../../config/term'
import ReportExportModal from '../../components/modals/ReportExportModal'
import { formatRequirementsStatusCsv } from '../../components/ComplianceRequirementsStatus'
import { StudentSummaryTable, ComplianceTable } from '../../components/reports/ReportTables'
import { displayLabel } from '../../utils/displayLabel'
import { reportPrintOptions } from '../../utils/reportPrint'
import { formatManilaDateTime } from '../../utils/manilaTime'
import InternTrackLoader from '../../components/InternTrackLoader'

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
    desc: 'Shows every applicable requirement with Approved, Pending, Missing, or Rejected status.',
  },
  {
    key: 'performance',
    title: 'Performance Analytics Report',
    icon: 'fa-chart-bar',
    color: 'amber',
    desc: 'Aggregated evaluation scores and performance metrics across all programs.',
  },
]

function PerformanceTable({ data }) {
  const byProgram = data.by_program ?? []
  const evalAvg   = data.eval_averages ?? []

  return (
    <>
      <h6 className="fw-semibold mb-2">By Program</h6>
      <div className="table-responsive mb-4">
        <table className="table table-sm table-bordered align-middle" style={{ fontSize: '0.82rem' }}>
          <thead className="table-light">
            <tr><th>Program</th><th className="it-col-num">Total</th><th className="it-col-num">Completed</th><th className="it-col-num">Avg Hours</th><th className="it-col-num">Completion Rate</th></tr>
          </thead>
          <tbody>
            {byProgram.map((p, i) => (
              <tr key={i}>
                <td className="fw-semibold">{displayLabel(p.program, '—')}</td>
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
  const handlePrint = useReactToPrint(reportPrintOptions(printRef, 'Coord_Report'))

  const handleExportCsv = () => {
    if (!reportData) return

    if (activeReport === 'student-summary') {
      setExportPreview({
        title: 'Student Summary Report',
        filename: 'student-summary-report',
        statusColumns: ['Status'],
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
        requirementColumns: ['Requirements Status'],
        rows: (reportData.rows ?? []).map(r => ({
          Student: r.student_name, Program: r.program, Industry: r.industry, 'Compliance %': r.compliance_pct,
          'Approved Docs': r.approved_docs, 'Required Docs': r.required_docs,
          'Requirements Status': formatRequirementsStatusCsv(r),
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
                className="btn btn-outline-success ms-2"
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
              className={`content-card h-100 cursor-pointer ${activeReport === r.key ? 'report-card--active' : ''}`}
              style={{ cursor: 'pointer' }}
              onClick={() => generateReport(r.key)}
            >
              <div className="p-3 text-center">
                <div className={`stat-icon ${r.color} mx-auto mb-2`} style={{ width: '48px', height: '48px', fontSize: '1.3rem' }}>
                  <i className={`fa ${r.icon}`}></i>
                </div>
                <div className="fw-semibold mb-1">{r.title}</div>
                <p className="text-muted mb-3" style={{ fontSize: '0.82rem' }}>{r.desc}</p>
                <button
                  className={`btn btn-sm ${activeReport === r.key ? 'btn-success' : 'btn-outline-success'}`}
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
        <div className="content-card interntrack-report-print" id="report-output" ref={printRef}>
          <div className="content-card-header d-print-none">
            <i className={`fa ${REPORT_TYPES.find(r => r.key === activeReport)?.icon}`}></i>
            <h6>{REPORT_TYPES.find(r => r.key === activeReport)?.title}</h6>
            {generatedAt && <small className="ms-auto text-muted">Generated: {formatManilaDateTime(generatedAt)}</small>}
            <button className="btn btn-sm btn-outline-success ms-2" onClick={handleExportCsv} disabled={!reportData}>
              <i className="fa fa-file-csv me-1"></i>Export CSV
            </button>
            <button className="btn btn-sm btn-outline-secondary ms-2" onClick={handlePrint}>
              <i className="fa fa-print me-1"></i>Print / Save PDF
            </button>
          </div>

          {/* Print Header */}
          <div className="d-none d-print-block p-3 mb-3 border-bottom">
            <img src="/interntrack-mark.png" alt="INTERNTRACK" className="print-app-mark" />
            <h5 className="mb-0">INTERNTRACK — {REPORT_TYPES.find(r => r.key === activeReport)?.title}</h5>
            <small className="text-muted">{`University of Cabuyao · ${CURRENT_TERM} · Generated: ${formatManilaDateTime(generatedAt)}`}</small>
          </div>

          <div className="p-3">
            {loading ? (
              <div className="text-center py-4"><InternTrackLoader /></div>
            ) : reportData ? (
              <>
                {activeReport === 'student-summary' && <StudentSummaryTable data={reportData} empty={<p className="text-muted">No data available.</p>} />}
                {activeReport === 'compliance'      && <ComplianceTable      data={reportData} empty={<p className="text-muted">No data available.</p>} />}
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

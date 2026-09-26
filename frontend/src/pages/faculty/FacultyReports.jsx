import { useState, useRef } from 'react'
import { useReactToPrint } from 'react-to-print'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import EmptyState from '../../components/EmptyState'
import api from '../../services/api'
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
    desc: 'Overview of your assigned students: hours, journals, and document compliance.',
  },
  {
    key: 'compliance',
    title: 'Document Compliance Report',
    icon: 'fa-folder-open',
    color: 'green',
    desc: 'Shows every applicable requirement with Approved, Pending, Missing, or Rejected status for assigned students.',
  },
  {
    key: 'performance',
    title: 'Performance Analytics Report',
    icon: 'fa-chart-bar',
    color: 'amber',
    desc: 'Aggregated hours and evaluation metrics for your assigned section(s).',
  },
]

function PerformanceTable({ data }) {
  const byProgram = data.by_program ?? []
  const evalAvg = data.eval_averages ?? []

  if (byProgram.length === 0 && evalAvg.length === 0) {
    return <EmptyState icon="fa-chart-bar" title="No performance data" message="Metrics appear after assigned students have hours or evaluations." />
  }

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
                <tr><th>Evaluator</th><th>Technical</th><th>Communication</th><th>Teamwork</th><th>Initiative</th><th>Work Ethics</th><th>Overall</th></tr>
              </thead>
              <tbody>
                {evalAvg.map((e, i) => (
                  <tr key={i}>
                    <td className="text-capitalize fw-semibold">{e.evaluator_type}</td>
                    <td>{parseFloat(e.avg_technical ?? 0).toFixed(2)}</td>
                    <td>{parseFloat(e.avg_communication ?? 0).toFixed(2)}</td>
                    <td>{parseFloat(e.avg_teamwork ?? 0).toFixed(2)}</td>
                    <td>{parseFloat(e.avg_initiative ?? 0).toFixed(2)}</td>
                    <td>{parseFloat(e.avg_work_ethics ?? 0).toFixed(2)}</td>
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

function FacultyReports() {
  const [activeReport, setActiveReport] = useState(null)
  const [reportData, setReportData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [generatedAt, setGeneratedAt] = useState(null)
  const [exportPreview, setExportPreview] = useState(null)

  const generateReport = async (key) => {
    setLoading(true)
    setError(null)
    setActiveReport(key)
    setReportData(null)
    try {
      const res = await api.get(`/faculty/reports/${key}`)
      setReportData(res.data)
      setGeneratedAt(res.data.generated_at)
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to generate report.')
      setReportData(null)
    } finally {
      setLoading(false)
    }
  }

  const handleExportCsv = () => {
    if (!reportData) return

    if (activeReport === 'student-summary') {
      setExportPreview({
        title: 'Faculty Student Summary Report',
        filename: 'faculty-student-summary',
        statusColumns: ['Status'],
        rows: (reportData.students ?? []).map((r) => ({
          Student: r.student_name,
          'Student No.': r.student_number,
          Program: r.program,
          Company: r.company,
          Status: r.status,
          'Hours Rendered': r.hours_rendered,
          'Target Hours': r.target_hours,
          'Progress %': r.progress_pct,
          'Validated Days': r.validated_days,
          'Approved Journals': r.approved_journals,
          'Approved Docs': r.approved_docs,
          'Required Docs': r.required_docs ?? reportData.docs_total,
        })),
      })
    } else if (activeReport === 'compliance') {
      setExportPreview({
        title: 'Faculty Document Compliance Report',
        filename: 'faculty-document-compliance',
        requirementColumns: ['Requirements Status'],
        rows: (reportData.rows ?? []).map((r) => ({
          Student: r.student_name,
          Program: r.program,
          'Compliance %': r.compliance_pct,
          'Approved Docs': r.approved_docs,
          'Required Docs': r.required_docs,
          'Requirements Status': formatRequirementsStatusCsv(r),
        })),
      })
    } else if (activeReport === 'performance') {
      setExportPreview({
        title: 'Faculty Performance Analytics Report',
        filename: 'faculty-performance',
        rows: (reportData.by_program ?? []).map((p) => ({
          Program: p.program,
          Total: p.total,
          Completed: p.completed,
          'Avg Hours': parseFloat(p.avg_hours ?? 0).toFixed(1),
          'Completion Rate %': p.total > 0 ? Math.round(p.completed / p.total * 100) : 0,
        })),
      })
    }
  }

  const printRef = useRef(null)
  const handlePrint = useReactToPrint(reportPrintOptions(printRef, 'Faculty_Report'))

  return (
    <Layout title="Reports" subtitle="Assigned students only" icon="fa-chart-bar" bodyClass="faculty-page reports-page">
      {error && <PageError message={error} onRetry={() => activeReport && generateReport(activeReport)} />}

      <div className="row g-3 mb-4">
        {REPORT_TYPES.map((r) => (
          <div key={r.key} className="col-md-4">
            <div
              className={`content-card h-100 ${activeReport === r.key ? 'report-card--active' : ''}`}
              style={{ cursor: 'pointer' }}
              onClick={() => generateReport(r.key)}
            >
              <div className="p-3 text-center">
                <div className={`stat-icon ${r.color} mx-auto mb-2`} style={{ width: 48, height: 48, fontSize: '1.3rem' }}>
                  <i className={`fa ${r.icon}`}></i>
                </div>
                <div className="fw-semibold mb-1">{r.title}</div>
                <p className="text-muted mb-3" style={{ fontSize: '0.82rem' }}>{r.desc}</p>
                <button
                  className={`btn btn-sm ${activeReport === r.key ? 'btn-success' : 'btn-outline-success'}`}
                  onClick={(e) => { e.stopPropagation(); generateReport(r.key) }}
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

      {activeReport && (
        <div className="content-card interntrack-report-print" id="report-output" ref={printRef}>
          <div className="content-card-header d-print-none">
            <i className={`fa ${REPORT_TYPES.find((r) => r.key === activeReport)?.icon}`}></i>
            <h6>{REPORT_TYPES.find((r) => r.key === activeReport)?.title}</h6>
            {generatedAt && <small className="ms-auto text-muted">Generated: {formatManilaDateTime(generatedAt)}</small>}
            <button className="btn btn-sm btn-outline-success ms-2" onClick={handleExportCsv} disabled={!reportData}>
              <i className="fa fa-file-csv me-1"></i>Export CSV
            </button>
            <button className="btn btn-sm btn-outline-secondary ms-2" onClick={handlePrint}>
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
            {loading ? (
              <div className="text-center py-4"><InternTrackLoader /></div>
            ) : reportData ? (
              <>
                {activeReport === 'student-summary' && (
                  <StudentSummaryTable
                    data={reportData}
                    empty={<EmptyState icon="fa-users" title="No assigned students" message="Reports will appear once students are mapped to you." />}
                  />
                )}
                {activeReport === 'compliance' && (
                  <ComplianceTable
                    data={reportData}
                    empty={<EmptyState icon="fa-folder-open" title="No compliance rows" message="No assigned internships to evaluate." />}
                  />
                )}
                {activeReport === 'performance' && <PerformanceTable data={reportData} />}
              </>
            ) : (
              <EmptyState title="Generate a report" message='Click "Generate" on a report card above.' />
            )}
          </div>
        </div>
      )}
      <ReportExportModal preview={exportPreview} onClose={() => setExportPreview(null)} />
    </Layout>
  )
}

export default FacultyReports

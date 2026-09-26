import ComplianceApprovedProgress from '../ComplianceApprovedProgress'
import ComplianceRequirementsStatus, { normalizeRequirementStatuses } from '../ComplianceRequirementsStatus'
import StatusChip from '../StatusChip'
import { displayLabel } from '../../utils/displayLabel'

/**
 * Report tables shared by Faculty and Coordinator reports. Presentation only:
 * every value is rendered exactly as the report API returns it.
 *
 * Column classes feed the shared print CSS (utils/reportPrint.js):
 *   it-col-num / it-col-status / it-col-progress keep short columns compact and
 *   on one line; Student, Program and Company take the remaining width.
 */
export function StudentSummaryTable({ data, empty = null }) {
  const rows = data.students ?? []
  const docsTotal = data.docs_total ?? rows[0]?.required_docs ?? null
  if (rows.length === 0) return empty
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle report-table" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr>
            <th className="it-col-num">#</th>
            <th>Student</th>
            <th>Program</th>
            <th>Company</th>
            <th className="it-col-status">Status</th>
            <th className="it-col-num">Hours</th>
            <th className="it-col-progress">Progress</th>
            <th className="it-col-num">Days</th>
            <th className="it-col-num">Journals ✓</th>
            <th className="it-col-num">Docs ✓</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              <td className="it-col-num">{i + 1}</td>
              <td>
                <div className="fw-semibold">{r.student_name}</div>
                <div className="text-muted">{r.student_number}</div>
              </td>
              <td>{displayLabel(r.program, '—')}</td>
              <td>{r.company || '—'}</td>
              <td className="it-col-status"><StatusChip status={r.status} /></td>
              <td className="it-col-num">{r.hours_rendered}/{r.target_hours}</td>
              <td className="it-col-progress">
                <div className="progress" style={{ height: '6px', minWidth: '80px' }}>
                  <div className="progress-bar bg-success" style={{ width: `${r.progress_pct}%` }}></div>
                </div>
                <small>{r.progress_pct}%</small>
              </td>
              <td className="it-col-num">{r.validated_days}</td>
              <td className="it-col-num">{r.approved_journals}</td>
              <td className="it-col-num">{r.approved_docs}/{r.required_docs ?? docsTotal ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function ComplianceTable({ data, empty = null }) {
  const rows = data.rows ?? []
  if (rows.length === 0) return empty
  return (
    <div className="table-responsive">
      <table className="table table-sm table-bordered align-middle report-table" style={{ fontSize: '0.82rem' }}>
        <thead className="table-light">
          <tr>
            <th className="it-col-num">#</th>
            <th className="it-col-text">Student</th>
            <th className="it-col-text">Program</th>
            <th className="it-col-progress">Compliance</th>
            <th>Requirements Status</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              <td className="it-col-num">{i + 1}</td>
              <td className="fw-semibold">{r.student_name}</td>
              <td>{displayLabel(r.program, '—')}</td>
              <td className="it-col-progress">
                <ComplianceApprovedProgress
                  pct={r.compliance_pct}
                  approved={r.approved_docs}
                  required={r.required_docs}
                  requirements={normalizeRequirementStatuses(r)}
                />
              </td>
              <td>
                <ComplianceRequirementsStatus row={r} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

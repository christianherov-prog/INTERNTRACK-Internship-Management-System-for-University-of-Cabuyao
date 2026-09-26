import { Link } from 'react-router-dom'
import { CCS_DEMO, displayPersonName } from './ccsMockData'
import { DEMO_MODULES } from './demoModules'

const PEOPLE = [
  { role: 'Student', person: CCS_DEMO.student, extra: CCS_DEMO.student.student_number },
  { role: 'Faculty', person: CCS_DEMO.faculty, extra: CCS_DEMO.faculty.faculty_number },
  { role: 'Coordinator', person: CCS_DEMO.coordinator, extra: CCS_DEMO.coordinator.faculty_number },
  { role: 'Supervisor', person: CCS_DEMO.supervisor, extra: CCS_DEMO.supervisor.faculty_number },
  { role: 'Director', person: CCS_DEMO.director, extra: CCS_DEMO.director.faculty_number },
]

export default function DemoHome() {
  const intern = CCS_DEMO.internship

  return (
    <>
      <div className="alert alert-info d-flex align-items-start gap-2">
        <i className="fa fa-info-circle mt-1"></i>
        <div>
          This is a <strong>standalone demo</strong> with hardcoded CCS sample data.
          It does not sign in, call the API, or use the live database.
          Module pages will be filled in on later passes.
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-link"></i>
          <h6>Connected CCS sample chain</h6>
        </div>
        <div className="p-3">
          <p className="text-muted small mb-3">
            {CCS_DEMO.department.name} ({CCS_DEMO.department.code}) · {CCS_DEMO.program.code} · {CCS_DEMO.company.company_name}
          </p>
          <div className="row g-3">
            {PEOPLE.map((row) => (
              <div className="col-md-6 col-xl" key={row.role}>
                <div className="border rounded p-3 h-100">
                  <div className="text-muted small">{row.role}</div>
                  <div className="fw-semibold">{displayPersonName(row.person)}</div>
                  <div className="small">{row.extra}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-briefcase"></i>
          <h6>Shared internship record</h6>
        </div>
        <div className="table-responsive">
          <table className="table table-sm mb-0" style={{ fontSize: '0.85rem' }}>
            <tbody>
              <tr><th>Internship ID</th><td>{intern.id}</td></tr>
              <tr><th>Student</th><td>{displayPersonName(intern.student)} ({intern.student.student_number})</td></tr>
              <tr><th>Faculty</th><td>{displayPersonName(intern.faculty)}</td></tr>
              <tr><th>Coordinator</th><td>{displayPersonName(intern.coordinator)}</td></tr>
              <tr><th>Supervisor</th><td>{displayPersonName(intern.supervisor)}</td></tr>
              <tr><th>Company</th><td>{intern.company.company_name}</td></tr>
              <tr><th>Status / hours</th><td>{intern.status} · {intern.total_hours_rendered} / {intern.target_hours}</td></tr>
              <tr><th>Term</th><td>{intern.term}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-th-large"></i>
          <h6>Modules (placeholders until pass 2)</h6>
        </div>
        <div className="p-3 d-flex flex-wrap gap-2">
          {DEMO_MODULES.map((item) => (
            <Link key={item.to} to={item.to} className="btn btn-sm btn-outline-primary">
              <i className={`fa ${item.icon} me-1`}></i>{item.text}
            </Link>
          ))}
        </div>
      </div>
    </>
  )
}

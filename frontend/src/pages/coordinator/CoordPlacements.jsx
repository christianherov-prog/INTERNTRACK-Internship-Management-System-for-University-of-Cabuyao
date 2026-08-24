import { useEffect, useState } from 'react'
import Layout from '../../components/Layout'
import PageError from '../../components/PageError'
import api from '../../services/api'
import { useCurrentTerm } from '../../hooks/useCurrentTerm'
import { formatStudentName } from '../../utils/formatName'

function CoordPlacements({ embedded = false }) {
  const currentTerm = useCurrentTerm()
  const [applications, setApplications] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [updatingId, setUpdatingId] = useState(null)
  const [successMsg, setSuccessMsg] = useState(null)
  const [search, setSearch] = useState("")
  const [programFilter, setProgramFilter] = useState("all")
  const [statusFilter, setStatusFilter] = useState("all")
  const [sectionFilter, setSectionFilter] = useState("all")

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/coordinator/applications')
      setApplications(res.data.applications || [])
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load applications.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const updateStatus = async (appId, newStatus) => {
    setUpdatingId(appId)
    setSuccessMsg(null)
    setError(null)
    try {
      await api.patch(`/coordinator/applications/${appId}/status`, { status: newStatus })
      setSuccessMsg('Application status updated.')
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update application.')
    } finally {
      setUpdatingId(null)
    }
  }

  const Wrapper = embedded ? 'div' : Layout;
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "Student Placements", subtitle: currentTerm, icon: "fa-paper-plane", bodyClass: "coordinator-page" };

  // Define programs
  const programs = ["all", ...new Set(applications.map(a => (typeof a.student?.student_profile?.program === 'string' ? a.student?.student_profile?.program : a.student?.student_profile?.program?.name || a.student?.student_profile?.program?.code) || "—").filter(s => s !== "—"))]

  // FIXED: Define sections by extracting them from the applications data
  const sections = ["all", ...new Set(applications.map(a => a.student?.student_profile?.section || "—").filter(s => s !== "—"))]

  const filtered = applications.filter(app => {
    const name = formatStudentName(app).toLowerCase()
    const prog = (typeof app.student?.student_profile?.program === 'string' ? app.student?.student_profile?.program : app.student?.student_profile?.program?.name || app.student?.student_profile?.program?.code) || "—"

    // FIXED: Define 'sec' for the filter condition
    const sec = app.student?.student_profile?.section || "—"
    const status = app.status.toLowerCase()

    return (!search || name.includes(search.toLowerCase()))
      && (programFilter === "all" || prog === programFilter)
      && (sectionFilter === "all" || sec === sectionFilter)
      && (statusFilter === "all" || status.includes(statusFilter))
  })

  return (
    <Wrapper {...wrapperProps}>
      {error && <PageError message={error} onRetry={load} />}
      {successMsg && (
        <div className="alert alert-success alert-dismissible mb-3">
          {successMsg}
          <button className="btn-close" onClick={() => setSuccessMsg(null)}></button>
        </div>
      )}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search by name…" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 170 }} value={programFilter} onChange={e => setProgramFilter(e.target.value)}>
          {programs.map(p => <option key={p} value={p}>{p === "all" ? "All Programs" : p}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 150 }} value={sectionFilter} onChange={e => setSectionFilter(e.target.value)}>
          {sections.map(s => <option key={s} value={s}>{s === "all" ? "Sections" : s}</option>)}
        </select>

        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {loading ? (
        <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      ) : (
        <div className="content-card">
          <div className="content-card-header">
            <i className="fa fa-paper-plane"></i>
            <h6>Student Placements</h6>
            <span className="ms-auto badge bg-secondary">{filtered.length} application{filtered.length !== 1 ? "s" : ""}</span>
          </div>
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Program</th>
                  <th>Company</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr><td colSpan={5} className="text-center py-4 text-muted">No applications match the selected filters.</td></tr>
                ) : filtered.map(app => (
                  <tr key={app.id}>
                    <td>
                      <div className="fw-semibold">{formatStudentName(app)}</div>
                      <div className="small text-muted">{app.student?.email}</div>
                    </td>
                    <td>{(typeof app.student?.student_profile?.program === 'string' ? app.student?.student_profile?.program : app.student?.student_profile?.program?.name || app.student?.student_profile?.program?.code) || '—'}</td>
                    <td className="fw-semibold">{app.company?.company_name}</td>
                    <td>
                      <span className={`badge ${app.status.includes('rejected') ? 'bg-danger' : app.status.includes('pending') ? 'bg-warning text-dark' : 'bg-success'}`}>
                        {app.status.replace(/_/g, ' ').toUpperCase()}
                      </span>
                    </td>
                    <td>
                      <div className="d-flex gap-2">
                        {app.status === 'pending_coordinator_approval' && (
                          <>
                            <button className="btn btn-sm btn-success" onClick={() => updateStatus(app.id, 'approved_to_apply')} disabled={updatingId === app.id}>
                              Approve to Apply
                            </button>
                            <button className="btn btn-sm btn-danger" onClick={() => updateStatus(app.id, 'rejected_by_company')} disabled={updatingId === app.id}>
                              Reject
                            </button>
                          </>
                        )}
                        {app.status === 'approved_to_apply' && (
                          <>
                            <button className="btn btn-sm btn-primary" onClick={() => updateStatus(app.id, 'accepted_by_company')} disabled={updatingId === app.id}>
                              Mark Accepted
                            </button>
                            <button className="btn btn-sm btn-danger" onClick={() => updateStatus(app.id, 'rejected_by_company')} disabled={updatingId === app.id}>
                              Mark Rejected
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </Wrapper>
  )
}

export default CoordPlacements
import { formatYearSection } from "../../utils/formatSection"
import { useEffect, useState, Fragment } from "react"
import { Link } from "react-router-dom"
import Layout from "../../components/Layout"
import PageError from "../../components/PageError"
import api from "../../services/api"
import { unwrapList } from "../../utils/apiList"
import { CURRENT_TERM } from "../../config/term"
import { AuthenticatedFileImage, AuthenticatedFileLink } from "../../components/AuthenticatedFile"
import { useCurrentTerm } from "../../hooks/useCurrentTerm"
import FormPreviewModal from "../../components/portfolio/FormPreviewModal"
import { formatStudentName as studentName } from "../../utils/formatName"

function studentSection(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  return formatYearSection(p?.section) || "—"
}

function studentCourse(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  return (typeof p?.program === 'string' ? p?.program : p?.program?.name || p?.program?.code) || (typeof row?.program === 'string' ? row?.program : row?.program?.name || row?.program?.code) || "—"
}

const statusBadge = (status) => {
  const map = { ongoing: "badge-active", active: "badge-active", for_evaluation: "badge-pending", completed: "badge-active", placed: "badge-pending", pending_placement: "badge-pending", unplaced: "badge-inactive" }
  return <span className={`badge-status ${map[status] ?? "badge-inactive"}`}>{(status || "—").replace(/_/g, " ")}</span>
}

const attStatusBadge = (s) => {
  const map = { pending: "bg-warning text-dark", validated: "bg-success", rejected: "bg-danger", flagged: "bg-secondary" }
  return <span className={`badge ${map[s] || "bg-secondary"}`}>{s}</span>
}

// ─── Journal Review Modal ─────────────────────────────────────────────────────
function ReviewModal({ journal, onClose, onSubmit, onPreview, processing }) {
  const [action, setAction] = useState("approved")
  const [feedback, setFeedback] = useState("")
  const [score, setScore] = useState("")
  const isImage = journal.file_path && !journal.file_path.endsWith(".pdf")
  const isPdf = journal.file_path && journal.file_path.endsWith(".pdf")
  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: "rgba(0,0,0,0.45)" }}>
      <div className="modal-dialog modal-xl modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title"><i className="fa fa-book me-2 text-primary"></i>Review Journal — Week {journal.week_number ?? journal.entry_number}</h5>
            <button className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            <div className="mb-3 p-3 rounded" style={{ background: "#f8fafc", fontSize: "0.88rem" }}>
              <div className="fw-semibold mb-1">Week {journal.week_number ?? journal.entry_number}</div>
              {journal.notes && <p className="mb-0 text-muted"><strong>Notes:</strong> {journal.notes}</p>}
              {journal.faculty_feedback && (
                <div className="mt-2 alert alert-info py-2 mb-0"><strong>Previous Feedback:</strong> {journal.faculty_feedback}</div>
              )}
            </div>
            <div className="mb-3 text-center">
              <button type="button" onClick={onPreview} className="btn btn-outline-primary">
                <i className="fa fa-eye me-2"></i>Preview Journal Form
              </button>
              {isPdf && (
                <AuthenticatedFileLink path={journal.file_path} className="btn btn-outline-danger ms-2">
                  <i className="fa fa-file-pdf me-2"></i>Open PDF
                </AuthenticatedFileLink>
              )}
            </div>

            {journal.file_path && isImage && (
              <div className="mb-3 text-center">
                <AuthenticatedFileImage path={journal.file_path} alt="Journal" style={{ maxWidth: "100%", maxHeight: "500px", border: "1px solid #ddd", borderRadius: "4px" }} />
              </div>
            )}

            {!journal.file_path && (
              <div className="alert alert-info">No supplementary file uploaded for this journal entry.</div>
            )}
            <div className="mb-3">
              <label className="form-label fw-semibold">Action</label>
              <div className="d-flex gap-3">
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="fac_approve" checked={action === "approved"} onChange={() => setAction("approved")} />
                  <label className="form-check-label" htmlFor="fac_approve">✅ Approve</label>
                </div>
                <div className="form-check">
                  <input type="radio" className="form-check-input" id="fac_revise" checked={action === "needs_revision"} onChange={() => setAction("needs_revision")} />
                  <label className="form-check-label" htmlFor="fac_revise">🔄 Needs Revision</label>
                </div>
              </div>
            </div>
            {action === "approved" && (
              <div className="mb-3">
                <label className="form-label fw-semibold">Score (Optional)</label>
                <input
                  type="number"
                  className="form-control"
                  placeholder="e.g. 100"
                  min="0"
                  max="100"
                  value={score}
                  onChange={e => setScore(e.target.value)}
                />
                <div className="form-text">This score will be saved for the student's record.</div>
              </div>
            )}
            <div>
              <label className="form-label fw-semibold">Feedback {action === "needs_revision" && <span className="text-danger">*</span>}</label>
              <textarea className="form-control" rows={3} value={feedback} onChange={e => setFeedback(e.target.value)} placeholder="Write feedback for the student…"></textarea>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button className="btn btn-primary" onClick={() => onSubmit(journal.id, action, feedback, score)} disabled={processing || (action === "needs_revision" && !feedback.trim())}>
              <i className={`fa fa-${processing ? "spinner fa-spin" : "check"} me-2`}></i>Submit Review
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

// ─── Tab: Students ────────────────────────────────────────────────────────────
function TabStudents() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [archived, setArchived] = useState(false)
  const [search, setSearch] = useState("")
  const [programFilter, setProgramFilter] = useState("all")
  const [sectionFilter, setSectionFilter] = useState("all")
  const [sexFilter, setSexFilter] = useState("all")
  const [placementFilter, setPlacementFilter] = useState("all")

  const [busyId, setBusyId] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)

  const fetchStudents = () => {
    setLoading(true); setError(null)
    api.get("/faculty/assigned-students", { params: { archived: archived ? 1 : 0 } })
      .then(res => setRows(unwrapList(res.data).items || []))
      .catch(err => { setError(err.response?.data?.message || "Failed to load students."); setRows([]) })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchStudents()
  }, [archived]) // Re-fetch when archived toggle changes

  const toggleArchive = async (row) => {
    const sid = row.student?.id; if (!sid) return
    setBusyId(sid); setMessage(null)
    try {
      await api.patch(`/faculty/students/${sid}/archive`, { archived: !archived })
      setMessage({ type: "success", text: archived ? "Student restored." : "Student archived." })
      fetchStudents()
    } catch (err) {
      setMessage({ type: "danger", text: err.response?.data?.message || "Action failed." })
    } finally {
      setBusyId(null)
    }
  }

  const programs = ["all", ...new Set(rows.map(r => studentCourse(r)).filter(s => s !== "—"))]
  const sections = ["all", ...new Set(rows.map(r => studentSection(r)).filter(s => s !== "—"))]
  const filtered = rows.filter(r => {
    const name = studentName(r).toLowerCase()
    const sec = studentSection(r)
    const prog = studentCourse(r)
    const sex = r.student?.sex || "—"
    const placed = !!r.company && r.status !== "unplaced"
    return (!search || name.includes(search.toLowerCase()))
      && (programFilter === "all" || prog === programFilter)
      && (sectionFilter === "all" || sec === sectionFilter)
      && (sexFilter === "all" || sex.toLowerCase() === sexFilter.toLowerCase())
      && (placementFilter === "all" || (placementFilter === "placed" && placed) || (placementFilter === "unplaced" && !placed))
  })

  return (
    <>
      {error && <PageError message={error} onRetry={fetchStudents} />}
      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}

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
        <select className="form-select form-select-sm text-secondary" style={{ width: 130 }} value={sexFilter} onChange={e => setSexFilter(e.target.value)}>
          <option value="all">All Sexes</option><option value="Male">Male</option><option value="Female">Female</option>
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={placementFilter} onChange={e => setPlacementFilter(e.target.value)}>
          <option value="all">All Status</option><option value="placed">Placed</option><option value="unplaced">Pending / Unplaced</option>
        </select>

        <div className="ms-auto btn-group">
          <button className={`btn btn-sm ${!archived ? "btn-primary" : "btn-outline-secondary"}`} onClick={() => setArchived(false)}>Active</button>
          <button className={`btn btn-sm ${archived ? "btn-secondary" : "btn-outline-secondary"}`} onClick={() => setArchived(true)}>Archived</button>
        </div>
      </div>



      {loading ? <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
        : filtered.length === 0 ? (
          <div className="content-card">
            <div className="text-center py-5 text-muted">
              <i className="fa fa-users fa-3x mb-3 d-block opacity-25"></i>
              {rows.length === 0 ? (archived ? "No archived students." : "No students assigned yet.") : "No students match the selected filters."}
            </div>
          </div>
        ) : (
          <div className="content-card mb-4">
            <div className="content-card-header">
              <i className="fa fa-users"></i><h6>Student Roster</h6>
              <span className="ms-auto badge bg-secondary">{filtered.length} student{filtered.length !== 1 ? "s" : ""}</span>
            </div>
            <div className="table-card">
              <div className="table-responsive">
                <table className="table table-hover mb-0 align-middle">
                  <thead><tr><th>Name</th><th>Student ID</th><th>Program</th><th>Section</th><th>Company</th><th>Supervisor</th><th style={{ width: '15%' }}>OJT Hours</th><th>Status</th><th>Actions</th></tr></thead>
                  <tbody>
                    {filtered.map(row => {
                      const profile = row.student?.student_profile || row.student?.studentProfile
                      const supervisorName = row.supervisor?.supervisorProfile?.full_name || row.supervisor?.supervisor_profile?.full_name || "—"
                      const totalHours = row.total_hours_rendered ?? 0
                      const targetHours = row.target_hours ?? 500
                      const progressPct = targetHours > 0 ? Math.min(100, Math.round((totalHours / targetHours) * 100)) : 0

                      const progressColor = (pct) => {
                        if (pct >= 75) return '#14b8a6'
                        if (pct >= 40) return '#f59e0b'
                        return '#ef4444'
                      }

                      return (
                        <Fragment key={row.id}>
                          <tr>
                            <td className="fw-semibold">
                              {studentName(row)}
                            </td>
                            <td>{row.student?.student_number || row.student?.email || profile?.student_number || "—"}</td>
                            <td>{(typeof row.program === 'string' ? row.program : row.program?.name || row.program?.code) || (typeof profile?.program === 'string' ? profile?.program : profile?.program?.name || profile?.program?.code) || "—"}</td>
                            <td>{studentSection(row)}</td>
                            <td>{row.company ? (row.company?.company_name || row.company?.name || "—") : <span className="text-muted fst-italic">Not placed</span>}</td>
                            <td>{row.company ? supervisorName : "—"}</td>
                            <td>
                              <div className="d-flex justify-content-between mb-1" style={{ fontSize: '0.75rem' }}>
                                <span className="fw-semibold">{totalHours} / {targetHours}</span>
                                <span className="fw-bold" style={{ color: progressColor(progressPct) }}>{progressPct}%</span>
                              </div>
                              <div className="progress" style={{ height: '6px', borderRadius: 4 }}>
                                <div className="progress-bar" role="progressbar" style={{ width: `${progressPct}%`, background: progressColor(progressPct) }}></div>
                              </div>
                            </td>
                            <td>{statusBadge(row.status)}</td>
                            <td className="d-flex gap-1" onClick={e => e.stopPropagation()}>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-info"
                                title="DTR Preview (FO-30)"
                                onClick={() => setPreviewModal({
                                  type: 'dtr',
                                  data: {
                                    studentName: studentName(row),
                                    program: studentCourse(row),
                                    companyName: row.company?.company_name || '—',
                                    companyLogoPath: row.company?.company_logo_path || '',
                                    supervisorName: supervisorName,
                                    logs: row.attendance_logs || [],
                                    month: new Date().toISOString().slice(0, 7)
                                  }
                                })}
                              >
                                DTR Preview (FO-30)
                                <i className="fa fa-clock"></i>
                              </button>
                              <button type="button" className={`btn btn-sm ${archived ? "btn-outline-success" : "btn-outline-secondary"}`} title={archived ? "Unarchive" : "Archive"} disabled={busyId === row.student?.id} onClick={() => toggleArchive(row)}>
                                <i className={`fa ${archived ? "fa-box-open" : "fa-box-archive"} ${busyId === row.student?.id ? "fa-spin" : ""}`}></i>
                                {archived ? "Unarchive" : "Archive"}
                              </button>
                            </td>
                          </tr>

                        </Fragment>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
      />
    </>
  )
}

// ─── Tab: Journal Queue ───────────────────────────────────────────────────────
function TabJournals() {
  const currentTerm = useCurrentTerm()
  const [journals, setJournals] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage] = useState(null)
  const [modal, setModal] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)

  const fetchJournals = () => {
    setLoading(true); setError(null)
    api.get("/faculty/journals")
      .then(res => setJournals(unwrapList(res.data).items))
      .catch(err => { setError(err.response?.data?.message || "Failed to load journals."); setJournals([]) })
      .finally(() => setLoading(false))
  }
  useEffect(() => { fetchJournals() }, [])

  const handleReview = async (id, action, feedback, score) => {
    setProcessing(true)
    try {
      const payload = { action, feedback }
      if (action === "approved" && score !== "") {
        payload.score = Number(score)
      }
      await api.patch(`/faculty/journals/${id}/review`, payload)
      setMessage({ type: action === "approved" ? "success" : "info", text: `Journal ${action === "approved" ? "approved" : "returned for revision"}.` })
      setModal(null); fetchJournals()
    } catch (err) { setMessage({ type: "danger", text: err.response?.data?.message ?? "Review failed." }) }
    finally { setProcessing(false) }
  }

  const handlePreviewJournal = (j) => {
    const profile = j.internship?.student?.studentProfile || j.internship?.student?.student_profile
    const name = profile ? `${profile.first_name} ${profile.last_name}` : '—'
    setPreviewModal({
      type: 'journal',
      data: {
        studentName: name,
        program: profile?.program?.name || profile?.program?.code || profile?.program || '—',
        companyName: j.internship?.company?.company_name || '—',
        weekNumber: j.week_number ?? j.entry_number,
        date: j.date,
        endDate: j.end_date,
        accomplishment: j.activities_summary,
        difficulties: j.challenges,
        insights: j.learnings,
      }
    })
  }

  return (
    <>
      {error && <PageError message={error} onRetry={fetchJournals} />}
      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}
      {modal && <ReviewModal journal={modal} onClose={() => setModal(null)} onSubmit={handleReview} onPreview={() => handlePreviewJournal(modal)} processing={processing} />}
      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-book"></i><h6>Pending Journal Reviews</h6>
          <span className="ms-auto badge bg-warning text-dark">{journals.length} pending</span>
        </div>
        <div className="table-card">
          {loading ? <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            : journals.length === 0 && !error ? (
              <div className="text-center py-4 text-muted"><i className="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>All journals reviewed!</div>
            ) : journals.map(j => {
              const profile = j.internship?.student?.studentProfile
              const name = profile ? `${profile.first_name} ${profile.last_name}` : "—"
              return (
                <div key={j.id} className="p-3 border-bottom d-flex align-items-start justify-content-between">
                  <div>
                    <div className="fw-semibold mb-1">{name} · <span className="text-primary">Week {j.week_number ?? j.entry_number}</span></div>
                    <div className="text-muted" style={{ fontSize: "0.82rem" }}>{j.date}</div>
                    {j.notes && <p className="mt-1 mb-0 text-muted" style={{ fontSize: "0.85rem" }}>{j.notes?.substring(0, 100)}…</p>}
                    <span className={`badge mt-1 ${j.status === "approved" ? "bg-success" : j.status === "needs_revision" ? "bg-warning text-dark" : "bg-secondary"}`}>{j.status}</span>
                  </div>
                  <button className="btn btn-sm btn-primary ms-3 flex-shrink-0" onClick={() => setModal(j)}>
                    <i className="fa fa-pen me-1"></i>Review
                  </button>
                </div>
              )
            })}
        </div>
      </div>
      
      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
      />
    </>
  )
}

// ─── Tab: Attendance Monitor ──────────────────────────────────────────────────
function TabAttendance() {
  const [rows, setRows] = useState([])
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [statusFilter, setStatusFilter] = useState("all")
  const [internshipId, setInternshipId] = useState("")

  const fetchAttendance = () => {
    setLoading(true); setError(null)
    const params = {}
    if (statusFilter && statusFilter !== "all") params.status = statusFilter
    if (internshipId) params.internship_id = internshipId
    api.get("/faculty/attendance", { params })
      .then(res => setRows(unwrapList(res.data).items))
      .catch(err => { setError(err.response?.data?.message || "Failed to load attendance."); setRows([]) })
      .finally(() => setLoading(false))
  }
  useEffect(() => {
    api.get("/faculty/assigned-students").then(res => setStudents(unwrapList(res.data).items)).catch(() => setStudents([]))
  }, [])
  useEffect(() => { fetchAttendance() }, [statusFilter, internshipId])

  return (
    <>
      {error && <PageError message={error} onRetry={fetchAttendance} />}
      <div className="content-card mb-3">
        <div className="content-card-header"><i className="fa fa-filter"></i><h6>Filters</h6></div>
        <div className="p-3 row g-3">
          <div className="col-md-4">
            <label className="form-label fw-semibold">Student</label>
            <select className="form-select" value={internshipId} onChange={e => setInternshipId(e.target.value)}>
              <option value="">All assigned students</option>
              {students.map(s => {
                const p = s.student?.student_profile || s.student?.studentProfile
                const name = p ? `${p.first_name || ""} ${p.last_name || ""}`.trim() : (s.student?.username || `Internship #${s.id}`)
                return <option key={s.id} value={s.id}>{name}</option>
              })}
            </select>
          </div>
          <div className="col-md-4">
            <label className="form-label fw-semibold">Status</label>
            <select className="form-select" value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="validated">Validated</option>
              <option value="rejected">Rejected</option>
              <option value="flagged">Flagged</option>
            </select>
          </div>
        </div>
      </div>
      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-clock"></i><h6>Attendance &amp; Logged Hours</h6>
          <span className="ms-auto badge bg-secondary">{rows.length} record{rows.length === 1 ? "" : "s"}</span>
        </div>
        <div className="table-card">
          {loading ? <div className="text-center py-4"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            : rows.length === 0 ? <div className="text-center py-4 text-muted">No attendance records for the selected filters.</div>
              : (
                <div className="table-responsive">
                  <table className="table table-hover mb-0">
                    <thead><tr><th>Student</th><th>Company</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody>
                      {rows.map(log => {
                        const p = log?.internship?.student?.student_profile || log?.internship?.student?.studentProfile
                        const name = p ? `${p.first_name || ""} ${p.last_name || ""}`.trim() : (log?.internship?.student?.username || "—")
                        return (
                          <tr key={log.id}>
                            <td className="fw-semibold">{name}</td>
                            <td>{log.internship?.company?.company_name || "—"}</td>
                            <td>{log.date ? String(log.date).slice(0, 10) : "—"}</td>
                            <td>{log.clock_in || "—"}</td>
                            <td>{log.clock_out || "—"}</td>
                            <td>{log.hours_rendered != null ? Number(log.hours_rendered).toFixed(2) : "—"}</td>
                            <td>{attStatusBadge(log.status)}</td>
                            <td className="text-muted" style={{ fontSize: "0.85rem", maxWidth: 180 }}>{log.remarks || "—"}</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              )}
        </div>
        <p className="text-muted px-3 pb-3 mb-0" style={{ fontSize: "0.8rem" }}>Read-only monitoring. Industry supervisors validate attendance; faculty review logged hours here.</p>
      </div>
    </>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────
const TABS = [
  { key: "students", label: "Student Roster", icon: "fa-users" },
  { key: "journals", label: "Journal Review Queue", icon: "fa-book" },
  { key: "attendance", label: "Attendance Monitor", icon: "fa-calendar-check" },
]

function FacultyAssignedStudents({ embedded = false }) {
  const [tab, setTab] = useState("students")

  const Wrapper = embedded ? 'div' : Layout;
  const wrapperProps = embedded ? { className: "embedded-view" } : { title: "Assigned Students", subtitle: CURRENT_TERM, icon: "fa-users", bodyClass: "faculty-page" };

  return (
    <Wrapper {...wrapperProps}>
      {/* Tab bar */}
      <ul className="nav nav-tabs mb-4">
        {TABS.map(t => (
          <li key={t.key} className="nav-item">
            <button
              className={`nav-link d-flex align-items-center gap-2 ${tab === t.key ? "active" : ""}`}
              onClick={() => setTab(t.key)}
            >
              <i className={`fa ${t.icon}`}></i>
              {t.label}
            </button>
          </li>
        ))}
      </ul>

      {tab === "students" && <TabStudents />}
      {tab === "journals" && <TabJournals />}
      {tab === "attendance" && <TabAttendance />}
    </Wrapper>
  )
}

export default FacultyAssignedStudents
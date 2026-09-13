import { formatYearSection } from "../../utils/formatSection"
import { useEffect, useState, Fragment } from "react"
import { Link } from "react-router-dom"
import Layout from "../../components/Layout"
import PageError from "../../components/PageError"
import api from "../../services/api"
import { unwrapList } from "../../utils/apiList"
import { CURRENT_TERM } from "../../config/term"
import { useCachedPage } from "../../hooks/useCachedPage"
import FormPreviewModal from "../../components/portfolio/FormPreviewModal"
import { formatStudentName as studentName } from "../../utils/formatName"
import InternTrackLoader from '../../components/InternTrackLoader'
import { loadFacultyFo31Preview, openOfficialFo30, openOfficialFo31 } from "../../utils/officialForm"
import { useConfirm } from '../../contexts/ConfirmContext'
import AsyncButton from '../../components/AsyncButton'
import { formatDisplayDate } from '../../utils/manilaTime'

function studentSection(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  return formatYearSection(p?.section) || "—"
}

function studentCourse(row) {
  const p = row?.student?.student_profile || row?.student?.studentProfile
  return (typeof p?.program === 'string' ? p?.program : p?.program?.name || p?.program?.code) || (typeof row?.program === 'string' ? row?.program : row?.program?.name || row?.program?.code) || "—"
}

function journalSubmitterName(j) {
  if (j?.student_name) return j.student_name
  const intern = j?.internship
  const fromIntern = intern ? studentName(intern) : "—"
  if (fromIntern && fromIntern !== "—") return fromIntern
  if (intern?.student) {
    const fromStudent = studentName(intern.student)
    if (fromStudent && fromStudent !== "—") return fromStudent
  }
  return intern?.student?.student_number || intern?.student?.email || "—"
}

function journalExcerpt(j) {
  const raw = String(j?.activities_summary || j?.notes || j?.learnings || j?.challenges || "")
    .replace(/\s+/g, " ")
    .trim()
  if (!raw) return ""
  return raw.length > 140 ? `${raw.slice(0, 140)}…` : raw
}

function journalStatusClass(status) {
  if (status === "approved") return "bg-success"
  if (status === "needs_revision") return "bg-warning text-dark"
  return "bg-secondary"
}

function journalStudentKey(j) {
  return j.internship?.student_id || j.internship?.student?.id || `name:${journalSubmitterName(j)}`
}

function latestJournalDate(entries) {
  return entries.reduce((latest, j) => {
    const d = String(j.date || "")
    return d > latest ? d : latest
  }, "")
}

function groupItemsByStudent(items, getMeta, sortEntries) {
  const groups = []
  const indexByKey = new Map()
  for (const item of items) {
    const meta = getMeta(item)
    let group = indexByKey.get(meta.key)
    if (!group) {
      group = { ...meta, entries: [] }
      indexByKey.set(meta.key, group)
      groups.push(group)
    }
    group.entries.push(item)
  }
  if (sortEntries) {
    for (const group of groups) sortEntries(group.entries)
  }
  return groups
}

function groupJournalsByStudent(journals) {
  return groupItemsByStudent(
    journals,
    (j) => ({
      key: journalStudentKey(j),
      studentId: j.internship?.student_id || j.internship?.student?.id,
      name: journalSubmitterName(j),
    }),
    (entries) => {
      entries.sort((a, b) => {
        const weekDiff = Number(b.week_number ?? b.entry_number ?? 0) - Number(a.week_number ?? a.entry_number ?? 0)
        if (weekDiff !== 0) return weekDiff
        return String(b.date || "").localeCompare(String(a.date || ""))
      })
    },
  )
}

function attendanceLogName(log) {
  const p = log?.internship?.student?.student_profile || log?.internship?.student?.studentProfile
  const fromProfile = p ? `${p.last_name || ""}, ${p.first_name || ""}`.trim().replace(/^,\s*|,\s*$/g, "") : ""
  return fromProfile || log?.internship?.student?.username || "—"
}

function groupAttendanceByStudent(logs) {
  return groupItemsByStudent(
    logs,
    (log) => ({
      key: log.internship?.student_id || log.internship?.student?.id || `name:${attendanceLogName(log)}`,
      name: attendanceLogName(log),
      company: log.internship?.company?.company_name || "—",
    }),
    (entries) => {
      entries.sort((a, b) => String(b.date || "").localeCompare(String(a.date || "")))
    },
  )
}

const statusBadge = (status) => {
  const map = { ongoing: "badge-active", active: "badge-active", for_evaluation: "badge-pending", completed: "badge-active", placed: "badge-pending", pending_placement: "badge-pending", unplaced: "badge-inactive" }
  return <span className={`badge-status ${map[status] ?? "badge-inactive"}`}>{(status || "—").replace(/_/g, " ")}</span>
}

const attStatusBadge = (s) => {
  const map = { pending: "bg-warning text-dark", validated: "bg-success", rejected: "bg-danger", flagged: "bg-secondary" }
  return <span className={`badge ${map[s] || "bg-secondary"}`}>{s}</span>
}

// ─── Tab: Students ────────────────────────────────────────────────────────────
function TabStudents() {
  const [archived, setArchived] = useState(false)
  const { loading, seed, run } = useCachedPage(`faculty:assigned-students:${archived ? 1 : 0}`)
  const [rows, setRows] = useState(() => seed ?? [])
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [search, setSearch] = useState("")
  const [programFilter, setProgramFilter] = useState("all")
  const [sectionFilter, setSectionFilter] = useState("all")
  const [sexFilter, setSexFilter] = useState("all")
  const [placementFilter, setPlacementFilter] = useState("all")

  const [busyId, setBusyId] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)
  const [previewBusy, setPreviewBusy] = useState(false)

  const fetchStudents = () => {
    setError(null)
    run(() => api.get("/faculty/assigned-students", { params: { archived: archived ? 1 : 0 } }).then(res => unwrapList(res.data).items || []))
      .then((next) => { if (next) setRows(next) })
      .catch((err) => {
        setError(err.response?.data?.message || "Failed to load students.")
      })
  }

  useEffect(() => {
    setRows(seed ?? [])
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
          <input className="form-control border-start-0 ps-0" placeholder="Search" value={search} onChange={e => setSearch(e.target.value)} />
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



      {loading && rows.length === 0 ? <div className="text-center py-5"><InternTrackLoader /></div>
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
                      const supervisorName = row.supervisor?.supervisorProfile?.full_name
                        || row.supervisor?.supervisor_profile?.full_name
                        || [row.supervisor?.supervisor_profile?.last_name, row.supervisor?.supervisor_profile?.first_name].filter(Boolean).join(', ')
                        || "—"
                      const totalHours = row.total_hours_rendered ?? 0
                      const targetHours = Number(row.target_hours) || 0
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
                                disabled={!row.id || previewBusy}
                                onClick={() => {
                                  if (!row.id) return
                                  setPreviewBusy(true)
                                  openOfficialFo30(row.id, setPreviewModal)
                                    .catch((err) => alert(err.response?.data?.message || 'Unable to load FO-30 preview.'))
                                    .finally(() => setPreviewBusy(false))
                                }}
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
        onDownload={previewModal?.onDownload}
      />
    </>
  )
}

// ─── Tab: Journal Queue ───────────────────────────────────────────────────────
function TabJournals() {
  const { loading, seed, run } = useCachedPage("faculty:assigned-journals")
  const [journals, setJournals] = useState(() => seed ?? [])
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [message, setMessage] = useState(null)
  const [reviewJournal, setReviewJournal] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [historyModal, setHistoryModal] = useState(null)
  const [historyData, setHistoryData] = useState([])
  const [loadingHistory, setLoadingHistory] = useState(false)

  const fetchJournals = () => {
    setError(null)
    run(() => api.get("/faculty/journals").then(res => unwrapList(res.data).items || []))
      .then((next) => { if (next) setJournals(next) })
      .catch((err) => {
        setError(err.response?.data?.message || "Failed to load journals.")
      })
  }
  useEffect(() => { fetchJournals() }, [])

  const handleReview = async (id, action, feedback) => {
    setProcessing(true)
    try {
      await api.patch(`/faculty/journals/${id}/review`, { action, feedback })
      setMessage({ type: action === "approved" ? "success" : "info", text: `Journal ${action === "approved" ? "approved" : "returned for revision"}.` })
      setPreviewModal(null)
      setReviewJournal(null)
      fetchJournals()
    } catch (err) { setMessage({ type: "danger", text: err.response?.data?.message ?? "Review failed." }) }
    finally { setProcessing(false) }
  }

  const openHistory = (studentId, studentName) => {
    if (!studentId) {
      alert("Cannot load history: student is missing on this journal.")
      return
    }
    setHistoryModal({ studentId, studentName })
    setLoadingHistory(true)
    api.get(`/faculty/students/${studentId}/journals`)
      .then(res => {
        const rows = Array.isArray(res.data) ? res.data : (res.data?.data || [])
        setHistoryData(rows)
      })
      .catch(() => alert("Failed to load history"))
      .finally(() => setLoadingHistory(false))
  }

  const handlePreviewJournal = (j) => {
    setReviewJournal(null)
    const internshipId = j.internship_id || j.internship?.id
    if (!internshipId) return
    openOfficialFo31(internshipId, {
      studentName: j.student_display_name || journalSubmitterName(j),
      program: j.program_name,
      companyName: j.internship?.company?.company_name,
      weekNumber: j.week_number ?? j.entry_number,
      date: j.date,
      endDate: j.end_date,
      accomplishment: j.activities_summary,
      difficulties: j.challenges,
      insights: j.learnings,
      studentSignaturePath: j.student_signature_path,
    }, setPreviewModal).catch((err) => alert(err.response?.data?.message || 'Unable to load FO-31 preview.'))
  }

  const openReview = (j) => {
    setReviewJournal(j)
    setPreviewLoading(true)
    setPreviewModal({ type: 'journal', data: {} })
    loadFacultyFo31Preview(j, setPreviewModal, {
      studentName: j.student_display_name || journalSubmitterName(j),
    })
      .catch((err) => {
        alert(err.response?.data?.message || 'Unable to load FO-31 preview.')
        setPreviewModal(null)
        setReviewJournal(null)
      })
      .finally(() => setPreviewLoading(false))
  }

  const closePreview = () => {
    setPreviewModal(null)
    setReviewJournal(null)
    setPreviewLoading(false)
  }

  return (
    <>
      {error && <PageError message={error} onRetry={fetchJournals} />}
      {message && <div className={`alert alert-${message.type} alert-dismissible mb-3`}>{message.text}<button className="btn-close" onClick={() => setMessage(null)}></button></div>}
      {historyModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ background: "rgba(0,0,0,0.45)" }}>
          <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Journal History — {historyModal.studentName}</h5>
                <button className="btn-close" onClick={() => setHistoryModal(null)}></button>
              </div>
              <div className="modal-body p-0">
                {loadingHistory ? (
                  <div className="p-5 text-center"><InternTrackLoader /></div>
                ) : historyData.length === 0 ? (
                  <div className="p-4 text-center text-muted">No past journals found.</div>
                ) : (
                  <ul className="list-group list-group-flush">
                    {historyData.map(h => (
                      <li key={h.id} className="list-group-item p-3">
                        <div className="d-flex justify-content-between">
                          <div className="fw-semibold text-primary">Week {h.week_number ?? h.entry_number}</div>
                          <span className={`badge ${journalStatusClass(h.status)}`}>{h.status}</span>
                        </div>
                        <div className="text-muted small mb-2">{h.date}{h.end_date ? ` — ${h.end_date}` : ""}</div>
                        {h.score != null && h.score !== "" && <div className="text-success small fw-bold"><i className="fa fa-check-circle me-1"></i>Score: {h.score}/100</div>}
                        {h.faculty_feedback && (
                          <div className="bg-light p-2 rounded small mt-2">
                            <strong>Feedback:</strong> {h.faculty_feedback}
                          </div>
                        )}
                        <button className="btn btn-sm btn-outline-secondary mt-2" onClick={() => handlePreviewJournal({ ...h, internship: h.internship || reviewJournal?.internship })}>
                          <i className="fa fa-eye me-1"></i>Preview Form
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
      <div className="content-card">
        <div className="content-card-header">
          <i className="fa fa-book"></i><h6>Pending Journal Reviews</h6>
          <span className="ms-auto badge bg-warning text-dark">{journals.length} pending</span>
        </div>
        <div className="table-card">
          {loading && journals.length === 0 ? <div className="text-center py-4"><InternTrackLoader /></div>
            : journals.length === 0 && !error ? (
              <div className="text-center py-4 text-muted"><i className="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>All journals reviewed!</div>
            ) : groupJournalsByStudent(journals).map(group => {
              const latestDate = latestJournalDate(group.entries)
              return (
                <div key={group.key} className="faculty-journal-queue-group border-bottom">
                  <div className="faculty-journal-queue-header d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2" style={{ background: "#f8fafc" }}>
                    <div className="fw-bold" style={{ fontSize: "0.98rem", lineHeight: 1.3 }}>{group.name}</div>
                    <div className="d-flex flex-wrap align-items-center gap-2" style={{ fontSize: "0.82rem" }}>
                      {latestDate ? <span className="text-muted">Latest {latestDate}</span> : null}
                      <span className="badge bg-warning text-dark">{group.entries.length} pending</span>
                    </div>
                  </div>
                  {group.entries.map(j => {
                    const weekLabel = j.week_number ?? j.entry_number
                    const excerpt = journalExcerpt(j)
                    return (
                      <div key={j.id} className="faculty-journal-queue-item d-flex align-items-start justify-content-between gap-3 px-3 py-2" style={{ borderTop: "1px solid #eef2f6" }}>
                        <div className="min-w-0">
                          <div className="d-flex flex-wrap align-items-center gap-2" style={{ fontSize: "0.82rem" }}>
                            <span className="text-primary fw-semibold">Week {weekLabel}</span>
                            {j.date && <span className="text-muted">{j.date}</span>}
                            <span className={`badge ${journalStatusClass(j.status)}`}>{(j.status || "—").replace(/_/g, " ")}</span>
                          </div>
                          {excerpt ? (
                            <p className="mt-1 mb-0 text-muted" style={{ fontSize: "0.85rem", lineHeight: 1.4 }}>{excerpt}</p>
                          ) : null}
                        </div>
                        <div className="d-flex align-items-center gap-2 ms-1 flex-shrink-0">
                          <button className="btn btn-sm btn-outline-secondary" onClick={() => openHistory(group.studentId, group.name)}>
                            <i className="fa fa-history me-1"></i>History
                          </button>
                          <button className="btn btn-sm btn-primary" onClick={() => openReview(j)}>
                            <i className="fa fa-pen me-1"></i>Review
                          </button>
                        </div>
                      </div>
                    )
                  })}
                </div>
              )
            })}
        </div>
      </div>
      
      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={closePreview}
        type={previewModal?.type}
        data={previewModal?.data || {}}
        onDownload={previewModal?.onDownload}
        loading={previewLoading}
        review={reviewJournal ? {
          journal: reviewJournal,
          processing,
          onSubmit: (action, feedback) => handleReview(reviewJournal.id, action, feedback),
        } : null}
      />
    </>
  )
}

// ─── Tab: Attendance Monitor ──────────────────────────────────────────────────
function TabAttendance() {
  const confirm = useConfirm()
  const [statusFilter, setStatusFilter] = useState("all")
  const [internshipId, setInternshipId] = useState("")
  const { loading, seed, run } = useCachedPage(`faculty:assigned-attendance:${statusFilter}:${internshipId || 'all'}`)
  const [rows, setRows] = useState(() => seed?.rows ?? [])
  const [students, setStudents] = useState([])
  const [corrections, setCorrections] = useState(() => seed?.corrections ?? [])
  const [error, setError] = useState(null)
  const [processing, setProcessing] = useState(null)
  const [message, setMessage] = useState(null)

  const fetchAttendance = () => {
    setError(null)
    const params = {}
    if (statusFilter && statusFilter !== "all") params.status = statusFilter
    if (internshipId) params.internship_id = internshipId
    run(() => Promise.all([
      api.get("/faculty/attendance", { params }),
      api.get("/faculty/dtr/corrections").catch(() => ({ data: { data: [] } })),
    ]).then(([res, corrRes]) => ({
      rows: unwrapList(res.data).items || [],
      corrections: unwrapList(corrRes.data).items || [],
    })))
      .then((next) => {
        if (next) {
          setRows(next.rows)
          setCorrections(next.corrections)
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || "Failed to load attendance.")
      })
  }
  useEffect(() => {
    api.get("/faculty/assigned-students").then(res => setStudents(unwrapList(res.data).items)).catch(() => setStudents([]))
  }, [])
  useEffect(() => {
    setRows(seed?.rows ?? [])
    setCorrections(seed?.corrections ?? [])
    fetchAttendance()
  }, [statusFilter, internshipId])

  const reviewCorrection = async (correction, action) => {
    const student = correction.student_name || 'this student'
    const verb = action === 'approved' ? 'Approve' : 'Reject'
    await confirm({
      title: `${verb} attendance correction?`,
      message: `${verb} the correction request for ${student} on ${formatDisplayDate(correction.date) || correction.date || '—'}? Original ${correction.original_clock_in || '—'}–${correction.original_clock_out || '—'}; requested ${correction.requested_clock_in || '—'}–${correction.requested_clock_out || '—'}.`,
      confirmLabel: verb,
      variant: action === 'approved' ? 'primary' : 'danger',
      run: async () => {
        setProcessing(correction.id)
        try {
          const res = await api.patch(`/faculty/dtr/corrections/${correction.id}`, { action })
          setMessage(res.data.message)
          fetchAttendance()
        } catch (err) {
          setMessage(err.response?.data?.message || "Action failed.")
          throw err
        } finally {
          setProcessing(null)
        }
      },
    })
  }

  return (
    <>
      {error && <PageError message={error} onRetry={fetchAttendance} />}
      {message && <div className="alert alert-info">{message}</div>}
      {corrections.length > 0 && (
        <div className="content-card mb-3">
          <div className="content-card-header">
            <i className="fa fa-clipboard-check"></i>
            <h6>Correction Requests (Faculty Review)</h6>
            <span className="ms-auto badge bg-warning text-dark">{corrections.length}</span>
          </div>
          <div className="table-responsive">
            <table className="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>Student</th><th>Date</th><th>Original</th><th>Requested</th><th>Status</th><th className="text-center">Actions</th></tr>
              </thead>
              <tbody>
                {corrections.map((c) => (
                  <tr key={c.id}>
                    <td className="fw-semibold">{c.student_name || "—"}</td>
                    <td>{formatDisplayDate(c.date) || c.date || "—"}</td>
                    <td>{c.original_clock_in || "—"} – {c.original_clock_out || "—"}</td>
                    <td>{c.requested_clock_in || "—"} – {c.requested_clock_out || "—"}</td>
                    <td>{c.status_label || c.status}</td>
                    <td className="text-center">
                      <AsyncButton className="btn btn-sm btn-success me-2" busy={processing === c.id} busyLabel="…" onClick={() => reviewCorrection(c, "approved")}>Approve</AsyncButton>
                      <AsyncButton className="btn btn-sm btn-danger" busy={processing === c.id} busyLabel="…" onClick={() => reviewCorrection(c, "rejected")}>Reject</AsyncButton>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
      <div className="content-card mb-3">
        <div className="content-card-header"><i className="fa fa-filter"></i><h6>Filters</h6></div>
        <div className="p-3 row g-3">
          <div className="col-md-4">
            <label className="form-label fw-semibold">Student</label>
            <select className="form-select" value={internshipId} onChange={e => setInternshipId(e.target.value)}>
              <option value="">All assigned students</option>
              {students.map(s => {
                const p = s.student?.student_profile || s.student?.studentProfile
                const name = p ? `${p.last_name || ""}, ${p.first_name || ""}`.trim() : (s.student?.username || `Internship #${s.id}`)
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
          {loading && rows.length === 0 ? <div className="text-center py-4"><InternTrackLoader /></div>
            : rows.length === 0 ? <div className="text-center py-4 text-muted">No attendance records for the selected filters.</div>
              : (
                <div className="table-responsive">
                  <table className="table table-hover mb-0 faculty-attendance-table">
                    <thead><tr><th>Student</th><th>Company</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th><th>Correction</th><th>Overtime</th><th>Remarks</th></tr></thead>
                    <tbody>
                      {groupAttendanceByStudent(rows).flatMap((group, gi) => {
                        const latestDate = String(latestJournalDate(group.entries) || "").slice(0, 10)
                        const groupTint = gi % 2 === 1 ? "#f7fbf8" : undefined
                        return group.entries.map((log, i) => (
                          <tr
                            key={log.id}
                            className="faculty-attendance-row"
                            style={{
                              background: groupTint,
                              borderTop: i === 0 && gi > 0 ? "2px solid #d9e8dc" : undefined,
                            }}
                          >
                            {i === 0 && (
                              <>
                                <td
                                  rowSpan={group.entries.length}
                                  className="faculty-attendance-group-head fw-bold"
                                  style={{ background: "#f8fafc", verticalAlign: "top", borderRight: "1px solid #eef2f6" }}
                                >
                                  <div>{group.name}</div>
                                  <div className="mt-1">
                                    <span className="badge bg-secondary">{group.entries.length} record{group.entries.length === 1 ? "" : "s"}</span>
                                  </div>
                                  {latestDate ? <div className="text-muted mt-1" style={{ fontSize: "0.78rem", fontWeight: 400 }}>Latest {latestDate}</div> : null}
                                </td>
                                <td
                                  rowSpan={group.entries.length}
                                  className="faculty-attendance-group-head"
                                  style={{ background: "#f8fafc", verticalAlign: "top" }}
                                >
                                  {group.company}
                                </td>
                              </>
                            )}
                            <td>{log.date ? String(log.date).slice(0, 10) : "—"}</td>
                            <td>{log.clock_in_display || log.clock_in || "—"}</td>
                            <td>{log.clock_out_display || log.clock_out || "—"}</td>
                            <td>{log.hours_rendered != null ? Number(log.hours_rendered).toFixed(2) : "—"}</td>
                            <td>{attStatusBadge(log.status)}</td>
                            <td>{log.correction_status_label || "—"}</td>
                            <td>{log.overtime_status || "none"}</td>
                            <td className="text-muted" style={{ fontSize: "0.85rem", maxWidth: 180 }}>{log.remarks || "—"}</td>
                          </tr>
                        ))
                      })}
                    </tbody>
                  </table>
                </div>
              )}
        </div>
        <p className="text-muted px-3 pb-3 mb-0" style={{ fontSize: "0.8rem" }}>Read-only monitoring of official DTR rows. Industry supervisors validate daily attendance; correction requests appear above only after supervisor approval.</p>
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

      <div hidden={tab !== "students"}><TabStudents /></div>
      <div hidden={tab !== "journals"}><TabJournals /></div>
      <div hidden={tab !== "attendance"}><TabAttendance /></div>
    </Wrapper>
  )
}

export default FacultyAssignedStudents
import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import StatusChangeModal from '../../components/StatusChangeModal'
import StatusHistoryModal from '../../components/StatusHistoryModal'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'
import { formatStudentName } from '../../utils/formatName'
import { useCachedPage } from '../../hooks/useCachedPage'
import InternTrackLoader from '../../components/InternTrackLoader'
import FormPreviewModal from '../../components/portfolio/FormPreviewModal'
import { openOfficialFo30 } from '../../utils/officialForm'


// Coordinators no longer edit student sections here: sections come from iEnroll/MISD.

function statusBadgeClass(status) {
  const s = status === 'ongoing' ? 'active' : status
  if (s === 'active' || s === 'placed') return 'badge-active'
  if (s === 'completed') return 'badge-completed'
  if (s === 'pending_placement') return 'badge-pending'
  if (s === 'suspended' || s === 'deferred' || s === 'expelled') return 'badge-inactive'
  return 'badge-inactive'
}

function statusLabel(status) {
  if (!status) return 'No Active Internship'
  const map = {
    ongoing: 'Active',
    active: 'Active',
    completed: 'Completed',
    suspended: 'Suspended',
    deferred: 'Deferred',
    expelled: 'Expelled',
    pending_placement: 'Pending Placement',
  }
  return map[status] || String(status).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function CoordRecords() {
  const [archived, setArchived] = useState(false)
  const cacheKey = `coordinator:records:${archived ? 1 : 0}`
  const { pending, seed, run } = useCachedPage(cacheKey)
  const [students, setStudents] = useState(() => seed ?? [])
  const [statusTarget, setStatusTarget] = useState(null)
  const [historyTarget, setHistoryTarget] = useState(null)
  const [message, setMessage] = useState(null)
  const [archiveBusy, setArchiveBusy] = useState(null)
  const [previewModal, setPreviewModal] = useState(null)

  const [search, setSearch] = useState("")
  const [programFilter, setProgramFilter] = useState("all")
  const [sectionFilter, setSectionFilter] = useState("all")
  const [statusFilter, setStatusFilter] = useState("all")

  const fetchRecords = () => {
    run(() => api.get('/coordinator/records', { params: { archived: archived ? 1 : 0 } }).then(res => unwrapList(res.data).items))
      .then((next) => { if (next) setStudents(next) })
      .catch(console.error)
  }

  useEffect(() => {
    if (seed) setStudents(seed)
    fetchRecords()
  }, [archived])

  const toggleArchive = async (student) => {
    setArchiveBusy(student.id)
    try {
      await api.patch(`/coordinator/students/${student.id}/archive`, { archived: !archived })
      setMessage({
        type: 'success',
        text: archived ? 'Student restored to Active.' : 'Student archived.',
      })
      fetchRecords()
    } catch (err) {
      setMessage({ type: 'danger', text: err.response?.data?.message || 'Archive action failed.' })
    } finally {
      setArchiveBusy(null)
    }
  }

  const programs = ["all", ...new Set(students.map(s => s.student_profile?.program?.code || "—").filter(x => x !== "—"))]
  const sections = ["all", ...new Set(students.map(s => formatYearSection(s.student_profile?.section) || "—").filter(x => x !== "—"))]

  const filtered = students.filter(student => {
    const name = formatStudentName(student).toLowerCase()
    const prog = student.student_profile?.program?.code || "—"
    const sec = formatYearSection(student.student_profile?.section) || "—"
    const st = student.active_internship?.status || "none"
    return (!search || name.includes(search.toLowerCase()))
      && (programFilter === "all" || prog === programFilter)
      && (sectionFilter === "all" || sec === sectionFilter)
      && (statusFilter === "all" || st === statusFilter)
  })

  return (
    <Layout title="Records & Placement" subtitle={CURRENT_TERM} icon="fa-folder-open" bodyClass="coordinator-page roster-page">
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {statusTarget && (
        <StatusChangeModal
          internshipId={statusTarget.internshipId}
          studentName={statusTarget.studentName}
          currentStatus={statusTarget.status}
          apiBase="coordinator"
          onClose={() => setStatusTarget(null)}
          onSaved={() => {
            setStatusTarget(null)
            setMessage({ type: 'success', text: 'Internship status updated; reason saved to history.' })
            fetchRecords()
          }}
        />
      )}

      {historyTarget && (
        <StatusHistoryModal
          internshipId={historyTarget.internshipId}
          studentName={historyTarget.studentName}
          apiBase="coordinator"
          onClose={() => setHistoryTarget(null)}
        />
      )}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 260 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input maxLength={100} className="form-control border-start-0 ps-0" placeholder="Search" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 170 }} value={programFilter} onChange={e => setProgramFilter(e.target.value)}>
          {programs.map(p => <option key={p} value={p}>{p === "all" ? "All Departments" : p}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 150 }} value={sectionFilter} onChange={e => setSectionFilter(e.target.value)}>
          {sections.map(s => <option key={s} value={s}>{s === "all" ? "All Sections" : s}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 160 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
          <option value="all">All Status</option>
          <option value="ongoing">Active / Ongoing</option>
          <option value="completed">Completed</option>
          <option value="suspended">Suspended</option>
          <option value="deferred">Deferred</option>
          <option value="expelled">Expelled</option>
          <option value="pending_placement">Pending Placement</option>
        </select>

        <div className="ms-auto btn-group">
          <button className={`btn btn-sm ${!archived ? "btn-primary" : "btn-outline-secondary"}`} onClick={() => setArchived(false)}>Active</button>
          <button className={`btn btn-sm ${archived ? "btn-secondary" : "btn-outline-secondary"}`} onClick={() => setArchived(true)}>Archived</button>
        </div>
      </div>

      <div className="content-card mb-4">
        <div className="content-card-header">
          <i className="fa fa-users"></i>
          <h6>{archived ? 'Archived Students' : 'Student Interns Roster'}</h6>
          <span className="ms-auto badge bg-secondary">{filtered.length} student{filtered.length !== 1 ? "s" : ""}</span>
        </div>
        <div className="table-card">
          <div className="table-responsive">
            {pending && students.length === 0 ? (
              <InternTrackLoader />
            ) : filtered.length === 0 ? (
              <div className="text-center py-5 text-muted">No students found matching your criteria.</div>
            ) : (
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map(student => {
                    const profile = student.student_profile
                    const internship = student.active_internship
                    const hasInternship = !!internship
                    const name = formatStudentName(student)
                    const st = internship?.status

                    return (
                      <tr key={student.id}>
                        <td>{name}</td>
                        <td>{profile?.student_number || student.username}</td>
                        <td>{student.student_profile?.program?.code || "—"}</td>
                        <td>{internship?.company?.company_name || '—'}</td>
                        <td>
                          <span className={`badge-status ${statusBadgeClass(st)}`}>
                            {statusLabel(st)}
                          </span>
                          {internship?.status_reason && (
                            <div className="text-muted mt-1" style={{ fontSize: '0.75rem', maxWidth: 180 }} title={internship.status_reason}>
                              {internship.status_reason.length > 48
                                ? `${internship.status_reason.slice(0, 48)}…`
                                : internship.status_reason}
                            </div>
                          )}
                        </td>
                        <td className="text-center">
                          <button
                            type="button"
                            className={`btn btn-sm ${archived ? 'btn-outline-success' : 'btn-outline-secondary'} me-1`}
                            title={archived ? 'Unarchive' : 'Archive'}
                            disabled={archiveBusy === student.id}
                            onClick={() => toggleArchive(student)}
                          >
                            <i className={`fa ${archived ? 'fa-box-open' : 'fa-box-archive'}`}></i>
                          </button>

                          {internship?.id ? (
                            <>
                              <button
                                className="btn btn-sm btn-outline-info me-1"
                                title="DTR Preview (FO-30)"
                                onClick={() => openOfficialFo30(internship.id, setPreviewModal).catch((err) => setMessage({ type: 'danger', text: err.response?.data?.message || 'Unable to load FO-30 preview.' }))}
                              >
                                <i className="fa fa-clock me-1"></i> DTR
                              </button>
                              <button
                                className="btn btn-sm btn-outline-primary me-1"
                                onClick={() => setStatusTarget({
                                  internshipId: internship.id,
                                  studentName: name,
                                  status: st,
                                })}
                              >
                                <i className="fa fa-tag me-1"></i> Status
                              </button>
                              <button
                                className="btn btn-sm btn-outline-secondary me-1"
                                onClick={() => setHistoryTarget({
                                  internshipId: internship.id,
                                  studentName: name,
                                })}
                              >
                                <i className="fa fa-clock-rotate-left me-1"></i> History
                              </button>
                            </>
                          ) : (
                            <button className="btn btn-sm btn-outline-secondary" disabled>
                              No internship
                            </button>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
      <FormPreviewModal
        isOpen={!!previewModal}
        onClose={() => setPreviewModal(null)}
        type={previewModal?.type}
        data={previewModal?.data || {}}
        onDownload={previewModal?.onDownload}
      />
    </Layout>
  )
}

export default CoordRecords

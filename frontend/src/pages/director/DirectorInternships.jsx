import { formatYearSection } from '../../utils/formatSection'
import { useState, useEffect } from 'react'
import Layout from '../../components/Layout'
import StatusChangeModal from '../../components/StatusChangeModal'
import StatusHistoryModal from '../../components/StatusHistoryModal'
import api from '../../services/api'
import { unwrapList } from '../../utils/apiList'
import { CURRENT_TERM } from '../../config/term'
import { formatStudentName } from '../../utils/formatName'

function AssignPlacementModal({ student, onClose, onAssigned }) {
  const [loading, setLoading] = useState(true)
  const [options, setOptions] = useState({ companies: [], faculty: [], supervisors: [] })
  const [form, setForm] = useState({ company_id: '', faculty_id: '', supervisor_id: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get('/director/placement-options')
      .then(res => {
        setOptions(res.data)
        if (res.data.companies.length > 0) setForm(f => ({ ...f, company_id: res.data.companies[0].id }))
        if (res.data.faculty.length > 0) setForm(f => ({ ...f, faculty_id: res.data.faculty[0].id }))
        if (res.data.supervisors.length > 0) setForm(f => ({ ...f, supervisor_id: res.data.supervisors[0].id }))
      })
      .catch(() => setError('Failed to load placement options.'))
      .finally(() => setLoading(false))
  }, [])

  const handleSubmit = (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)

    const internshipId = student.active_internship?.id
    if (!internshipId) {
      setError('Student does not have a pending internship record.')
      setSaving(false)
      return
    }

    api.post(`/director/internships/${internshipId}/place`, form)
      .then(res => {
        onAssigned(res.data.internship)
      })
      .catch(err => {
        setError(err.response?.data?.message || 'Failed to assign placement.')
      })
      .finally(() => setSaving(false))
  }

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ background: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Assign Placement: {student.student_profile?.first_name} {student.student_profile?.last_name}</h5>
              <button type="button" className="btn-close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              {error && <div className="alert alert-danger">{error}</div>}

              {loading ? (
                <div className="text-center py-3"><i className="fa fa-spinner fa-spin text-muted fa-2x"></i></div>
              ) : (
                <>
                  <div className="mb-3">
                    <label className="form-label">Host Training Establishment (Company)</label>
                    <select className="form-select" value={form.company_id} onChange={e => setForm({ ...form, company_id: e.target.value })} required>
                      {options.companies.map(c => <option key={c.id} value={c.id}>{c.company_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Faculty Supervisor</label>
                    <select className="form-select" value={form.faculty_id} onChange={e => setForm({ ...form, faculty_id: e.target.value })} required>
                      {options.faculty.map(f => <option key={f.id} value={f.id}>{f.faculty_profile?.first_name} {f.faculty_profile?.last_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Industry Supervisor</label>
                    <select className="form-select" value={form.supervisor_id} onChange={e => setForm({ ...form, supervisor_id: e.target.value })} required>
                      {options.supervisors.map(s => <option key={s.id} value={s.id}>{s.supervisor_profile?.first_name} {s.supervisor_profile?.last_name}</option>)}
                    </select>
                  </div>
                </>
              )}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn btn-primary" disabled={loading || saving}>
                {saving ? 'Assigning...' : 'Authorize Deployment'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

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

function DirectorInternships() {
  const [students, setStudents] = useState([])
  const [loading, setLoading] = useState(true)
  const [assigning, setAssigning] = useState(null)
  const [statusTarget, setStatusTarget] = useState(null)
  const [historyTarget, setHistoryTarget] = useState(null)
  const [message, setMessage] = useState(null)
  const [certLoading, setCertLoading] = useState(null)
  const [archived, setArchived] = useState(false)
  const [archiveBusy, setArchiveBusy] = useState(null)
  
  const [departments, setDepartments] = useState([])
  const [programs, setPrograms] = useState([])
  
  const [search, setSearch] = useState("")
  const [departmentFilter, setDepartmentFilter] = useState("all")
  const [programFilter, setProgramFilter] = useState("all")
  const [sectionFilter, setSectionFilter] = useState("all")
  const [statusFilter, setStatusFilter] = useState("all")

  useEffect(() => {
    api.get('/academic/departments')
      .then(res => setDepartments(res.data))
      .catch(console.error)
  }, [])

  useEffect(() => {
    if (departmentFilter === 'all') {
      api.get('/academic/programs')
        .then(res => setPrograms(res.data))
        .catch(console.error)
    } else {
      api.get('/academic/programs', { params: { department_id: departmentFilter } })
        .then(res => setPrograms(res.data))
        .catch(console.error)
    }
    setProgramFilter('all') // reset program when department changes
  }, [departmentFilter])

  const fetchRecords = () => {
    setLoading(true)
    api.get('/director/records', { params: { archived: archived ? 1 : 0 } })
      .then(res => setStudents(unwrapList(res.data).items))
      .catch(console.error)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchRecords()
  }, [archived])

  const toggleArchive = async (student) => {
    setArchiveBusy(student.id)
    try {
      await api.patch(`/director/students/${student.id}/archive`, { archived: !archived })
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

  const downloadCertificate = async (internshipId) => {
    setCertLoading(internshipId)
    try {
      // NOTE: Certificate generation is typically Coordinator, but we'll try Director if they have access.
      const res = await api.get(`/coordinator/internships/${internshipId}/certificate`, { responseType: 'blob' })
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      const a = document.createElement('a')
      a.href = url
      a.download = `completion-certificate-${internshipId}.pdf`
      a.click()
      window.URL.revokeObjectURL(url)
      setMessage({ type: 'success', text: 'Certificate PDF generated from student data.' })
    } catch (err) {
      let text = 'Could not generate certificate (status must be Completed).'
      if (err.response?.data instanceof Blob) {
        try {
          const j = JSON.parse(await err.response.data.text())
          text = j.message || text
        } catch { /* ignore */ }
      }
      setMessage({ type: 'danger', text })
    } finally {
      setCertLoading(null)
    }
  }
  
  const sections = ["all", ...new Set(students.map(s => formatYearSection(s.student_profile?.section) || "—").filter(x => x !== "—"))]

  const filteredStudents = students.filter(student => {
    const name = formatStudentName(student).toLowerCase()
    const deptId = student.student_profile?.department_id || "none"
    const progId = student.student_profile?.program_id || "none"
    const sec = formatYearSection(student.student_profile?.section) || "—"
    const st = student.active_internship?.status || "none"
    return (!search || name.includes(search.toLowerCase()))
      && (departmentFilter === "all" || deptId == departmentFilter)
      && (programFilter === "all" || progId == programFilter)
      && (sectionFilter === "all" || sec === sectionFilter)
      && (statusFilter === "all" || st === statusFilter)
  })

  return (
    <Layout title="Student Roster & Placements" subtitle={CURRENT_TERM} icon="fa-folder-open" bodyClass="director-page roster-page">
      {message && (
        <div className={`alert alert-${message.type} alert-dismissible mb-3`}>
          {message.text}
          <button className="btn-close" onClick={() => setMessage(null)}></button>
        </div>
      )}

      {assigning && (
        <AssignPlacementModal
          student={assigning}
          onClose={() => setAssigning(null)}
          onAssigned={() => {
            setAssigning(null)
            fetchRecords()
          }}
        />
      )}

      {statusTarget && (
        <StatusChangeModal
          internshipId={statusTarget.internshipId}
          studentName={statusTarget.studentName}
          currentStatus={statusTarget.status}
          apiBase="director"
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
          apiBase="director"
          onClose={() => setHistoryTarget(null)}
        />
      )}

      {/* Filters */}
      <div className="d-flex flex-wrap gap-3 align-items-center mb-4 p-3 bg-white rounded border shadow-sm">
        <div className="input-group input-group-sm" style={{ width: 220 }}>
          <span className="input-group-text bg-light text-muted border-end-0"><i className="fa fa-search"></i></span>
          <input className="form-control border-start-0 ps-0" placeholder="Search by name…" value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <select className="form-select form-select-sm text-secondary" style={{ width: 140 }} value={departmentFilter} onChange={e => setDepartmentFilter(e.target.value)}>
          <option value="all">All Depts</option>
          {departments.map(d => <option key={d.id} value={d.id} title={d.name}>{d.code}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 220 }} value={programFilter} onChange={e => setProgramFilter(e.target.value)}>
          <option value="all">All Programs</option>
          {programs.map(p => <option key={p.id} value={p.id} title={p.name}>{p.code}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 130 }} value={sectionFilter} onChange={e => setSectionFilter(e.target.value)}>
          {sections.map(s => <option key={s} value={s}>{s === "all" ? "All Sections" : s}</option>)}
        </select>
        <select className="form-select form-select-sm text-secondary" style={{ width: 140 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
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
          <h6>{archived ? 'Archived Students' : 'Student Interns Roster (Cross-Department)'}</h6>
          <span className="ms-auto badge bg-secondary">{filteredStudents.length} student{filteredStudents.length !== 1 ? "s" : ""}</span>
        </div>
        <div className="table-card">
          <div className="table-responsive">
            {loading ? (
              <div className="text-center py-5"><i className="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            ) : filteredStudents.length === 0 ? (
              <div className="text-center py-5 text-muted">No students match the selected filters.</div>
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
                  {filteredStudents.map(s => {
                    const profile = s.student_profile
                    const internship = s.active_internship
                    const hasInternship = !!internship
                    const name = formatStudentName(s)
                    const st = internship?.status

                    return (
                      <tr key={s.id}>
                        <td>{name}</td>
                        <td>{profile?.student_number || s.username}</td>
                        <td>
                        {s.student_profile?.program?.code ?? '—'}
                      </td>
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
                              disabled={archiveBusy === s.id}
                              onClick={() => toggleArchive(s)}
                            >
                              <i className={`fa ${archived ? 'fa-box-open' : 'fa-box-archive'}`}></i>
                            </button>
                            {st === 'pending_placement' ? (
                              <button className="btn btn-sm btn-primary me-1" onClick={() => setAssigning(s)}>
                                <i className="fa fa-map-pin me-1"></i> Assign
                              </button>
                            ) : null}
                            {internship?.id ? (
                              <>
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
    </Layout>
  )
}

export default DirectorInternships
